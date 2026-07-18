<?php

namespace App\Services\Documents;

use App\Exceptions\DocumentVoidException;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DocumentVoidService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function void(Document $document, User $actor, string $reason, ?Request $request = null): Document
    {
        if ($document->voided_at !== null) {
            return $document;
        }
        if (mb_strlen(trim($reason)) < 5 || mb_strlen(trim($reason)) > 2000) {
            throw new DocumentVoidException('Alasan void wajib berisi 5 sampai 2.000 karakter.');
        }

        return DB::transaction(function () use ($document, $actor, $reason, $request): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($locked->voided_at !== null) {
                return $locked;
            }

            $before = $locked->only(['voided_at', 'voided_by', 'void_reason']);
            $locked->update([
                'voided_at' => now('UTC'),
                'voided_by' => $actor->getKey(),
                'void_reason' => trim($reason),
            ]);
            $this->audit->record(
                'document.voided',
                actor: $actor,
                subject: $locked,
                before: $before,
                after: $locked->only(['voided_at', 'voided_by', 'void_reason']),
                request: $request,
            );

            return $locked;
        }, 3);
    }
}
