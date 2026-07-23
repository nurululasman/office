<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationItem extends Model
{
    use HasUuids;

    protected $fillable = ['quotation_id', 'parent_item_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id')->orderBy('position');
    }

    public function values(): HasMany
    {
        return $this->hasMany(QuotationItemValue::class)->orderBy('position');
    }
}
