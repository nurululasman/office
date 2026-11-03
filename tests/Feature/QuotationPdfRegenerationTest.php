<?php

namespace Tests\Feature;

use App\Jobs\GenerateQuotationPdf;
use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\GeneratedFile;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuotationPdfRegenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorized_user_can_regenerate_pdf_and_job_is_queued(): void
    {
        Queue::fake();

        $maker = $this->userWithRole('quotation-maker');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);

        // Complete the quotation
        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0]);
        $quotation->refresh();
        $this->assertSame('complete', $quotation->status);

        $file = GeneratedFile::query()->where('kind', 'quotation_pdf')->firstOrFail();
        $file->update(['status' => 'ready', 'size' => 1024, 'sha256' => hash('sha256', 'dummy')]);

        // Post regenerate
        $response = $this->as($maker)->post(route('quotations.pdf.regenerate', $quotation));
        $response->assertRedirect(route('quotations.show', $quotation));
        $response->assertSessionHas('status', 'Permintaan regenerasi PDF quotation berhasil diproses.');

        $file->refresh();
        $this->assertSame('queued', $file->status);
        $this->assertNull($file->last_error);
        Queue::assertPushedOn('pdf', GenerateQuotationPdf::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'quotation.pdf_regenerated',
            'subject_id' => $quotation->getKey(),
            'actor_id' => $maker->getKey(),
        ]);
    }

    public function test_unauthorized_user_cannot_regenerate_pdf(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $unauthorized = $this->userWithRole('office-user');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);

        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0]);

        $this->as($unauthorized)
            ->post(route('quotations.pdf.regenerate', $quotation))
            ->assertForbidden();
    }

    public function test_cannot_regenerate_pdf_for_draft_quotation(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);

        $this->assertSame('draft', $quotation->status);

        $this->as($maker)
            ->post(route('quotations.pdf.regenerate', $quotation))
            ->assertForbidden();
    }

    public function test_show_page_displays_regenerate_button_for_complete_quotation(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $this->documentType('direct');
        $quotation = $this->createQuotation($maker);

        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0]);

        $response = $this->as($maker)->get(route('quotations.show', $quotation));
        $response->assertOk();
        $response->assertSee('Regenerate PDF');
        $response->assertSee(route('quotations.pdf.regenerate', $quotation));
    }

    public function test_regenerate_pdf_updates_header_company_profile_data(): void
    {
        Queue::fake();

        $maker = $this->userWithRole('quotation-maker');
        $this->documentType('direct');

        $profile = CompanyProfile::query()->firstOrCreate(['company_code' => 'JBLU'], [
            'legal_name' => 'PT JBLU Lama', 'display_name' => 'JBLU', 'address_lines' => ['Jl. Sudirman Lama No. 1', 'Jakarta Selatan'],
            'city' => 'Jakarta Selatan', 'postal_code' => '12190', 'country' => 'ID',
            'phone' => '+62 21 111-0000', 'email' => 'old@jblu.co.id',
        ]);

        $customTemplateHtml = <<<'HTML'
<p>{{ company_display_name }}</p>
<p>{{ company_address }}</p>
<p>{{ quotation_number }}</p>
<p>{{ quotation_date }}</p>
<p>{{ customer_name }}</p>
<p>{{ customer_address }}</p>
<p>{{ subject }}</p>
<p>{{ intro_text }}</p>
<div>{{ quotation_items }}</div>
<div>{{ quotation_terms }}</div>
<p>{{ closing_text }}</p>
<p>{{ sender_name }}</p>
<p>{{ sender_title }}</p>
HTML;

        $template = DocumentTemplate::query()->create([
            'company_profile_id' => $profile->getKey(),
            'document_type_id' => DocumentType::query()->sole()->getKey(),
            'type' => 'quotation', 'version' => 1,
            'name' => 'Quotation with Address',
            'content_html' => $customTemplateHtml,
            'settings' => ['columns' => [
                ['key' => 'service', 'label' => 'Service', 'value_type' => 'text', 'required' => true],
                ['key' => 'price', 'label' => 'Price', 'value_type' => 'currency', 'required' => true],
            ]],
        ]);

        $response = $this->as($maker)->post(route('quotations.store'), [
            'template_id' => $template->getKey(), 'quotation_date' => '2026-07-18',
            'subject' => 'Storage quotation', 'customer_name' => 'Customer A', 'customer_address' => 'Jakarta',
            'sender_name' => 'Sales JBLU', 'sender_title' => 'Sales Manager', 'currency' => 'IDR',
            'content_html' => '<p>Storage 125000</p>', 'terms_html' => '<p>Terms</p>',
            'items' => [['values' => ['service' => 'Storage', 'price' => '125000']]], 'terms' => [],
        ]);
        $response->assertSessionHasNoErrors();
        $quotation = Quotation::query()->latest()->firstOrFail();

        $this->as($maker)->post(route('quotations.complete', $quotation), ['lock_version' => 0]);
        $quotation->refresh();
        $this->assertSame('complete', $quotation->status);
        $this->assertSame(['Jl. Sudirman Lama No. 1', 'Jakarta Selatan'], $quotation->template_snapshot['company_profile']['address_lines']);

        // Update company profile data (address, phone, email, legal name)
        $profile->update([
            'legal_name' => 'PT JBLU Baru',
            'address_lines' => ['Jl. MH Thamrin Baru No. 99', 'Jakarta Pusat'],
            'city' => 'Jakarta Pusat',
            'postal_code' => '10350',
            'phone' => '+62 21 555-0199',
            'email' => 'contact@jblu.co.id',
        ]);

        // Regenerate PDF
        $response = $this->as($maker)->post(route('quotations.pdf.regenerate', $quotation));
        $response->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('PT JBLU Baru', $quotation->template_snapshot['company_profile']['legal_name']);
        $this->assertSame(['Jl. MH Thamrin Baru No. 99', 'Jakarta Pusat'], $quotation->template_snapshot['company_profile']['address_lines']);
        $this->assertSame('+62 21 555-0199', $quotation->template_snapshot['company_profile']['phone']);
        $this->assertSame('contact@jblu.co.id', $quotation->template_snapshot['company_profile']['email']);

        // Check rendered HTML
        $renderedHtml = app(\App\Services\Quotations\QuotationDocumentRenderer::class)->content($quotation, false);
        $this->assertStringContainsString("Jl. MH Thamrin Baru No. 99<br>\nJakarta Pusat", $renderedHtml);
        $this->assertStringNotContainsString('Jl. Sudirman Lama No. 1', $renderedHtml);
    }

    private function createQuotation(User $user): Quotation
    {
        $template = $this->template();
        $response = $this->as($user)->post(route('quotations.store'), [
            'template_id' => $template->getKey(), 'quotation_date' => '2026-07-18',
            'subject' => 'Storage quotation', 'customer_name' => 'Customer A', 'customer_address' => 'Jakarta',
            'sender_name' => 'Sales JBLU', 'sender_title' => 'Sales Manager', 'currency' => 'IDR',
            'content_html' => '<p>Storage 125000</p>', 'terms_html' => '<p>Terms</p>',
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
            'company_profile_id' => $profile->getKey(),
            'document_type_id' => DocumentType::query()->sole()->getKey(),
            'type' => 'quotation', 'version' => 1,
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
