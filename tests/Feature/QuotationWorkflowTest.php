<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class QuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_direct_completion_issues_one_number_and_audits_bypass_atomically(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);

        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('complete', $quotation->status);
        $this->assertNotNull($quotation->document_id);
        $this->assertSame('approval_mode_direct', $quotation->approval_bypass_reason);
        $this->assertTrue($quotation->approvalBypasser->is($maker));
        $this->assertNull($quotation->approved_by);
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.approval_bypassed', 'subject_id' => $quotation->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.completed', 'subject_id' => $quotation->getKey()]);

        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0])
            ->assertRedirect(route('quotations.show', $quotation));
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseCount('document_sequences', 1);
    }

    public function test_maker_checker_submit_and_approval_issue_number_only_on_approval(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $checker = $this->userWithRole('quotation-approver');
        $this->documentType('maker_checker');
        $quotation = $this->createQuotation($maker);

        $this->as($maker)->post(route('quotations.submit', $quotation), ['lock_version' => 0]);
        $quotation->refresh();
        $this->assertSame('pending_approval', $quotation->status);
        $this->assertNull($quotation->document_id);
        $this->assertDatabaseCount('documents', 0);

        $this->as($checker)->post(route('quotations.approve', $quotation), ['lock_version' => 1])
            ->assertRedirect(route('quotations.show', $quotation));
        $quotation->refresh();
        $this->assertSame('complete', $quotation->status);
        $this->assertTrue($quotation->approver->is($checker));
        $this->assertTrue($quotation->completer->is($checker));
        $this->assertNotNull($quotation->document_id);
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.submitted', 'subject_id' => $quotation->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.approved', 'subject_id' => $quotation->getKey()]);

        $this->as($checker)->post(route('quotations.approve', $quotation), ['lock_version' => 1])
            ->assertRedirect(route('quotations.show', $quotation));
        $this->assertDatabaseCount('documents', 1);
        $this->assertSame(1, $quotation->audits()->where('action', 'quotation.approved')->count());
        $this->assertSame(1, $quotation->audits()->where('action', 'quotation.completed')->count());
    }

    public function test_maker_checker_rejects_self_approval_even_for_system_admin(): void
    {
        $admin = $this->userWithRole('system-admin');
        $this->documentType('maker_checker');
        $quotation = $this->createQuotation($admin);
        $this->as($admin)->post(route('quotations.submit', $quotation), ['lock_version' => 0]);

        $this->as($admin)->post(route('quotations.approve', $quotation->refresh()), ['lock_version' => 1])
            ->assertSessionHasErrors('workflow');

        $this->assertSame('pending_approval', $quotation->refresh()->status);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_checker_can_reject_with_reason_without_issuing_number(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $checker = $this->userWithRole('quotation-approver');
        $this->documentType('maker_checker');
        $quotation = $this->createQuotation($maker);
        $this->as($maker)->post(route('quotations.submit', $quotation), ['lock_version' => 0]);

        $this->as($checker)->post(route('quotations.reject', $quotation->refresh()), [
            'lock_version' => 1, 'reason' => 'Tarif perlu dikoreksi kembali.',
        ])->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('rejected', $quotation->status);
        $this->assertSame('Tarif perlu dikoreksi kembali.', $quotation->rejection_reason);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.rejected', 'subject_id' => $quotation->getKey()]);
    }

    public function test_stale_workflow_version_is_rejected_before_number_issuance(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);
        $quotation->update(['lock_version' => 2]);

        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0])
            ->assertSessionHasErrors('workflow');

        $this->assertSame('draft', $quotation->refresh()->status);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_rejected_quotation_can_be_revised_to_draft_with_audit_history(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $checker = $this->userWithRole('quotation-approver');
        $this->documentType('maker_checker');
        $quotation = $this->createQuotation($maker);
        $this->as($maker)->post(route('quotations.submit', $quotation), ['lock_version' => 0]);
        $this->as($checker)->post(route('quotations.reject', $quotation->refresh()), ['lock_version' => 1, 'reason' => 'Tarif perlu dikoreksi kembali.']);
        $quotation->refresh();

        $this->as($maker)->get(route('quotations.edit', $quotation))->assertOk();
        $this->as($maker)->put(route('quotations.update', $quotation), [
            'template_id' => $quotation->template_id, 'quotation_date' => '2026-07-18',
            'subject' => 'Storage quotation revised', 'customer_name' => 'Customer A', 'customer_address' => 'Jakarta',
            'sender_name' => 'Sales JBLU', 'sender_title' => 'Sales Manager', 'currency' => 'IDR',
            'items' => [['values' => ['service' => 'Storage revised', 'price' => '120000']]], 'terms' => [],
            'lock_version' => 2,
        ])->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('draft', $quotation->status);
        $this->assertSame('Tarif perlu dikoreksi kembali.', $quotation->rejection_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.revised', 'subject_id' => $quotation->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.rejected', 'subject_id' => $quotation->getKey()]);
    }

    public function test_void_marks_quotation_and_document_without_reusing_or_deleting_number(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $checker = $this->userWithRole('quotation-approver');
        $type = $this->documentType('direct');
        $quotation = $this->createQuotation($maker);
        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0]);
        $quotation->refresh();
        $number = $quotation->document->number;

        $this->as($checker)->post(route('quotations.void', $quotation), ['lock_version' => 1, 'reason' => 'Quotation dibatalkan oleh pelanggan.'])
            ->assertRedirect(route('quotations.show', $quotation));
        $quotation->refresh();
        $this->assertSame('void', $quotation->status);
        $this->assertSame($number, $quotation->document->number);
        $this->assertSame('Quotation dibatalkan oleh pelanggan.', $quotation->document->void_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.voided', 'subject_id' => $quotation->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.voided', 'subject_id' => $quotation->document_id]);

        $this->as($checker)->post(route('quotations.void', $quotation), ['lock_version' => 1, 'reason' => 'Retry tidak mengubah alasan.']);
        $this->assertSame(1, $quotation->audits()->where('action', 'quotation.voided')->count());
        $this->assertSame(1, $quotation->document->audits()->where('action', 'document.voided')->count());
        $this->assertSame(1, $type->sequences()->sole()->last_value);
    }

    public function test_complete_quotation_rejects_content_mutation_and_deletion_at_model_boundary(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);
        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0]);
        $quotation->refresh();

        $this->as($maker)->get(route('quotations.edit', $quotation))->assertForbidden();

        try {
            $quotation->update(['subject' => 'Forbidden mutation']);
            $this->fail('Complete quotation content mutation should fail.');
        } catch (LogicException $exception) {
            $this->assertSame('Quotation complete atau void bersifat immutable.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $quotation->delete();
    }

    public function test_detail_displays_workflow_and_void_audit_trail(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $checker = $this->userWithRole('quotation-approver');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);
        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0]);
        $this->as($checker)->post(route('quotations.void', $quotation->refresh()), ['lock_version' => 1, 'reason' => 'Quotation dibatalkan oleh pelanggan.']);

        $this->as($checker)->get(route('quotations.show', $quotation->refresh()))
            ->assertOk()->assertSee('Audit trail quotation')->assertSee('quotation.completed')
            ->assertSee('quotation.voided')->assertSee('Quotation dibatalkan oleh pelanggan.');
    }

    public function test_invalid_number_pattern_rolls_back_completion_sequence_and_audits(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $type = $this->documentType('direct');
        $type->update(['number_pattern' => 'QT-{UNKNOWN}-{SEQ:4}']);
        $quotation = $this->createQuotation($maker);

        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0])
            ->assertSessionHasErrors();

        $this->assertSame('draft', $quotation->refresh()->status);
        $this->assertNull($quotation->document_id);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_sequences', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'quotation.completed']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'quotation.approval_bypassed']);
    }

    public function test_role_permission_matrix_is_enforced_for_every_quotation_action(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $otherMaker = $this->userWithRole('quotation-maker');
        $approver = $this->userWithRole('quotation-approver');
        $auditor = $this->userWithRole('auditor');
        $admin = $this->userWithRole('system-admin');
        $documentAdmin = $this->userWithRole('document-admin');
        $basic = $this->userWithRole('office-user');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);

        $this->assertTrue($maker->can('view', $quotation));
        $this->assertTrue($maker->can('create', Quotation::class));
        $this->assertTrue($maker->can('update', $quotation));
        $this->assertTrue($maker->can('completeDirect', $quotation));
        $this->assertTrue($maker->can('submit', $quotation));
        $this->assertTrue($maker->can('preview', $quotation));
        $this->assertFalse($maker->can('approve', $quotation));
        $this->assertFalse($maker->can('reject', $quotation));
        $this->assertFalse($maker->can('void', $quotation));
        $this->assertFalse($otherMaker->can('update', $quotation));
        $this->assertFalse($otherMaker->can('completeDirect', $quotation));
        $this->assertFalse($otherMaker->can('submit', $quotation));

        $this->assertTrue($approver->can('view', $quotation));
        $this->assertTrue($approver->can('preview', $quotation));
        $this->assertTrue($approver->can('approve', $quotation));
        $this->assertTrue($approver->can('reject', $quotation));
        $this->assertTrue($approver->can('void', $quotation));
        $this->assertFalse($approver->can('create', Quotation::class));
        $this->assertFalse($approver->can('update', $quotation));
        $this->assertFalse($approver->can('completeDirect', $quotation));
        $this->assertFalse($approver->can('submit', $quotation));

        $this->assertTrue($auditor->can('view', $quotation));
        $this->assertTrue($auditor->can('preview', $quotation));
        foreach (['update', 'completeDirect', 'submit', 'approve', 'reject', 'void'] as $ability) {
            $this->assertFalse($auditor->can($ability, $quotation));
        }
        $this->assertFalse($auditor->can('create', Quotation::class));

        foreach (['view', 'update', 'completeDirect', 'submit', 'approve', 'reject', 'void', 'preview'] as $ability) {
            $this->assertTrue($admin->can($ability, $quotation));
        }
        $this->assertTrue($admin->can('create', Quotation::class));

        foreach ([$documentAdmin, $basic] as $unauthorized) {
            foreach (['view', 'update', 'completeDirect', 'submit', 'approve', 'reject', 'void', 'preview'] as $ability) {
                $this->assertFalse($unauthorized->can($ability, $quotation));
            }
            $this->assertFalse($unauthorized->can('create', Quotation::class));
        }
    }

    private function createQuotation(User $user): Quotation
    {
        $template = $this->template();
        $response = $this->as($user)->post(route('quotations.store'), [
            'template_id' => $template->getKey(), 'quotation_date' => '2026-07-18',
            'subject' => 'Storage quotation', 'customer_name' => 'Customer A', 'customer_address' => 'Jakarta',
            'sender_name' => 'Sales JBLU', 'sender_title' => 'Sales Manager', 'currency' => 'IDR',
            'items' => [['values' => ['service' => 'Storage', 'price' => '125000']]], 'terms' => [],
        ]);
        $response->assertSessionHasNoErrors();

        return Quotation::query()->latest()->firstOrFail();
    }

    private function documentType(string $mode): DocumentType
    {
        return DocumentType::query()->create([
            'code' => 'QUOTATION', 'name' => 'Quotation',
            'number_pattern' => 'QT-JBLU-{YYYY}{MM}{SEQ:4}', 'approval_mode' => $mode,
        ]);
    }

    private function template(): DocumentTemplate
    {
        $profile = CompanyProfile::query()->firstOrCreate(['company_code' => 'JBLU'], [
            'legal_name' => 'PT JBLU', 'display_name' => 'JBLU', 'address_lines' => ['Jakarta'],
            'city' => 'Jakarta', 'postal_code' => '10110', 'country' => 'ID',
        ]);

        return DocumentTemplate::query()->create([
            'company_profile_id' => $profile->getKey(), 'type' => 'quotation', 'version' => 1,
            'name' => 'Quotation', 'settings' => ['columns' => [
                ['key' => 'service', 'label' => 'Service', 'value_type' => 'text', 'required' => true],
                ['key' => 'price', 'label' => 'Price', 'value_type' => 'currency', 'required' => true],
            ]],
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession(['office.sso.tokens' => [
            'access_token' => 'encrypted', 'refresh_token' => null,
            'expires_at' => time() + 3600, 'authenticated_at' => time(),
        ]]);
    }
}
