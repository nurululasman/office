<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use HasUuids;

    protected $fillable = ['company_profile_id', 'type', 'version', 'name', 'settings', 'is_active'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'settings' => 'array', 'is_active' => 'boolean'];
    }

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function generatedFiles(): HasMany
    {
        return $this->hasMany(GeneratedFile::class, 'template_id');
    }
}
