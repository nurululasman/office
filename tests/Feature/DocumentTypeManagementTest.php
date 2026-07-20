<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentSequence;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Documents\DocumentNumberIssuer;
use App\Services\Documents\DocumentNumberPattern;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorized_user_can_create_type_from_segments_and_change_future_pattern(): void
    {
        $admin = $this->userWithRole('document-admin');

        $this->actingAs($admin)->withSession($this->validSsoSession())->post('/document-types', [
            'code' => 'quotation',
            'name' => 'Quotation',
            'approval_mode' => 'maker_checker',
            'latest_sequence' => 0,
            'segments' => [
                ['type' => 'literal', 'value' => 'QT-JBLU-'],
                ['type' => 'token', 'value' => 'YYYY'],
                ['type' => 'token', 'value' => 'MM'],
                ['type' => 'sequence', 'width' => 4],
            ],
        ])->assertRedirect('/document-types');

        $type = DocumentType::query()->sole();
        $this->assertSame('QUOTATION', $type->code);
        $this->assertSame('QT-JBLU-{YYYY}{MM}{SEQ:4}', $type->number_pattern);
        $this->assertSame('maker_checker', $type->approval_mode);
        $this->assertTrue($type->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_type.created', 'subject_id' => $type->getKey()]);

        $this->actingAs($admin)->withSession($this->validSsoSession())->put("/document-types/{$type->getKey()}", [
            'code' => 'QUOTATION',
            'name' => 'Quotation JBLU',
            'approval_mode' => 'direct',
            'latest_sequence' => 0,
            'segments' => [
                ['type' => 'sequence', 'width' => 4],
                ['type' => 'literal', 'value' => '/JBLU/'],
                ['type' => 'token', 'value' => 'MONTH_ROMAN'],
                ['type' => 'literal', 'value' => '/'],
                ['type' => 'token', 'value' => 'YYYY'],
            ],
        ])->assertRedirect('/document-types');

        $this->assertSame('{SEQ:4}/JBLU/{MONTH_ROMAN}/{YYYY}', $type->refresh()->number_pattern);
        $this->assertTrue($type->is_active);
        $this->assertSame(1, AuditLog::query()->where('action', 'document_type.updated')->count());
    }

    public function test_pattern_requires_one_valid_sequence_and_rejects_unsafe_literals(): void
    {
        $admin = $this->userWithRole('document-admin');
        $base = ['code' => 'GENERAL', 'name' => 'General', 'approval_mode' => 'direct', 'latest_sequence' => 0];

        $this->actingAs($admin)->withSession($this->validSsoSession())->post('/document-types', $base + [
            'segments' => [['type' => 'literal', 'value' => 'DOC-']],
        ])->assertSessionHasErrors('segments');

        $this->actingAs($admin)->withSession($this->validSsoSession())->post('/document-types', $base + [
            'segments' => [
                ['type' => 'literal', 'value' => '../<script>'],
                ['type' => 'sequence', 'width' => 11],
            ],
        ])->assertSessionHasErrors(['segments.0.value']);

        $this->assertDatabaseCount('document_types', 0);
    }

    public function test_preview_uses_business_date_and_supported_tokens(): void
    {
        CarbonImmutable::setTestNow('2026-07-18 03:00:00 UTC');
        $admin = $this->userWithRole('document-admin');

        $this->actingAs($admin)->withSession($this->validSsoSession())->postJson('/document-types/preview', [
            'segments' => [
                ['type' => 'sequence', 'width' => 4],
                ['type' => 'literal', 'value' => '/JBLU/'],
                ['type' => 'token', 'value' => 'MONTH_ROMAN'],
                ['type' => 'literal', 'value' => '/'],
                ['type' => 'token', 'value' => 'YYYY'],
            ],
        ])->assertOk()->assertExactJson([
            'pattern' => '{SEQ:4}/JBLU/{MONTH_ROMAN}/{YYYY}',
            'preview' => '0001/JBLU/VII/2026',
        ]);

        CarbonImmutable::setTestNow();
    }

    public function test_admin_can_set_latest_sequence_and_next_issue_continues_from_it(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 03:00:00 UTC');
        $admin = $this->userWithRole('document-admin');

        $this->actingAs($admin)->withSession($this->validSsoSession())->post('/document-types', [
            'code' => 'GENERAL',
            'name' => 'General',
            'approval_mode' => 'direct',
            'latest_sequence' => 125,
            'segments' => [
                ['type' => 'literal', 'value' => 'DOC-'],
                ['type' => 'sequence', 'width' => 4],
            ],
        ])->assertRedirect('/document-types');

        $type = DocumentType::query()->sole();
        $this->assertDatabaseHas('document_sequences', [
            'document_type_id' => $type->getKey(), 'period_year' => 2026, 'last_value' => 125,
        ]);

        $document = app(DocumentNumberIssuer::class)
            ->issue($type, $admin, 'Surat uji', 'Internal');

        $this->assertSame(126, $document->sequence_value);
        $this->assertSame('DOC-0126', $document->number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_sequence.latest_value_updated']);
        CarbonImmutable::setTestNow();
    }

    public function test_latest_sequence_cannot_be_lowered(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 03:00:00 UTC');
        $admin = $this->userWithRole('document-admin');
        $type = DocumentType::query()->create([
            'code' => 'GENERAL', 'name' => 'General', 'number_pattern' => 'DOC-{SEQ:4}',
        ]);
        DocumentSequence::query()->create([
            'document_type_id' => $type->getKey(), 'period_year' => 2026, 'last_value' => 125,
        ]);

        $this->actingAs($admin)->withSession($this->validSsoSession())->put("/document-types/{$type->getKey()}", [
            'code' => 'GENERAL',
            'name' => 'General changed',
            'approval_mode' => 'direct',
            'latest_sequence' => 124,
            'segments' => [['type' => 'sequence', 'width' => 4]],
        ])->assertSessionHasErrors('latest_sequence');

        $this->assertSame(125, DocumentSequence::query()->sole()->last_value);
        $this->assertSame('General', $type->refresh()->name);
        CarbonImmutable::setTestNow();
    }

    public function test_read_and_manage_permissions_are_enforced(): void
    {
        $reader = $this->userWithRole('auditor');
        $basic = $this->userWithRole('office-user');

        $this->actingAs($reader)->withSession($this->validSsoSession())->get('/document-types')->assertOk();
        $this->actingAs($reader)->withSession($this->validSsoSession())->get('/document-types/create')->assertForbidden();
        $this->actingAs($basic)->withSession($this->validSsoSession())->get('/document-types')->assertForbidden();
    }

    public function test_type_can_be_toggled_but_used_type_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('document-admin');
        $type = DocumentType::query()->create([
            'code' => 'GENERAL', 'name' => 'General', 'number_pattern' => 'DOC-{SEQ:4}',
        ]);

        $this->actingAs($admin)->withSession($this->validSsoSession())->patch("/document-types/{$type->getKey()}/toggle")
            ->assertRedirect();
        $this->assertFalse($type->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_type.deactivated']);

        DocumentSequence::query()->create([
            'document_type_id' => $type->getKey(), 'period_year' => 2026, 'last_value' => 0,
        ]);
        $this->actingAs($admin)->withSession($this->validSsoSession())->delete("/document-types/{$type->getKey()}")
            ->assertSessionHasErrors();
        $this->assertDatabaseHas('document_types', ['id' => $type->getKey()]);
    }

    public function test_unused_type_can_be_deleted_with_audit_context(): void
    {
        $admin = $this->userWithRole('document-admin');
        $type = DocumentType::query()->create([
            'code' => 'TEMP', 'name' => 'Temporary', 'number_pattern' => 'TEMP-{SEQ:4}',
        ]);

        $this->actingAs($admin)->withSession($this->validSsoSession())->delete("/document-types/{$type->getKey()}")
            ->assertRedirect('/document-types');

        $this->assertModelMissing($type);
        $audit = AuditLog::query()->where('action', 'document_type.deleted')->sole();
        $this->assertSame($type->getKey(), $audit->context['document_type_id']);
    }

    public function test_pattern_parser_round_trips_a_valid_pattern(): void
    {
        $patterns = app(DocumentNumberPattern::class);
        $pattern = 'QT-JBLU-{YYYY}{MM}{SEQ:4}';

        $this->assertSame($pattern, $patterns->toPattern($patterns->fromPattern($pattern)));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @return array<string, array<string, int|string|null>> */
    private function validSsoSession(): array
    {
        return ['office.sso.tokens' => [
            'access_token' => 'encrypted', 'refresh_token' => null,
            'expires_at' => time() + 3600, 'authenticated_at' => time(),
        ]];
    }
}
