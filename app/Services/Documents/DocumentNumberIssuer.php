<?php

namespace App\Services\Documents;

use App\Exceptions\DocumentIssuanceException;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentNumberIssuer
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly DocumentNumberPattern $patterns,
        private readonly AuditLogger $audit,
    ) {}

    public function issue(
        DocumentType $documentType,
        User $issuer,
        string $title,
        string $purpose,
        ?Model $source = null,
    ): Document {
        if ($source !== null && ($existing = $this->findBySource($source)) !== null) {
            return $existing;
        }

        $this->assertValidInput($documentType, $issuer, $title, $purpose, $source);

        try {
            return DB::transaction(function () use ($documentType, $issuer, $title, $purpose, $source): Document {
                $lockedType = DocumentType::query()->lockForUpdate()->findOrFail($documentType->getKey());

                if ($source !== null && ($existing = $this->findBySource($source)) !== null) {
                    return $existing;
                }

                if (! $lockedType->is_active) {
                    throw new DocumentIssuanceException('Tipe dokumen tidak aktif dan tidak dapat menerbitkan nomor baru.');
                }

                $businessTime = CarbonImmutable::now(config('office.business_timezone'));
                $periodYear = $businessTime->year;
                $this->ensureSequenceExists($lockedType, $periodYear);

                $sequence = DocumentSequence::query()
                    ->where('document_type_id', $lockedType->getKey())
                    ->where('period_year', $periodYear)
                    ->lockForUpdate()
                    ->firstOrFail();

                $sequence->last_value++;
                $sequence->save();

                $segments = $this->patterns->validateSegments(
                    $this->patterns->fromPattern($lockedType->number_pattern),
                );
                $number = $this->patterns->preview($segments, $businessTime, $sequence->last_value);

                $document = Document::query()->create([
                    'document_type_id' => $lockedType->getKey(),
                    'sequence_value' => $sequence->last_value,
                    'period_year' => $periodYear,
                    'number' => $number,
                    'title' => trim($title),
                    'purpose' => trim($purpose),
                    'source_type' => $source?->getMorphClass(),
                    'source_id' => $source?->getKey(),
                    'issued_at' => $businessTime->utc(),
                    'issued_by' => $issuer->getKey(),
                ]);

                $this->audit->record(
                    'document.issued',
                    actor: $issuer,
                    subject: $document,
                    after: $document->only([
                        'document_type_id', 'sequence_value', 'period_year', 'number', 'title',
                        'purpose', 'source_type', 'source_id', 'issued_at', 'issued_by',
                    ]),
                );

                return $document;
            }, self::TRANSACTION_ATTEMPTS);
        } catch (QueryException $exception) {
            // A competing transaction may have completed the same source first.
            if ($source !== null && ($existing = $this->findBySource($source)) !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function ensureSequenceExists(DocumentType $documentType, int $periodYear): void
    {
        $now = now('UTC');

        DocumentSequence::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'document_type_id' => $documentType->getKey(),
            'period_year' => $periodYear,
            'last_value' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function findBySource(Model $source): ?Document
    {
        if ($source->getKey() === null) {
            return null;
        }

        return Document::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->first();
    }

    private function assertValidInput(
        DocumentType $documentType,
        User $issuer,
        string $title,
        string $purpose,
        ?Model $source,
    ): void {
        if (! $documentType->exists) {
            throw new DocumentIssuanceException('Tipe dokumen harus sudah tersimpan.');
        }
        if (! $issuer->exists) {
            throw new DocumentIssuanceException('Penerbit nomor harus merupakan user tersimpan.');
        }
        if (trim($title) === '' || mb_strlen(trim($title)) > 255) {
            throw new DocumentIssuanceException('Judul dokumen wajib diisi dan maksimal 255 karakter.');
        }
        if (trim($purpose) === '') {
            throw new DocumentIssuanceException('Peruntukan dokumen wajib diisi.');
        }
        if ($source !== null && (! $source->exists || ! is_string($source->getKey()) || ! Str::isUuid($source->getKey()))) {
            throw new DocumentIssuanceException('Source dokumen harus merupakan model UUID yang sudah tersimpan.');
        }
    }
}
