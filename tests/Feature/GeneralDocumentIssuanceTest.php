<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Documents\DocumentNumberIssuer;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralDocumentIssuanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_issue_form_only_lists_active_document_types(): void
    {
        $officer = $this->userWithRole('document-officer');
        $active = $this->type('ACTIVE', true);
        $inactive = $this->type('INACTIVE', false);

        $this->actingAs($officer)->withSession($this->validSsoSession())->get('/documents/create')
            ->assertOk()
            ->assertSee($active->name)
            ->assertSee($active->number_pattern)
            ->assertDontSee($inactive->name)
            ->assertSee('Terbitkan nomor surat umum');
    }

    public function test_document_officer_can_issue_and_view_a_copyable_number(): void
    {
        CarbonImmutable::setTestNow('2026-07-18 03:15:00 UTC');
        $officer = $this->userWithRole('document-officer');
        $type = $this->type('GENERAL', true, 'GEN-{YYYY}{MM}-{SEQ:4}');

        $response = $this->actingAs($officer)->withSession($this->validSsoSession())->post('/documents', [
            'document_type_id' => $type->getKey(),
            'title' => '  Surat Keterangan Operasional  ',
            'purpose' => '  Untuk proses operasional pelanggan  ',
        ]);

        $document = Document::query()->sole();
        $response->assertRedirect(route('documents.issued', $document));
        $this->assertSame('GEN-202607-0001', $document->number);
        $this->assertSame('Surat Keterangan Operasional', $document->title);
        $this->assertSame('Untuk proses operasional pelanggan', $document->purpose);
        $this->assertTrue($document->issuer->is($officer));
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.issued', 'subject_id' => $document->getKey()]);

        $this->actingAs($officer)->withSession($this->validSsoSession())->get(route('documents.issued', $document))
            ->assertOk()
            ->assertSee('GEN-202607-0001')
            ->assertSee('Salin nomor')
            ->assertSee('data-testid="issued-number"', false)
            ->assertSee('Surat Keterangan Operasional')
            ->assertSee('Untuk proses operasional pelanggan');

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_inactive_type_and_invalid_input_are_rejected_without_consuming_sequence(): void
    {
        $officer = $this->userWithRole('document-officer');
        $inactive = $this->type('INACTIVE', false);

        $this->actingAs($officer)->withSession($this->validSsoSession())->post('/documents', [
            'document_type_id' => $inactive->getKey(),
            'title' => ' ',
            'purpose' => ' ',
        ])->assertSessionHasErrors(['document_type_id', 'title', 'purpose']);

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_sequences', 0);
    }

    public function test_documents_issue_permission_is_required(): void
    {
        $documentAdmin = $this->userWithRole('document-admin');
        $type = $this->type('GENERAL', true);

        $this->actingAs($documentAdmin)->withSession($this->validSsoSession())
            ->get('/documents/create')->assertForbidden();
        $this->actingAs($documentAdmin)->withSession($this->validSsoSession())
            ->post('/documents', [
                'document_type_id' => $type->getKey(), 'title' => 'Blocked', 'purpose' => 'Blocked',
            ])->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_result_is_available_to_register_reader_but_not_basic_user(): void
    {
        $officer = $this->userWithRole('document-officer');
        $auditor = $this->userWithRole('auditor');
        $basic = $this->userWithRole('office-user');
        $type = $this->type('GENERAL', true);
        $document = app(DocumentNumberIssuer::class)
            ->issue($type, $officer, 'Readable document', 'Audit review');

        $this->actingAs($auditor)->withSession($this->validSsoSession())
            ->get(route('documents.issued', $document))->assertOk();
        $this->actingAs($basic)->withSession($this->validSsoSession())
            ->get(route('documents.issued', $document))->assertForbidden();
    }

    public function test_empty_active_type_state_disables_issuance(): void
    {
        $officer = $this->userWithRole('document-officer');

        $this->actingAs($officer)->withSession($this->validSsoSession())->get('/documents/create')
            ->assertOk()
            ->assertSee('Belum ada tipe dokumen aktif')
            ->assertSee('disabled', false);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function type(string $code, bool $active, string $pattern = 'DOC-{SEQ:4}'): DocumentType
    {
        return DocumentType::query()->create([
            'code' => $code,
            'name' => $code.' Document Type',
            'number_pattern' => $pattern,
            'is_active' => $active,
        ]);
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
