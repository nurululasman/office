<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessManagementUiTest extends TestCase
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

    public function test_system_admin_can_open_access_management_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system-admin');

        $this->actingAs($admin)->withSession($this->validSsoSession())->get(route('users.index'))->assertOk()->assertSee('Atur akses');
        $this->actingAs($admin)->withSession($this->validSsoSession())->get(route('roles.index'))->assertOk()->assertSee('Tambah role');
        $this->actingAs($admin)->withSession($this->validSsoSession())->get(route('permissions.index'))->assertOk()->assertSee('Katalog permission sistem');
    }

    public function test_admin_can_update_another_users_status_and_roles_with_audit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system-admin');
        $user = User::factory()->create();
        $user->assignRole('office-user');
        $maker = Role::query()->where('slug', 'quotation-maker')->sole();

        $this->actingAs($admin)->withSession($this->validSsoSession())->put(route('users.update', $user), ['is_active' => false, 'roles' => [$maker->id]])->assertRedirect(route('users.index'));

        $this->assertFalse($user->fresh()->is_active);
        $this->assertEquals(['quotation-maker'], $user->roles()->pluck('slug')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'authorization.user_access.updated', 'subject_id' => $user->id]);
    }

    public function test_admin_cannot_change_own_access(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system-admin');

        $this->actingAs($admin)->withSession($this->validSsoSession())->put(route('users.update', $admin), ['is_active' => false, 'roles' => []])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->hasRole('system-admin'));
    }

    public function test_admin_can_create_update_and_delete_custom_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system-admin');
        $read = Permission::query()->where('slug', 'documents.read')->sole();
        $issue = Permission::query()->where('slug', 'documents.issue')->sole();

        $this->actingAs($admin)->withSession($this->validSsoSession())->post(route('roles.store'), ['name' => 'Registry Staff', 'slug' => 'registry-staff', 'description' => 'Read registry', 'permissions' => [$read->id]])->assertRedirect();
        $role = Role::query()->where('slug', 'registry-staff')->sole();
        $this->assertEquals(['documents.read'], $role->permissions()->pluck('slug')->all());

        $this->actingAs($admin)->withSession($this->validSsoSession())->put(route('roles.update', $role), ['name' => 'Registry Officer', 'slug' => 'registry-officer', 'description' => null, 'permissions' => [$read->id, $issue->id]])->assertRedirect();
        $this->assertSame(2, $role->permissions()->count());

        $this->actingAs($admin)->withSession($this->validSsoSession())->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));
        $this->assertModelMissing($role);
        $this->assertSame(3, AuditLog::query()->where('action', 'like', 'authorization.role.%')->count());
    }

    public function test_system_roles_are_read_only_and_basic_users_are_forbidden(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system-admin');
        $basic = User::factory()->create();
        $basic->assignRole('office-user');
        $systemRole = Role::query()->where('slug', 'system-admin')->sole();

        $this->actingAs($admin)->withSession($this->validSsoSession())->put(route('roles.update', $systemRole), ['name' => 'Changed', 'slug' => 'changed', 'permissions' => []])->assertForbidden();
        $this->actingAs($basic)->withSession($this->validSsoSession())->get(route('users.index'))->assertForbidden();
        $this->actingAs($basic)->withSession($this->validSsoSession())->get(route('roles.index'))->assertForbidden();
        $this->actingAs($basic)->withSession($this->validSsoSession())->get(route('permissions.index'))->assertForbidden();
    }

    public function test_admin_can_update_user_name_and_upload_signature(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('system-admin');
        $user = User::factory()->create(['name' => 'Original Name', 'username' => 'sso_jblu_user']);
        $user->assignRole('office-user');

        $payload = [
            'name' => 'Updated User Name',
            'username' => 'hacked_username',
            'signature' => UploadedFile::fake()->image('ttd.png', 150, 75),
            'is_active' => true,
            'roles' => $user->roles()->pluck('id')->all(),
        ];

        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->put(route('users.update', $user), $payload)
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Updated User Name', $user->name);
        $this->assertSame('sso_jblu_user', $user->username); // Username cannot be modified!
        $this->assertMatchesRegularExpression('#^/storage/user-signatures/[a-f0-9]{64}\.png$#', $user->signature_path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $user->signature_path));

        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Updated User Name')
            ->assertSee('sso_jblu_user')
            ->assertSee($user->signature_path, false);

        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Updated User Name')
            ->assertSee('sso_jblu_user')
            ->assertSee($user->signature_path, false);
    }

    public function test_validation_rejects_invalid_signature_format(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system-admin');
        $user = User::factory()->create();
        $user->assignRole('office-user');

        $invalid = [
            'name' => 'Valid Name',
            'signature' => UploadedFile::fake()->create('signature.svg', 10, 'image/svg+xml'),
        ];

        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->put(route('users.update', $user), $invalid)
            ->assertSessionHasErrors(['signature']);
    }

    public function test_user_can_update_own_name_and_signature_without_changing_access(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['name' => 'Admin Self']);
        $admin->assignRole('system-admin');

        $payload = [
            'name' => 'Admin Self Updated',
            'signature' => UploadedFile::fake()->image('admin-ttd.png', 120, 60),
        ];

        $this->actingAs($admin)->withSession($this->validSsoSession())
            ->put(route('users.update', $admin), $payload)
            ->assertRedirect(route('users.index'));

        $admin->refresh();
        $this->assertSame('Admin Self Updated', $admin->name);
        $this->assertNotNull($admin->signature_path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $admin->signature_path));
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
