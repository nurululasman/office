<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_type_id', 'sequence_value', 'period_year', 'number', 'title', 'purpose',
        'source_type', 'source_id', 'issued_at', 'issued_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'sequence_value' => 'integer',
            'period_year' => 'integer',
            'issued_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }
}
