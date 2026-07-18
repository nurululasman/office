<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItemValue extends Model
{
    use HasUuids;

    protected $fillable = ['quotation_item_id', 'key', 'value', 'value_type', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
    }
}
