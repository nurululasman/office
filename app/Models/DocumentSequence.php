<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    use HasUuids;

    protected $fillable = ['document_type_id', 'period_year', 'last_value'];

    protected function casts(): array
    {
        return ['period_year' => 'integer', 'last_value' => 'integer'];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
