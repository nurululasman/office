<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use HasUuids;

    public const PLACEHOLDER_CONTRACT_VERSION = 1;

    public const LEGACY_CONTENT_HTML = <<<'HTML'
<p>{{ company_display_name }}</p>
<p>{{ quotation_number }}</p>
<p>{{ quotation_date }}</p>
<p>{{ customer_name }}</p>
<p>{{ customer_address }}</p>
<p>{{ subject }}</p>
<p>{{ intro_text }}</p>
<div>{{ quotation_items }}</div>
<div>{{ quotation_terms }}</div>
<p>{{ closing_text }}</p>
<p>{{ sender_name }}</p>
<p>{{ sender_title }}</p>
HTML;

    protected $fillable = [
        'company_profile_id', 'type', 'template_key', 'version', 'name', 'status',
        'content_html', 'content_sha256', 'settings', 'item_schema',
        'default_intro_text', 'default_closing_text', 'default_terms', 'editor_config',
        'lock_version', 'created_by', 'updated_by', 'activated_by', 'activated_at', 'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (DocumentTemplate $template): void {
            $template->template_key ??= $template->type.'-default';
            $template->status ??= $template->is_active === false ? 'archived' : 'active';
            if ($template->isDirty('is_active') && ! $template->isDirty('status')) {
                $template->status = $template->is_active ? 'active' : 'archived';
            }
            $template->is_active = $template->status === 'active';
            $template->content_html ??= self::LEGACY_CONTENT_HTML;
            $template->content_sha256 = hash('sha256', $template->content_html);

            if ($template->isDirty('settings') && ! $template->isDirty('item_schema')) {
                $template->item_schema = $template->settings ?? [];
            } elseif ($template->isDirty('item_schema') && ! $template->isDirty('settings')) {
                $template->settings = $template->item_schema ?? [];
            } elseif ($template->item_schema === null) {
                $template->item_schema = $template->settings ?? [];
            }
            if ($template->settings === null) {
                $template->settings = $template->item_schema ?? [];
            }

            $template->default_terms ??= [];
            $template->editor_config ??= [];
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'settings' => 'array',
            'item_schema' => 'array',
            'default_terms' => 'array',
            'editor_config' => 'array',
            'lock_version' => 'integer',
            'activated_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function generatedFiles(): HasMany
    {
        return $this->hasMany(GeneratedFile::class, 'template_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $profile = $this->companyProfile;

        return [
            'template_id' => $this->getKey(),
            'template_key' => $this->template_key,
            'template_version' => $this->version,
            'content_html' => $this->content_html,
            'item_schema' => $this->item_schema,
            'company_profile' => $profile ? [
                'id' => $profile->getKey(),
                'company_code' => $profile->company_code,
                'legal_name' => $profile->legal_name,
                'display_name' => $profile->display_name,
                'address_lines' => $profile->address_lines,
                'city' => $profile->city,
                'postal_code' => $profile->postal_code,
                'country' => $profile->country,
                'email' => $profile->email,
                'phone' => $profile->phone,
                'website' => $profile->website,
                'logo_path' => $profile->logo_path,
                'logo_sha256' => $profile->logo_sha256,
                'primary_color' => $profile->primary_color,
            ] : null,
        ];
    }
}
