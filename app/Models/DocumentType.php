<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'name', 'number_pattern', 'reset_period', 'approval_mode', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
