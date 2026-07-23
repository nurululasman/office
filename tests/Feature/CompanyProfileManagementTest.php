<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_document_admin_can_create_view_update_and_upload_immutable_logo(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('document-admin');
        $payload = $this->payload() + ['logo' => UploadedFile::fake()->image('logo.png', 200, 100)];

        $response = $this->as($admin)->post(route('company-profiles.store'), $payload);

        $profile = CompanyProfile::query()->sole();
        $response->assertRedirect(route('company-profiles.show', $profile));
        $this->assertSame(['Jl. Example 1', 'Gedung A'], $profile->address_lines);
        $this->assertSame('ID', $profile->country);
        $this->assertMatchesRegularExpression('#^/storage/company-logos/[a-f0-9]{64}\.png$#', $profile->logo_path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $profile->logo_path));
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_profile.created', 'subject_id' => $profile->getKey()]);

        $this->as($admin)->get(route('company-profiles.show', $profile))
            ->assertOk()->assertSee('PT Example Logistics')->assertSee($profile->logo_path, false);

        $updated = $this->payload();
        $updated['display_name'] = 'Example Updated';
        $updated['is_active'] = '0';
        $this->as($admin)->put(route('company-profiles.update', $profile), $updated)
            ->assertRedirect(route('company-profiles.show', $profile));

        $profile->refresh();
        $this->assertSame('Example Updated', $profile->display_name);
        $this->assertFalse($profile->is_active);
        $this->assertNotNull($profile->logo_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_profile.updated', 'subject_id' => $profile->getKey()]);
    }

    public function test_validation_normalizes_codes_and_rejects_duplicate_or_unsafe_branding_fields(): void
    {
        $admin = $this->userWithRole('document-admin');
        CompanyProfile::query()->create($this->modelData());
        $invalid = $this->payload();
        $invalid['company_code'] = 'ex ample';
        $invalid['country'] = 'Indonesia';
        $invalid['website'] = 'javascript:alert(1)';
        $invalid['primary_color'] = '#GGGGGG';
        $invalid['address_lines_text'] = " \n ";
        $invalid['logo'] = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');

        $this->as($admin)->post(route('company-profiles.store'), $invalid)
            ->assertSessionHasErrors([
                'company_code', 'country', 'website', 'primary_color',
                'address_lines_text', 'logo',
            ]);

        $duplicate = $this->payload();
        $duplicate['company_code'] = 'example';
        $this->as($admin)->post(route('company-profiles.store'), $duplicate)
            ->assertSessionHasErrors('company_code');
        $this->assertDatabaseCount('company_profiles', 1);
    }

    public function test_permissions_allow_auditor_read_only_and_reject_basic_user(): void
    {
        $profile = CompanyProfile::query()->create($this->modelData());
        $auditor = $this->userWithRole('auditor');
        $basic = $this->userWithRole('office-user');

        $this->as($auditor)->get(route('company-profiles.index'))->assertOk();
        $this->as($auditor)->get(route('company-profiles.show', $profile))->assertOk();
        $this->as($auditor)->get(route('company-profiles.edit', $profile))->assertForbidden();
        $this->as($basic)->get(route('company-profiles.index'))->assertForbidden();
    }

    public function test_unused_profile_can_be_deleted_but_profile_used_by_template_is_protected(): void
    {
        $admin = $this->userWithRole('system-admin');
        $unused = CompanyProfile::query()->create($this->modelData(['company_code' => 'UNUSED']));
        $this->as($admin)->delete(route('company-profiles.destroy', $unused))
            ->assertRedirect(route('company-profiles.index'));
        $this->assertModelMissing($unused);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_profile.deleted']);

        $used = CompanyProfile::query()->create($this->modelData(['company_code' => 'USED']));
        $used->templates()->create([
            'type' => 'quotation', 'template_key' => 'used-profile', 'version' => 1,
            'name' => 'Used', 'status' => 'active',
            'content_html' => DocumentTemplate::LEGACY_CONTENT_HTML,
            'settings' => ['columns' => []], 'item_schema' => ['columns' => []],
        ]);
        $this->as($admin)->delete(route('company-profiles.destroy', $used))
            ->assertSessionHasErrors();
        $this->assertModelExists($used);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'company_code' => 'example',
            'legal_name' => 'PT Example Logistics',
            'display_name' => 'Example',
            'address_lines_text' => "Jl. Example 1\nGedung A",
            'city' => 'Jakarta',
            'postal_code' => '10110',
            'country' => 'id',
            'email' => 'office@example.test',
            'phone' => '+62 21 123',
            'website' => 'https://example.test',
            'tax_id' => '01.234.567.8-999.000',
            'primary_color' => '#087eae',
            'is_active' => '1',
        ];
    }

    /** @return array<string, mixed> */
    private function modelData(array $overrides = []): array
    {
        return array_replace([
            'company_code' => 'EXAMPLE',
            'legal_name' => 'PT Example Logistics',
            'display_name' => 'Example',
            'address_lines' => ['Jl. Example 1'],
            'city' => 'Jakarta',
            'postal_code' => '10110',
            'country' => 'ID',
            'primary_color' => '#087EAE',
            'is_active' => true,
        ], $overrides);
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession(['office.sso.tokens' => [
            'access_token' => 'encrypted', 'refresh_token' => null,
            'expires_at' => time() + 3600, 'authenticated_at' => time(),
        ]]);
    }
}
