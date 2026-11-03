<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

class Quotation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Quotation $quotation): void {
            // Kept empty only for the legacy physical column; items live in content_html.
            $quotation->item_schema ??= [];
            if ($quotation->template_snapshot !== null) {
                return;
            }

            $template = DocumentTemplate::query()->with('companyProfile')->findOrFail($quotation->template_id);
            $quotation->template_snapshot = $template->snapshot();
            $quotation->template_content_sha256 = $template->content_sha256;
            $quotation->placeholder_contract_version = DocumentTemplate::PLACEHOLDER_CONTRACT_VERSION;
        });

        static::updating(function (Quotation $quotation): void {
            if (! in_array($quotation->getOriginal('status'), ['complete', 'void'], true)) {
                return;
            }

            $allowed = $quotation->getOriginal('status') === 'complete'
                ? ['status', 'lock_version', 'template_snapshot']
                : ['template_snapshot'];
            if (array_diff(array_keys($quotation->getDirty()), $allowed) !== []) {
                throw new LogicException('Quotation complete atau void bersifat immutable.');
            }
        });

        static::deleting(function (Quotation $quotation): void {
            if (in_array($quotation->status, ['complete', 'void'], true)) {
                throw new LogicException('Quotation complete atau void tidak dapat dihapus.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quotation_date' => 'immutable_date',
            'item_schema' => 'array',
            'template_snapshot' => 'array', 'placeholder_contract_version' => 'integer',
            'lock_version' => 'integer',
            'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime', 'approval_bypassed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isSelfSender(): bool
    {
        if ($this->sender_id === null) {
            return true;
        }

        return (int) $this->sender_id === (int) $this->created_by;
    }

    public function refreshCompanyProfileSnapshot(): bool
    {
        $profile = null;
        if ($this->template_id) {
            $template = DocumentTemplate::query()->with('companyProfile')->find($this->template_id);
            $profile = $template?->companyProfile;
        }

        if (! $profile) {
            $profileId = $this->template_snapshot['company_profile']['id'] ?? null;
            if ($profileId) {
                $profile = CompanyProfile::query()->find($profileId);
            }
        }

        if (! $profile) {
            return false;
        }

        $snapshot = $this->template_snapshot ?? [];
        $snapshot['company_profile'] = $profile->snapshot();
        $this->template_snapshot = $snapshot;

        $saved = $this->save();
        $this->unsetRelation('template');

        return $saved;
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(QuotationTerm::class)->orderBy('position');
    }

    public function generatedFiles(): MorphMany
    {
        return $this->morphMany(GeneratedFile::class, 'owner');
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvalBypasser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_bypassed_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
