<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyProfile extends Model
{
    use HasUuids;

    protected $fillable = ['company_code', 'legal_name', 'display_name', 'address_lines', 'city', 'postal_code', 'country', 'email', 'phone', 'website', 'tax_id', 'bank_information', 'logo_path', 'logo_sha256', 'stamp_path', 'stamp_sha256', 'primary_color', 'is_active'];

    protected function casts(): array
    {
        return ['address_lines' => 'array', 'is_active' => 'boolean'];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }
}
