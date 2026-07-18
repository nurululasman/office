<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentRegistrySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_registry_models_use_uuid_keys_and_expose_relations(): void
    {
        $user = User::factory()->create();
        $type = DocumentType::query()->create([
            'code' => 'QUOTATION',
            'name' => 'Quotation',
            'number_pattern' => 'QT-JBLU-{YYYY}{MM}{SEQ:4}',
        ]);
        $sequence = $type->sequences()->create(['period_year' => 2026, 'last_value' => 1]);
        $document = $type->documents()->create([
            'sequence_value' => 1,
            'period_year' => 2026,
            'number' => 'QT-JBLU-2026070001',
            'title' => 'Quotation test',
            'purpose' => 'Schema verification',
            'issued_at' => now(),
            'issued_by' => $user->getKey(),
        ]);
        $type->refresh();

        $this->assertTrue(Str::isUuid($type->getKey()));
        $this->assertTrue(Str::isUuid($sequence->getKey()));
        $this->assertTrue(Str::isUuid($document->getKey()));
        $this->assertTrue($sequence->documentType->is($type));
        $this->assertTrue($document->documentType->is($type));
        $this->assertTrue($document->issuer->is($user));
        $this->assertSame(1, $document->sequence_value);
        $this->assertTrue($type->is_active);
    }

    public function test_sequence_is_unique_per_document_type_and_year(): void
    {
        $type = $this->createDocumentType('GENERAL');
        DocumentSequence::query()->create([
            'document_type_id' => $type->getKey(),
            'period_year' => 2026,
            'last_value' => 1,
        ]);

        $this->expectException(QueryException::class);

        DocumentSequence::query()->create([
            'document_type_id' => $type->getKey(),
            'period_year' => 2026,
            'last_value' => 2,
        ]);
    }

    public function test_document_sequence_and_number_are_unique_within_type_and_year(): void
    {
        $user = User::factory()->create();
        $type = $this->createDocumentType('CONTRACT');
        $attributes = [
            'document_type_id' => $type->getKey(),
            'sequence_value' => 1,
            'period_year' => 2026,
            'number' => 'CTR-JBLU-2026070001',
            'title' => 'Contract test',
            'purpose' => 'Schema verification',
            'issued_at' => now(),
            'issued_by' => $user->getKey(),
        ];
        Document::query()->create($attributes);

        $this->expectException(QueryException::class);
        Document::query()->create($attributes);
    }

    public function test_source_entity_can_only_receive_one_document_number(): void
    {
        $user = User::factory()->create();
        $type = $this->createDocumentType('SOURCE');
        $sourceId = (string) Str::uuid();
        $base = [
            'document_type_id' => $type->getKey(),
            'period_year' => 2026,
            'title' => 'Source test',
            'purpose' => 'Idempotency constraint verification',
            'source_type' => 'quotation',
            'source_id' => $sourceId,
            'issued_at' => now(),
            'issued_by' => $user->getKey(),
        ];

        Document::query()->create($base + ['sequence_value' => 1, 'number' => 'SRC-0001']);

        $this->expectException(QueryException::class);
        Document::query()->create($base + ['sequence_value' => 2, 'number' => 'SRC-0002']);
    }

    public function test_audit_subject_supports_legacy_numeric_and_domain_uuid_keys(): void
    {
        $user = User::factory()->create();
        $type = $this->createDocumentType('AUDIT');

        $userAudit = AuditLog::query()->create([
            'action' => 'user.updated',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->getKey(),
            'occurred_at' => now(),
        ]);
        $typeAudit = AuditLog::query()->create([
            'action' => 'document_type.created',
            'subject_type' => $type->getMorphClass(),
            'subject_id' => $type->getKey(),
            'occurred_at' => now(),
        ]);

        $this->assertTrue($userAudit->subject->is($user));
        $this->assertTrue($typeAudit->subject->is($type));
    }

    private function createDocumentType(string $code): DocumentType
    {
        return DocumentType::query()->create([
            'code' => $code,
            'name' => ucfirst(strtolower($code)),
            'number_pattern' => $code.'-{SEQ:4}',
        ]);
    }
}
