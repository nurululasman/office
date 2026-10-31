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
