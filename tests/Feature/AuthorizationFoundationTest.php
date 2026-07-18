<?php

namespace Tests\Feature;

use App\Data\Identity\SsoProfile;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\SsoUserProvisioner;
use Database\Seeders\BootstrapSystemAdminSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_permission_catalog_and_role_matrix_are_seeded_idempotently(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(32, Permission::query()->count());
        $this->assertSame(9, Role::query()->count());
        $this->assertSame(32, Role::query()->where('slug', 'system-admin')->firstOrFail()->permissions()->count());
        $this->assertSame(0, Role::query()->where('slug', 'office-user')->firstOrFail()->permissions()->count());
        $this->assertTrue(Role::query()->where('slug', 'quotation-maker')->firstOrFail()
            ->permissions()->where('slug', 'quotations.complete-direct')->exists());
        $this->assertFalse(Role::query()->where('slug', 'quotation-maker')->firstOrFail()
            ->permissions()->where('slug', 'quotations.approve')->exists());
    }

    public function test_jit_user_gets_only_the_permissionless_base_role(): void
    {
        $user = app(SsoUserProvisioner::class)->provision(new SsoProfile(
            issuer: 'https://sso.example.test',
            subject: '019f72be-3540-7f9f-bd15-cf407a3993bf',
            tenantId: 'tenant-office',
            email: 'user@example.test',
            name: 'user',
            avatarUrl: null,
        ));

        $this->assertTrue($user->hasRole('office-user'));
        $this->assertFalse($user->hasPermissionTo('documents.read'));
        $this->assertFalse(Gate::forUser($user)->allows('quotations.create'));
    }

    public function test_role_permissions_are_evaluated_from_database_on_each_check(): void
    {
        $user = User::factory()->create();
        $user->assignRole('quotation-maker');

        $this->assertTrue(Gate::forUser($user)->allows('quotations.create'));
        $this->assertTrue(Gate::forUser($user)->allows('quotations.complete-direct'));
        $this->assertFalse(Gate::forUser($user)->allows('quotations.approve'));

        $user->roles()->detach();

        $this->assertFalse(Gate::forUser($user)->allows('quotations.create'));
    }

    public function test_bootstrap_admin_seeder_targets_exact_sso_identity_and_is_idempotent(): void
    {
        $user = User::factory()->create([
            'sso_issuer' => 'https://sso.example.test',
            'sso_subject' => '019f72be-3540-7f9f-bd15-cf407a3993bf',
        ]);
        config()->set('authorization.bootstrap_admin', [
            'issuer' => $user->sso_issuer,
            'subject' => $user->sso_subject,
        ]);

        $this->seed(BootstrapSystemAdminSeeder::class);
        $this->seed(BootstrapSystemAdminSeeder::class);

        $this->assertTrue($user->hasRole('system-admin'));
        $this->assertSame(1, $user->roles()->where('slug', 'system-admin')->count());
        $this->assertTrue(Gate::forUser($user)->allows('roles.manage'));
        $this->assertTrue(Gate::forUser($user)->allows('contracts.approve'));
        $this->assertSame(1, AuditLog::query()->where('action', 'authorization.role.assigned')->count());
    }

    public function test_user_policy_prevents_self_deactivation_and_self_role_assignment(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $admin->assignRole('system-admin');

        $this->assertFalse(Gate::forUser($admin)->allows('update', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('assignRoles', $admin));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $other));
        $this->assertTrue(Gate::forUser($admin)->allows('assignRoles', $other));
    }
}
