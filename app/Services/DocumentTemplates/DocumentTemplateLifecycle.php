<?php

namespace App\Services\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class DocumentTemplateLifecycle
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DocumentTemplateHtmlSanitizer $sanitizer,
        private readonly DocumentTemplatePlaceholderValidator $placeholders,
        private readonly QuotationItemPresentation $itemPresentation,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    public function createDraft(array $attributes, User $actor, ?Request $request = null): DocumentTemplate
    {
        Gate::forUser($actor)->authorize('create', DocumentTemplate::class);

        return DB::transaction(function () use ($attributes, $actor, $request): DocumentTemplate {
            $attributes['content_html'] = $this->sanitizer->sanitize((string) ($attributes['content_html'] ?? ''));
            $this->placeholders->validateDraft($attributes['content_html']);
            $this->itemPresentation->resolve(is_array($attributes['item_schema'] ?? null) ? $attributes['item_schema'] : []);
            $template = DocumentTemplate::query()->create(array_merge($attributes, [
                'type' => 'quotation',
                'status' => 'draft',
                'is_active' => false,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]));

            $this->audit->record(
                'quotation_template.created',
                $actor,
                $template,
                after: $this->auditState($template),
                request: $request,
            );

            return $template;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    public function updateDraft(
        DocumentTemplate $template,
        array $attributes,
        int $expectedLockVersion,
        User $actor,
        ?Request $request = null,
    ): DocumentTemplate {
        Gate::forUser($actor)->authorize('update', $template);

        return DB::transaction(function () use ($template, $attributes, $expectedLockVersion, $actor, $request): DocumentTemplate {
            $locked = DocumentTemplate::query()->lockForUpdate()->findOrFail($template->getKey());
            $this->assertDraftAndVersion($locked, $expectedLockVersion);
            $before = $this->auditState($locked);

            if (array_key_exists('content_html', $attributes)) {
                $attributes['content_html'] = $this->sanitizer->sanitize((string) $attributes['content_html']);
                $this->placeholders->validateDraft($attributes['content_html']);
            }
            if (array_key_exists('item_schema', $attributes)) {
                $this->itemPresentation->resolve(is_array($attributes['item_schema']) ? $attributes['item_schema'] : []);
            }
            $locked->fill(collect($attributes)->only([
                'name', 'content_html', 'item_schema', 'default_intro_text',
                'default_closing_text', 'default_terms', 'editor_config',
            ])->all());
            $locked->status = 'draft';
            $locked->updated_by = $actor->getKey();
            $locked->lock_version++;
            $locked->save();

            $this->audit->record(
                'quotation_template.updated',
                $actor,
                $locked,
                before: $before,
                after: $this->auditState($locked),
                request: $request,
            );

            return $locked;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function createVersion(
        DocumentTemplate $source,
        User $actor,
        ?Request $request = null,
    ): DocumentTemplate {
        Gate::forUser($actor)->authorize('createVersion', $source);

        return DB::transaction(function () use ($source, $actor, $request): DocumentTemplate {
            $lockedSource = DocumentTemplate::query()->lockForUpdate()->findOrFail($source->getKey());
            $versions = DocumentTemplate::query()
                ->where('type', $lockedSource->type)
                ->where('template_key', $lockedSource->template_key)
                ->lockForUpdate()
                ->pluck('version');
            $nextVersion = (int) $versions->max() + 1;

            $copy = $lockedSource->replicate([
                'status', 'is_active', 'lock_version', 'created_by', 'updated_by',
                'activated_by', 'activated_at', 'created_at', 'updated_at',
            ]);
            $copy->version = $nextVersion;
            $copy->name = $lockedSource->name.' v'.$nextVersion;
            $copy->status = 'draft';
            $copy->is_active = false;
            $copy->lock_version = 0;
            $copy->created_by = $actor->getKey();
            $copy->updated_by = $actor->getKey();
            $copy->activated_by = null;
            $copy->activated_at = null;
            $copy->save();

            $this->audit->record(
                'quotation_template.version_created',
                $actor,
                $copy,
                after: $this->auditState($copy),
                context: ['source_template_id' => $lockedSource->getKey(), 'source_version' => $lockedSource->version],
                request: $request,
            );

            return $copy;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function activate(
        DocumentTemplate $template,
        int $expectedLockVersion,
        User $actor,
        ?Request $request = null,
    ): DocumentTemplate {
        Gate::forUser($actor)->authorize('activate', $template);

        return DB::transaction(function () use ($template, $expectedLockVersion, $actor, $request): DocumentTemplate {
            $locked = DocumentTemplate::query()->lockForUpdate()->findOrFail($template->getKey());
            $this->assertDraftAndVersion($locked, $expectedLockVersion);
            $before = $this->auditState($locked);
            $locked->content_html = $this->sanitizer->sanitize($locked->content_html);
            $this->placeholders->validateForActivation($locked->content_html);
            $this->itemPresentation->resolve($locked->item_schema);

            $active = DocumentTemplate::query()
                ->where('type', $locked->type)
                ->where('template_key', $locked->template_key)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($active) {
                $before = $this->auditState($active);
                $active->status = 'archived';
                $active->is_active = false;
                $active->updated_by = $actor->getKey();
                $active->lock_version++;
                $active->save();
                $this->audit->record(
                    'quotation_template.archived',
                    $actor,
                    $active,
                    before: $before,
                    after: $this->auditState($active),
                    context: ['reason' => 'superseded', 'replacement_template_id' => $locked->getKey()],
                    request: $request,
                );
            }

            $locked->status = 'active';
            $locked->is_active = true;
            $locked->activated_by = $actor->getKey();
            $locked->activated_at = now();
            $locked->updated_by = $actor->getKey();
            $locked->lock_version++;
            $locked->save();

            $this->audit->record(
                'quotation_template.activated',
                $actor,
                $locked,
                before: $before,
                after: $this->auditState($locked),
                request: $request,
            );

            return $locked;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function archive(
        DocumentTemplate $template,
        int $expectedLockVersion,
        User $actor,
        ?Request $request = null,
    ): DocumentTemplate {
        Gate::forUser($actor)->authorize('archive', $template);

        return DB::transaction(function () use ($template, $expectedLockVersion, $actor, $request): DocumentTemplate {
            $locked = DocumentTemplate::query()->lockForUpdate()->findOrFail($template->getKey());
            if (! in_array($locked->status, ['draft', 'active'], true) || $locked->lock_version !== $expectedLockVersion) {
                throw new LogicException('Template telah berubah atau tidak dapat diarsipkan.');
            }
            if ($locked->status === 'active' && ! DocumentTemplate::query()
                ->where('type', 'quotation')
                ->where('status', 'active')
                ->whereKeyNot($locked->getKey())
                ->exists()) {
                throw new LogicException('Minimal satu template quotation harus tetap aktif.');
            }

            $before = $this->auditState($locked);
            $locked->status = 'archived';
            $locked->is_active = false;
            $locked->updated_by = $actor->getKey();
            $locked->lock_version++;
            $locked->save();

            $this->audit->record(
                'quotation_template.archived',
                $actor,
                $locked,
                before: $before,
                after: $this->auditState($locked),
                context: ['reason' => 'manual'],
                request: $request,
            );

            return $locked;
        });
    }

    private function assertDraftAndVersion(DocumentTemplate $template, int $expectedLockVersion): void
    {
        if ($template->status !== 'draft' || $template->lock_version !== $expectedLockVersion) {
            throw new LogicException('Template telah berubah atau bukan draft yang dapat diedit.');
        }
    }

    /** @return array<string, mixed> */
    private function auditState(DocumentTemplate $template): array
    {
        return [
            'template_key' => $template->template_key,
            'type' => $template->type,
            'version' => $template->version,
            'name' => $template->name,
            'status' => $template->status,
            'content_sha256' => $template->content_sha256,
            'lock_version' => $template->lock_version,
            'company_profile_id' => $template->company_profile_id,
            'activated_by' => $template->activated_by,
            'activated_at' => $template->activated_at?->toIso8601String(),
        ];
    }
}
