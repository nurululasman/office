<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Documents\DocumentNumberIssuer;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRegisterAndVoidTest extends TestCase
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

    public function test_register_supports_search_and_type_year_status_filters(): void
    {
        $officer = $this->userWithRole('document-officer');
        $admin = $this->userWithRole('system-admin');
        $typeA = $this->type('TYPE_A');
        $typeB = $this->type('TYPE_B');

        CarbonImmutable::setTestNow('2025-07-01 00:00:00 UTC');
        $old = $this->issue($typeA, $officer, 'Arsip lama', 'Alpha customer');
        CarbonImmutable::setTestNow('2026-07-01 00:00:00 UTC');
        $current = $this->issue($typeA, $officer, 'Surat operasional', 'Beta customer');
        $other = $this->issue($typeB, $officer, 'Dokumen kontraktor', 'Gamma customer');
        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->post(route('documents.void', $other), ['reason' => 'Diterbitkan dengan tipe yang keliru'])
            ->assertRedirect(route('documents.show', $other));

        $this->actingAs($officer)->withSession($this->validSsoSession())
            ->get(route('documents.index', ['q' => 'ALPHA']))
            ->assertOk()->assertSee($old->number)->assertDontSee($current->number)->assertDontSee($other->number);

        $this->actingAs($officer)->withSession($this->validSsoSession())
            ->get(route('documents.index', ['document_type_id' => $typeA->getKey(), 'period_year' => 2026, 'status' => 'issued']))
            ->assertOk()->assertSee($current->number)->assertDontSee($old->number)->assertDontSee($other->number);

        $this->actingAs($officer)->withSession($this->validSsoSession())
            ->get(route('documents.index', ['status' => 'void']))
            ->assertOk()->assertSee($other->number)->assertDontSee($current->number);
    }

    public function test_detail_displays_issuance_and_void_audit_snapshots(): void
    {
        $officer = $this->userWithRole('document-officer');
        $admin = $this->userWithRole('system-admin');
        $document = $this->issue($this->type('DETAIL'), $officer, 'Detail audit', 'Audit customer');
        $reason = 'Nomor diterbitkan untuk dokumen yang salah';

        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->post(route('documents.void', $document), ['reason' => $reason])->assertRedirect();

        $this->actingAs($officer)->withSession($this->validSsoSession())
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee($document->number)
            ->assertSee('document.issued')
            ->assertSee('document.voided')
            ->assertSee($reason)
            ->assertSee($admin->name)
            ->assertSee('Audit trail');

        $this->assertSame(2, $document->audits()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.voided', 'subject_id' => $document->getKey(), 'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_void_requires_permission_and_a_meaningful_reason(): void
    {
        $officer = $this->userWithRole('document-officer');
        $admin = $this->userWithRole('system-admin');
        $document = $this->issue($this->type('VOID_GUARD'), $officer, 'Guard', 'Void guard');

        $this->actingAs($officer)->withSession($this->validSsoSession())
            ->post(route('documents.void', $document), ['reason' => 'Valid but unauthorized'])
            ->assertForbidden();
        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->post(route('documents.void', $document), ['reason' => 'bad'])
            ->assertSessionHasErrors('reason');

        $this->assertNull($document->fresh()->voided_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document.voided']);
    }

    public function test_void_is_idempotent_and_number_is_never_reused(): void
    {
        $officer = $this->userWithRole('document-officer');
        $admin = $this->userWithRole('system-admin');
        $type = $this->type('VOID_RETRY');
        $first = $this->issue($type, $officer, 'First', 'Customer');
        $reason = 'Dokumen dibatalkan oleh pemilik proses';

        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->post(route('documents.void', $first), ['reason' => $reason])->assertRedirect();
        $voidedAt = $first->fresh()->voided_at;
        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->post(route('documents.void', $first), ['reason' => 'Retry dengan alasan berbeda'])->assertRedirect();

        $first->refresh();
        $second = $this->issue($type, $officer, 'Second', 'Customer');
        $this->assertSame($voidedAt->getTimestamp(), $first->voided_at->getTimestamp());
        $this->assertSame($reason, $first->void_reason);
        $this->assertSame(1, AuditLog::query()->where('action', 'document.voided')->count());
        $this->assertSame(2, $second->sequence_value);
        $this->assertNotSame($first->number, $second->number);
        $this->assertDatabaseCount('documents', 2);
    }

    public function test_register_and_detail_require_read_permission(): void
    {
        $officer = $this->userWithRole('document-officer');
        $basic = $this->userWithRole('office-user');
        $document = $this->issue($this->type('READ_GUARD'), $officer, 'Read guard', 'Customer');

        $this->actingAs($basic)->withSession($this->validSsoSession())
            ->get(route('documents.index'))->assertForbidden();
        $this->actingAs($basic)->withSession($this->validSsoSession())
            ->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($officer)->withSession($this->validSsoSession())
            ->get(route('documents.index'))->assertOk()->assertSee($document->number);
    }

    private function issue(DocumentType $type, User $issuer, string $title, string $purpose): Document
    {
        return app(DocumentNumberIssuer::class)->issue($type, $issuer, $title, $purpose);
    }

    private function type(string $code): DocumentType
    {
        return DocumentType::query()->create([
            'code' => $code, 'name' => $code.' Type', 'number_pattern' => $code.'-{YYYY}-{SEQ:4}',
        ]);
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
