<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotations\QuotationDocumentRenderer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuotationSenderApprovalWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'sqlite' && ! Schema::hasTable('users')) {
            $this->artisan('migrate');
        }
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_self_sender_quotation_sets_direct_mode_and_includes_stamp_and_signature_automatically(): void
    {
        Storage::fake('public');
        $this->documentType('direct');

        $stampBytes = UploadedFile::fake()->image('company_stamp.png', 100, 100)->getContent();
        $stampHash = hash('sha256', $stampBytes);
        Storage::disk('public')->put("company-stamps/{$stampHash}.png", $stampBytes);

        $template = $this->template([
            'stamp_path' => "/storage/company-stamps/{$stampHash}.png",
            'stamp_sha256' => $stampHash,
        ]);

        $signatureBytes = UploadedFile::fake()->image('my_signature.png', 80, 40)->getContent();
        $signatureHash = hash('sha256', $signatureBytes);
        Storage::disk('public')->put("user-signatures/{$signatureHash}.png", $signatureBytes);

        $maker = $this->userWithRole('quotation-maker', [
            'signature_path' => "/storage/user-signatures/{$signatureHash}.png",
            'signature_sha256' => $signatureHash,
        ]);

        // Create quotation where sender is oneself
        $response = $this->as($maker)->post(route('quotations.store'), [
            'template_id' => $template->getKey(),
            'quotation_date' => '2026-09-12',
            'subject' => 'Self Sender Quotation',
            'customer_name' => 'PT Pelanggan Sejahtera',
            'customer_address' => 'Jl. Sudirman No. 10, Jakarta',
            'sender_id' => $maker->getKey(),
            'sender_name' => $maker->name,
            'sender_title' => 'Sales Specialist',
            'currency' => 'IDR',
            'content_html' => '<p>Item List</p>',
            'terms_html' => '<p>Terms List</p>',
        ]);

        $response->assertSessionHasNoErrors();
        $quotation = Quotation::query()->latest()->firstOrFail();

        $this->assertSame($maker->getKey(), $quotation->sender_id);
        $this->assertTrue($quotation->isSelfSender());
        $this->assertSame('direct', $quotation->approval_mode);
        $this->assertSame('draft', $quotation->status);

        // Preview should already display company stamp and user signature
        $renderer = app(QuotationDocumentRenderer::class);
        $draftHtml = $renderer->content($quotation, true);
        $this->assertStringContainsString('class="company-stamp-img"', $draftHtml);
        $this->assertStringContainsString('class="user-signature-img"', $draftHtml);
        $this->assertStringContainsString(base64_encode($stampBytes), $draftHtml);
        $this->assertStringContainsString(base64_encode($signatureBytes), $draftHtml);

        // Direct completion is allowed
        $this->assertTrue($maker->can('completeDirect', $quotation));
        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('complete', $quotation->status);
        $this->assertNotNull($quotation->document_id);

        $completedHtml = $renderer->content($quotation, false);
        $this->assertStringContainsString('class="company-stamp-img"', $completedHtml);
        $this->assertStringContainsString('class="user-signature-img"', $completedHtml);
    }

    public function test_different_sender_forces_maker_checker_and_requires_sender_approval(): void
    {
        Storage::fake('public');
        $this->documentType('direct'); // Document type is configured as direct, but different sender must force maker_checker

        $stampBytes = UploadedFile::fake()->image('company_stamp.png', 100, 100)->getContent();
        $stampHash = hash('sha256', $stampBytes);
        Storage::disk('public')->put("company-stamps/{$stampHash}.png", $stampBytes);

        $template = $this->template([
            'stamp_path' => "/storage/company-stamps/{$stampHash}.png",
            'stamp_sha256' => $stampHash,
        ]);

        $signerSignatureBytes = UploadedFile::fake()->image('director_signature.png', 90, 45)->getContent();
        $signerSignatureHash = hash('sha256', $signerSignatureBytes);
        Storage::disk('public')->put("user-signatures/{$signerSignatureHash}.png", $signerSignatureBytes);

        $maker = $this->userWithRole('quotation-maker');
        $signer = $this->userWithRole('office-user', [ // regular user without quotation-approver role
            'name' => 'Budi Director',
            'signature_path' => "/storage/user-signatures/{$signerSignatureHash}.png",
            'signature_sha256' => $signerSignatureHash,
        ]);

        // Create quotation where sender is someone else
        $response = $this->as($maker)->post(route('quotations.store'), [
            'template_id' => $template->getKey(),
            'quotation_date' => '2026-09-12',
            'subject' => 'Different Sender Quotation',
            'customer_name' => 'PT Mitra Sejati',
            'customer_address' => 'Jl. Thamrin No. 5, Jakarta',
            'sender_id' => $signer->getKey(),
            'sender_name' => $signer->name,
            'sender_title' => 'Managing Director',
            'currency' => 'IDR',
            'content_html' => '<p>Consulting Service</p>',
            'terms_html' => '<p>Payment in 30 days</p>',
        ]);

        $response->assertSessionHasNoErrors();
        $quotation = Quotation::query()->latest()->firstOrFail();

        $this->assertSame($signer->getKey(), $quotation->sender_id);
        $this->assertFalse($quotation->isSelfSender());
        $this->assertSame('maker_checker', $quotation->approval_mode);
        $this->assertSame('draft', $quotation->status);

        // Creator cannot complete direct
        $this->assertFalse($maker->can('completeDirect', $quotation));

        // In draft preview, stamp and signature are NOT shown yet because it has not been approved
        $renderer = app(QuotationDocumentRenderer::class);
        $draftHtml = $renderer->content($quotation, true);
        $this->assertStringNotContainsString('class="company-stamp-img"', $draftHtml);
        $this->assertStringNotContainsString('class="user-signature-img"', $draftHtml);

        // Submit for approval
        $this->as($maker)->post(route('quotations.submit', $quotation), ['lock_version' => 0])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('pending_approval', $quotation->status);

        // Creator cannot approve
        $this->assertFalse($maker->can('approve', $quotation));

        // Signer CAN approve even though signer only has basic role (because signer is designated sender)
        $this->assertTrue($signer->can('approve', $quotation));

        // Signer approves
        $this->as($signer)->post(route('quotations.approve', $quotation), ['lock_version' => 1])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('complete', $quotation->status);
        $this->assertSame($signer->getKey(), $quotation->approved_by);
        $this->assertNotNull($quotation->document_id);

        // Now that it's approved and complete, stamp and signer's signature ARE shown!
        $completeHtml = $renderer->content($quotation, false);
        $this->assertStringContainsString('class="company-stamp-img"', $completeHtml);
        $this->assertStringContainsString('class="user-signature-img"', $completeHtml);
        $this->assertStringContainsString(base64_encode($stampBytes), $completeHtml);
        $this->assertStringContainsString(base64_encode($signerSignatureBytes), $completeHtml);
    }

    public function test_designated_sender_can_reject_quotation(): void
    {
        $this->documentType('direct');
        $template = $this->template();

        $maker = $this->userWithRole('quotation-maker');
        $signer = $this->userWithRole('office-user', ['name' => 'Pak Bos']);

        $this->as($maker)->post(route('quotations.store'), [
            'template_id' => $template->getKey(),
            'quotation_date' => '2026-09-12',
            'subject' => 'Quotation To Reject',
            'customer_name' => 'PT Reject Test',
            'customer_address' => 'Jakarta',
            'sender_id' => $signer->getKey(),
            'sender_name' => $signer->name,
            'sender_title' => 'Chief Executive',
            'currency' => 'IDR',
            'content_html' => '<p>Items</p>',
            'terms_html' => '<p>Terms</p>',
        ]);

        $quotation = Quotation::query()->latest()->firstOrFail();
        $this->as($maker)->post(route('quotations.submit', $quotation), ['lock_version' => 0]);
        $quotation->refresh();

        $this->assertTrue($signer->can('reject', $quotation));
        $this->as($signer)->post(route('quotations.reject', $quotation), [
            'lock_version' => 1,
            'reason' => 'Harga tidak disetujui, tolong revisi kembali.',
        ])->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('rejected', $quotation->status);
        $this->assertSame('Harga tidak disetujui, tolong revisi kembali.', $quotation->rejection_reason);
    }

    private function documentType(string $mode): DocumentType
    {
        return DocumentType::query()->updateOrCreate(['code' => 'QUOTATION'], [
            'name' => 'Quotation',
            'number_pattern' => 'QT-JBLU-{YYYY}{MM}{SEQ:4}',
            'approval_mode' => $mode,
            'is_active' => true,
        ]);
    }

    private function template(array $profileOverrides = []): DocumentTemplate
    {
        $profile = CompanyProfile::query()->firstOrCreate(['company_code' => 'JBLU'], [
            'legal_name' => 'PT JBLU',
            'display_name' => 'JBLU',
            'address_lines' => ['Jakarta'],
            'city' => 'Jakarta',
            'postal_code' => '10110',
            'country' => 'ID',
        ]);

        if ($profileOverrides !== []) {
            $profile->update($profileOverrides);
        }

        $templateContent = app(\App\Services\DocumentTemplates\DocumentTemplateHtmlSanitizer::class)->sanitize(<<<'HTML'
<table style="width: 100%;">
<tbody><tr><td><h2>{{ company_legal_name }}</h2></td><td><div>{{ company_logo }}</div></td></tr></tbody>
</table>
<p>No: {{ quotation_number }}</p>
<p>Date: {{ quotation_date }}</p>
<p>To: {{ customer_name }}</p>
<p>Subject: {{ subject }}</p>
<p>From: {{ sender_name }}</p>
<div>{{ quotation_items }}</div>
<div>{{ quotation_terms }}</div>
<div>{{ signature_block }}</div>
<div>{{ draft_watermark }}</div>
HTML);

        return DocumentTemplate::query()->create([
            'company_profile_id' => $profile->getKey(),
            'document_type_id' => DocumentType::query()->where('code', 'QUOTATION')->firstOrFail()->getKey(),
            'type' => 'quotation',
            'version' => 1,
            'name' => 'Template Test',
            'status' => 'active',
            'is_active' => true,
            'content_html' => $templateContent,
            'content_sha256' => hash('sha256', $templateContent),
        ]);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession(['office.sso.tokens' => [
            'access_token' => 'encrypted',
            'refresh_token' => null,
            'expires_at' => time() + 3600,
            'authenticated_at' => time(),
        ]]);
    }
}
