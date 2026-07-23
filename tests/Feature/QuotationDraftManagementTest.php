<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_maker_can_create_and_view_draft_with_dynamic_columns(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();

        $response = $this->as($maker)->post(route('quotations.store'), $this->payload($template));

        $quotation = Quotation::query()->sole();
        $response->assertRedirect(route('quotations.show', $quotation));
        $this->assertSame('draft', $quotation->status);
        $this->assertNull($quotation->document_id);
        $this->assertTrue($quotation->creator->is($maker));
        $this->assertSame(['description', 'unit_price', 'available'], $quotation->items->first()->values->pluck('key')->all());
        $this->assertSame('125000.50', $quotation->items->first()->values[1]->value);
        $this->assertSame('currency', $quotation->items->first()->values[1]->value_type);
        $this->assertSame(['TOP 30 days', 'VAT excluded'], $quotation->terms->pluck('content')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.created', 'subject_id' => $quotation->getKey()]);

        $this->as($maker)->get(route('quotations.show', $quotation))->assertOk()
            ->assertSee('Storage service')->assertSee('125000.50')->assertSee('Belum diterbitkan');
    }

    public function test_sender_title_and_customer_address_are_optional(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();
        $payload = $this->payload($template);
        $payload['customer_address'] = '';
        $payload['sender_title'] = '';

        $this->as($maker)->post(route('quotations.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quotation = Quotation::query()->sole();
        $this->assertNull($quotation->customer_address);
        $this->assertNull($quotation->sender_title);
    }

    public function test_validation_follows_template_value_types_and_rejects_unknown_keys(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();
        $payload = $this->payload($template);
        $payload['items'][0]['values']['description'] = '';
        $payload['items'][0]['values']['unit_price'] = '12,500';
        $payload['items'][0]['values']['available'] = 'yes';
        $payload['items'][0]['values']['rate_40'] = '999';

        $this->as($maker)->post(route('quotations.store'), $payload)
            ->assertSessionHasErrors([
                'items.0.values.description', 'items.0.values.unit_price',
                'items.0.values.available', 'items.0.values.rate_40',
            ]);

        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_only_owner_or_update_any_permission_can_edit_a_draft(): void
    {
        $owner = $this->userWithRole('quotation-maker');
        $otherMaker = $this->userWithRole('quotation-maker');
        $admin = $this->userWithRole('system-admin');
        $template = $this->template();
        $this->as($owner)->post(route('quotations.store'), $this->payload($template));
        $quotation = Quotation::query()->sole();

        $this->as($otherMaker)->get(route('quotations.edit', $quotation))->assertForbidden();
        $this->as($admin)->get(route('quotations.edit', $quotation))->assertOk();

        $payload = $this->payload($template);
        $payload['subject'] = 'Updated by owner';
        $payload['lock_version'] = $quotation->lock_version;
        $this->as($owner)->put(route('quotations.update', $quotation), $payload)
            ->assertRedirect(route('quotations.show', $quotation));
        $this->assertSame('Updated by owner', $quotation->refresh()->subject);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.updated', 'subject_id' => $quotation->getKey()]);
    }

    public function test_non_draft_is_immutable_and_reader_cannot_create(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $auditor = $this->userWithRole('auditor');
        $template = $this->template();
        $this->as($maker)->post(route('quotations.store'), $this->payload($template));
        $quotation = Quotation::query()->sole();
        $quotation->update(['status' => 'pending_approval']);

        $this->as($maker)->get(route('quotations.edit', $quotation))->assertForbidden();
        $this->as($auditor)->get(route('quotations.index'))->assertOk();
        $this->as($auditor)->get(route('quotations.create'))->assertForbidden();
    }

    public function test_existing_draft_keeps_its_schema_snapshot_when_template_changes(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();
        $this->as($maker)->post(route('quotations.store'), $this->payload($template));
        $quotation = Quotation::query()->sole();
        $template->update([
            'is_active' => false,
            'settings' => ['columns' => [['key' => 'replacement', 'label' => 'Replacement', 'value_type' => 'text', 'required' => true]]],
        ]);

        $payload = $this->payload($template);
        $payload['subject'] = 'Snapshot retained';
        $payload['lock_version'] = $quotation->lock_version;
        $this->as($maker)->put(route('quotations.update', $quotation), $payload)->assertSessionHasNoErrors();

        $quotation->refresh();
        $this->assertSame('description', $quotation->item_schema['columns'][0]['key']);
        $this->assertSame(['description', 'unit_price', 'available'], $quotation->items->first()->values->pluck('key')->all());
    }

    public function test_create_form_requires_active_quotation_template(): void
    {
        $maker = $this->userWithRole('quotation-maker');

        $this->as($maker)->get(route('quotations.create'))->assertOk()->assertSee('Pilih template');
        $this->as($maker)->post(route('quotations.store'), [])->assertSessionHasErrors('template_id');
    }

    public function test_authorized_reader_can_preview_watermarked_draft_with_date_and_idr_formatting(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $basic = $this->userWithRole('office-user');
        $template = $this->template();
        $template->companyProfile->update([
            'address_lines' => ['Alamat lengkap yang ditampilkan'],
            'city' => 'Kota duplikat',
            'postal_code' => '99999',
            'country' => 'ZZ',
        ]);
        $this->as($maker)->post(route('quotations.store'), $this->payload($template));
        $quotation = Quotation::query()->sole();

        $preview = $this->as($maker)->get(route('quotations.preview', $quotation));

        $preview
            ->assertOk()
            ->assertSee('data-testid="quotation-preview"', false)
            ->assertSee('data-template-rendered="true"', false)
            ->assertSee('@page { size: A4 portrait; margin: 17mm 18mm 16mm; }', false)
            ->assertSee('.quotation-page { width: auto; min-height: 254mm; margin: 0; padding: 0 0 6mm; box-shadow: none; }', false)
            ->assertSee('.document-footer { position: static; margin-top: 6mm; transform: none; }', false)
            ->assertSee('display: table-header-group', false)
            ->assertSee('.rate-table th, .rate-table td { padding: 0 2mm 1.8mm;', false)
            ->assertSee('break-inside: avoid', false)
            ->assertSee('DRAFT — nomor belum terbit')
            ->assertSee('Preview draft - bukan dokumen resmi')
            ->assertSee('18 Juli 2026')
            ->assertSee('Rp 125.001')
            ->assertSee('Storage / day')
            ->assertDontSee('Name &amp; signature', false);
        $this->assertSame(1, substr_count((string) $preview->getContent(), 'Customer A'));

        $this->as($basic)->get(route('quotations.preview', $quotation))->assertForbidden();
    }

    public function test_quotation_output_escapes_untrusted_html(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();
        $payload = $this->payload($template);
        $payload['subject'] = '<script>alert("xss")</script>';
        $payload['customer_name'] = '<img src=x onerror=alert(1)>';

        $this->as($maker)->post(route('quotations.store'), $payload)->assertSessionHasNoErrors();
        $quotation = Quotation::query()->sole();

        $this->as($maker)->get(route('quotations.preview', $quotation))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_official_jblu_logo_has_verified_dimensions_ratio_and_hash(): void
    {
        $path = public_path('static/jblu.png');
        $size = getimagesize($path);

        $this->assertFileExists($path);
        $this->assertSame(896, $size[0]);
        $this->assertSame(755, $size[1]);
        $this->assertEqualsWithDelta(1.187, $size[0] / $size[1], 0.001);
        $this->assertSame('CF7F4C45F4D23C345E35D17A02758D92CD644E2FFE222F23EFE60F025A14DBCC', strtoupper(hash_file('sha256', $path)));
    }

    public function test_stale_edit_does_not_overwrite_a_newer_draft(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();
        $this->as($maker)->post(route('quotations.store'), $this->payload($template));
        $quotation = Quotation::query()->sole();
        $newer = $this->payload($template) + ['lock_version' => 0];
        $newer['subject'] = 'Newer edit';
        $this->as($maker)->put(route('quotations.update', $quotation), $newer)->assertSessionHasNoErrors();

        $stale = $this->payload($template) + ['lock_version' => 0];
        $stale['subject'] = 'Stale overwrite';
        $this->as($maker)->put(route('quotations.update', $quotation), $stale)
            ->assertSessionHasErrors('lock_version');

        $this->assertSame('Newer edit', $quotation->refresh()->subject);
        $this->assertSame(1, $quotation->audits()->where('action', 'quotation.updated')->count());
    }

    public function test_create_form_shows_active_template_version_and_loads_template_defaults(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();
        $template->update([
            'default_intro_text' => 'Default introduction',
            'default_closing_text' => 'Default closing',
            'default_terms' => ['Term template one', 'Term template two'],
        ]);

        $this->as($maker)->get(route('quotations.create'))
            ->assertOk()
            ->assertSee('Container quotation')
            ->assertSee('v1')
            ->assertSee('Default introduction')
            ->assertSee('Default closing')
            ->assertSee('Term template one');
    }

    public function test_nested_list_items_are_persisted_with_parent_relationship_and_depth_is_enforced(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $template = $this->template();
        $schema = [
            'presentation' => ['type' => 'nested_list', 'style' => 'unordered', 'content_key' => 'description', 'max_depth' => 2],
            'columns' => [['key' => 'description', 'label' => 'Description', 'value_type' => 'text', 'required' => true]],
        ];
        $template->update(['item_schema' => $schema, 'settings' => $schema]);
        $payload = $this->payload($template);
        $payload['items'] = [
            ['values' => ['description' => 'Parent']],
            ['parent_index' => 0, 'values' => ['description' => 'Child']],
        ];

        $this->as($maker)->post(route('quotations.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quotation = Quotation::query()->sole();
        $this->assertTrue($quotation->items[1]->parent->is($quotation->items[0]));

        $invalid = $payload;
        $invalid['items'][] = ['parent_index' => 1, 'values' => ['description' => 'Too deep']];
        $this->as($maker)->post(route('quotations.store'), $invalid)
            ->assertSessionHasErrors('items.2.parent_index');
    }

    public function test_switching_template_refreshes_snapshot_and_save_preview_redirects_to_preview(): void
    {
        $maker = $this->userWithRole('quotation-maker');
        $first = $this->template();
        $secondSchema = ['columns' => [['key' => 'service', 'label' => 'Service', 'value_type' => 'text', 'required' => true]]];
        $second = $first->companyProfile->templates()->create([
            'type' => 'quotation', 'template_key' => 'service-quotation', 'version' => 2,
            'name' => 'Service quotation', 'status' => 'active',
            'content_html' => '<p>Second {{ subject }}</p><div>{{ quotation_items }}</div>',
            'settings' => $secondSchema, 'item_schema' => $secondSchema,
        ]);
        $this->as($maker)->post(route('quotations.store'), $this->payload($first));
        $quotation = Quotation::query()->sole();

        $payload = $this->payload($second);
        $payload['items'] = [['values' => ['service' => 'Handling']]];
        $payload['lock_version'] = $quotation->lock_version;
        $payload['submit_action'] = 'preview';

        $this->as($maker)->put(route('quotations.update', $quotation), $payload)
            ->assertRedirect(route('quotations.preview', $quotation));

        $quotation->refresh();
        $this->assertSame($second->getKey(), $quotation->template_id);
        $this->assertSame(2, $quotation->template_snapshot['template_version']);
        $this->assertSame($second->content_html, $quotation->template_snapshot['content_html']);
        $this->assertSame($second->content_sha256, $quotation->template_content_sha256);
        $this->assertSame('service', $quotation->item_schema['columns'][0]['key']);
    }

    private function template(): DocumentTemplate
    {
        $profile = CompanyProfile::query()->create([
            'company_code' => 'JBLU', 'legal_name' => 'PT JBLU', 'display_name' => 'JBLU',
            'address_lines' => ['Jakarta'], 'city' => 'Jakarta', 'postal_code' => '10110', 'country' => 'ID',
        ]);

        return $profile->templates()->create([
            'type' => 'quotation', 'version' => 1, 'name' => 'Container quotation',
            'settings' => ['columns' => [
                ['key' => 'description', 'label' => 'Description', 'value_type' => 'text', 'required' => true],
                ['key' => 'unit_price', 'label' => 'Unit Price', 'value_type' => 'currency', 'required' => true],
                ['key' => 'available', 'label' => 'Available', 'value_type' => 'boolean', 'required' => false],
            ]],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(DocumentTemplate $template): array
    {
        return [
            'template_id' => $template->getKey(), 'quotation_date' => '2026-07-18',
            'subject' => 'Storage service', 'customer_name' => 'Customer A',
            'customer_address' => 'Jakarta', 'attention_name' => 'Budi', 'attention_role' => 'Manager',
            'sender_name' => 'Sales JBLU', 'sender_title' => 'Sales Manager', 'currency' => 'IDR',
            'intro_text' => 'Our offer', 'closing_text' => 'Thank you',
            'items' => [['values' => ['description' => 'Storage / day', 'unit_price' => '125000.50', 'available' => 'true']]],
            'terms' => ['TOP 30 days', 'VAT excluded'],
        ];
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
