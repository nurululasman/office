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
        static::updating(function (Quotation $quotation): void {
            if (! in_array($quotation->getOriginal('status'), ['complete', 'void'], true)) {
                return;
            }

            $allowed = $quotation->getOriginal('status') === 'complete' ? ['status', 'lock_version'] : [];
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
            'quotation_date' => 'immutable_date', 'item_schema' => 'array', 'lock_version' => 'integer',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('position');
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
