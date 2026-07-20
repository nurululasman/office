<?php

namespace App\Services\Documents;

use App\Models\DocumentSequence;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentSequenceManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function setLatestValue(
        DocumentType $documentType,
        int $periodYear,
        int $latestValue,
        User $actor,
        ?Request $request = null,
    ): ?DocumentSequence {
        if ($latestValue === 0 && ! $documentType->sequences()->where('period_year', $periodYear)->exists()) {
            return null;
        }

        $now = now('UTC');
        DocumentSequence::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'document_type_id' => $documentType->getKey(),
            'period_year' => $periodYear,
            'last_value' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DocumentSequence::query()
            ->where('document_type_id', $documentType->getKey())
            ->where('period_year', $periodYear)
            ->lockForUpdate()
            ->firstOrFail();

        if ($latestValue < $sequence->last_value) {
            throw ValidationException::withMessages([
                'latest_sequence' => "Latest sequence tidak boleh diturunkan dari {$sequence->last_value}.",
            ]);
        }

        if ($latestValue === $sequence->last_value) {
            return $sequence;
        }

        $before = $sequence->only(['document_type_id', 'period_year', 'last_value']);
        $sequence->update(['last_value' => $latestValue]);
        $this->audit->record(
            'document_sequence.latest_value_updated',
            actor: $actor,
            subject: $sequence,
            before: $before,
            after: $sequence->only(['document_type_id', 'period_year', 'last_value']),
            request: $request,
        );

        return $sequence;
    }
}
