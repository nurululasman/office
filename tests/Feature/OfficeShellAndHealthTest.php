<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeShellAndHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_liveness_and_readiness_are_public_and_do_not_expose_exception_details(): void
    {
        Storage::fake('documents');

        $this->getJson('/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);

        $this->getJson('/health/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => ['database' => 'ok', 'queue' => 'ok', 'storage' => 'ok'],
            ]);

        config()->set('office.documents.disk', 'missing-health-disk');
        $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('checks.storage', 'fail')
            ->assertJsonMissing(['exception']);
    }

    public function test_dashboard_uses_jblu_shell_and_only_shows_authorized_operational_data(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Office']);
        $admin->assignRole('system-admin');

        $this->actingAs($admin)
            ->withSession($this->validSsoSession())
            ->get('/office')
            ->assertOk()
            ->assertSee('JBLU')
            ->assertSee('Admin Office')
            ->assertSee('User aktif')
            ->assertSee('Role tersedia')
            ->assertSee('Aktivitas terbaru')
            ->assertSee('static/jblu.png', false);

        $basicUser = User::factory()->create(['name' => 'Basic User']);
        $basicUser->assignRole('office-user');

        $this->actingAs($basicUser)
            ->withSession($this->validSsoSession())
            ->get('/office')
            ->assertOk()
            ->assertSee('Akun siap digunakan')
            ->assertDontSee('User aktif')
            ->assertDontSee('Aktivitas terbaru');
    }

    public function test_custom_error_page_is_rendered_for_unknown_route(): void
    {
        $this->get('/alamat-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('JBLU');
    }

    /** @return array<string, array<string, int|string|null>> */
    private function validSsoSession(): array
    {
        return [
            'office.sso.tokens' => [
                'access_token' => 'encrypted',
                'refresh_token' => null,
                'expires_at' => time() + 3600,
                'authenticated_at' => time(),
            ],
        ];
    }
}
