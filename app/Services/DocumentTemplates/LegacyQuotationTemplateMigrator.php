<?php

namespace App\Services\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

final class LegacyQuotationTemplateMigrator
{
    public const MARKER = 'document-template-step-12-v1';

    private const CONTENT = <<<'HTML'
<table style="width: 100%; border-collapse: collapse">
<tbody><tr><td><h2>{{ company_legal_name }}</h2><p>{{ company_address }}</p><p>{{ company_email }}</p><p>{{ company_phone }}</p></td><td style="text-align: right"><div>{{ company_logo }}</div></td></tr></tbody>
</table>
<hr>
<table style="width: 100%; border-collapse: collapse">
<tbody>
<tr><td><strong>No.:</strong> {{ quotation_number }}</td><td><strong>Date:</strong> {{ quotation_date }}</td></tr>
<tr><td><strong>To:</strong> {{ customer_name }}</td><td><strong>From:</strong> {{ sender_name }}<br>{{ sender_title }}</td></tr>
<tr><td><strong>Address:</strong> {{ customer_address }}</td><td><strong>Subject:</strong> {{ subject }}</td></tr>
</tbody>
</table>
<p>{{ intro_text }}</p>
<div>{{ quotation_items }}</div>
<div>{{ quotation_terms }}</div>
<p>{{ closing_text }}</p>
<div>{{ signature_block }}</div>
<div>{{ draft_watermark }}</div>
HTML;

    public function __construct(
        private readonly DocumentTemplateLifecycle $lifecycle,
        private readonly DocumentTemplateHtmlSanitizer $sanitizer,
        private readonly DocumentTemplatePlaceholderValidator $placeholders,
        private readonly QuotationItemPresentation $itemPresentation,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{candidates: list<array<string, mixed>>, migrated: list<array<string, mixed>>} */
    public function plan(): array
    {
        $this->assertSchemaReady();
        $templates = DocumentTemplate::query()
            ->withCount('generatedFiles')
            ->where('type', 'quotation')
            ->where('template_key', 'quotation-default')
            ->orderBy('version')
            ->get();

        return [
            'candidates' => $templates
                ->filter(fn (DocumentTemplate $template): bool => $this->isLegacyCandidate($template))
                ->map(fn (DocumentTemplate $template): array => $this->summary($template))
                ->values()->all(),
            'migrated' => $templates
                ->filter(fn (DocumentTemplate $template): bool => data_get($template->editor_config, 'migration.marker') === self::MARKER)
                ->map(fn (DocumentTemplate $template): array => $this->summary($template))
                ->values()->all(),
        ];
    }

    /** @return list<DocumentTemplate> */
    public function migrate(User $actor): array
    {
        $this->assertSchemaReady();
        $this->authorizeActor($actor);
        $sources = DocumentTemplate::query()
            ->where('type', 'quotation')
            ->where('template_key', 'quotation-default')
            ->where('status', 'active')
            ->orderBy('version')
            ->get()
            ->filter(fn (DocumentTemplate $template): bool => $this->isLegacyCandidate($template));

        $migrated = [];
        foreach ($sources as $source) {
            $migrated[] = DB::transaction(function () use ($source, $actor): DocumentTemplate {
                $copy = $this->lifecycle->createVersion($source, $actor);
                $schema = is_array($source->item_schema) && $source->item_schema !== []
                    ? $source->item_schema
                    : $source->settings;
                $copy = $this->lifecycle->updateDraft($copy, [
                    'name' => $source->name.' — WYSIWYG',
                    'content_html' => $this->sanitizer->sanitize(self::CONTENT),
                    'item_schema' => $schema,
                    'editor_config' => array_replace_recursive($copy->editor_config ?? [], [
                        'migration' => [
                            'marker' => self::MARKER,
                            'source_template_id' => $source->getKey(),
                            'source_version' => $source->version,
                        ],
                    ]),
                ], $copy->lock_version, $actor);

                return $this->lifecycle->activate($copy, $copy->lock_version, $actor);
            }, 3);
        }

        return $migrated;
    }

    public function rollback(User $actor): int
    {
        $this->assertSchemaReady();
        $this->authorizeActor($actor);
        $active = DocumentTemplate::query()
            ->where('type', 'quotation')
            ->where('template_key', 'quotation-default')
            ->where('status', 'active')
            ->get()
            ->filter(fn (DocumentTemplate $template): bool => data_get($template->editor_config, 'migration.marker') === self::MARKER);

        $count = 0;
        foreach ($active as $replacement) {
            DB::transaction(function () use ($replacement, $actor, &$count): void {
                $lockedReplacement = DocumentTemplate::query()->lockForUpdate()->findOrFail($replacement->getKey());
                $sourceId = data_get($lockedReplacement->editor_config, 'migration.source_template_id');
                $source = DocumentTemplate::query()->lockForUpdate()->find($sourceId);
                if (! $source || $source->type !== 'quotation' || $source->template_key !== $lockedReplacement->template_key) {
                    throw new LogicException('Template sumber rollback tidak ditemukan atau tidak cocok.');
                }

                $this->placeholders->validateForActivation($source->content_html);
                $this->itemPresentation->resolve($source->item_schema);
                $lockedReplacement->update([
                    'status' => 'archived', 'is_active' => false,
                    'updated_by' => $actor->getKey(), 'lock_version' => $lockedReplacement->lock_version + 1,
                ]);
                $source->update([
                    'status' => 'active', 'is_active' => true,
                    'activated_by' => $actor->getKey(), 'activated_at' => now(),
                    'updated_by' => $actor->getKey(), 'lock_version' => $source->lock_version + 1,
                ]);
                $this->audit->record('quotation_template.migration_rolled_back', $actor, $source, context: [
                    'marker' => self::MARKER,
                    'archived_template_id' => $lockedReplacement->getKey(),
                ]);
                $count++;
            }, 3);
        }

        return $count;
    }

    private function isLegacyCandidate(DocumentTemplate $template): bool
    {
        return $template->status === 'active'
            && hash_equals(
                hash('sha256', DocumentTemplate::LEGACY_CONTENT_HTML),
                hash('sha256', (string) $template->content_html),
            );
    }

    private function assertSchemaReady(): void
    {
        foreach (['template_key', 'status', 'content_html', 'item_schema', 'editor_config'] as $column) {
            if (! Schema::hasColumn('document_templates', $column)) {
                throw new LogicException(
                    "Schema template belum siap: kolom document_templates.{$column} tidak ditemukan. "
                    .'Jalankan preflight dan migration database terlebih dahulu, lalu ulangi dry-run.',
                );
            }
        }
        if (! Schema::hasColumn('quotations', 'template_snapshot')) {
            throw new LogicException(
                'Schema quotation belum siap: kolom quotations.template_snapshot tidak ditemukan. '
                .'Jalankan preflight dan migration database terlebih dahulu, lalu ulangi dry-run.',
            );
        }
    }

    private function authorizeActor(User $actor): void
    {
        if (! $actor->is_active
            || ! $actor->hasPermissionTo('quotation-template.create')
            || ! $actor->hasPermissionTo('quotation-template.activate')
            || ! $actor->hasPermissionTo('quotation-template.archive')) {
            throw new LogicException('Actor migrasi harus aktif dan memiliki permission pengelolaan template.');
        }
    }

    /** @return array<string, mixed> */
    private function summary(DocumentTemplate $template): array
    {
        return [
            'id' => $template->getKey(),
            'name' => $template->name,
            'version' => $template->version,
            'status' => $template->status,
            'columns' => count($template->item_schema['columns'] ?? []),
            'quotation_count' => $template->quotations()->count(),
            'generated_file_count' => $template->generated_files_count,
        ];
    }
}
