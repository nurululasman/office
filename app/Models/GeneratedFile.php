<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeneratedFile extends Model
{
    use HasUuids;

    protected $fillable = ['owner_type', 'owner_id', 'template_id', 'kind', 'disk', 'path', 'mime_type', 'size', 'sha256', 'generated_at', 'generated_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'generated_at' => 'immutable_datetime'];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
