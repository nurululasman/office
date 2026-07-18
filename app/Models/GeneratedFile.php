<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeneratedFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_type', 'owner_id', 'template_id', 'kind', 'status', 'attempts', 'disk', 'path',
        'mime_type', 'size', 'sha256', 'last_error', 'queued_at', 'started_at', 'generated_at', 'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer', 'attempts' => 'integer', 'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime', 'generated_at' => 'immutable_datetime',
        ];
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
