<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessManagementUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    /** @return array<string, array<string, int|string|null>> */
    private function validSsoSession(): array
    {
        return ['office.sso.tokens' => [
            'access_token' => 'encrypted', 'refresh_token' => null,
            'expires_at' => time() + 3600, 'authenticated_at' => time(),
        ]];
    }
}
