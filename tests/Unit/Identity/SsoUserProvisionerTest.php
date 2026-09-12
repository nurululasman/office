<?php

namespace Tests\Unit\Identity;

use App\Data\Identity\SsoProfile;
use App\Models\User;
use App\Services\Identity\SsoUserProvisioner;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SsoUserProvisionerTest extends TestCase
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

    public function test_new_user_gets_name_from_sso_name_and_username_from_sso_username(): void
    {
        $profile = new SsoProfile(
            issuer: 'https://sso.example.test',
            subject: 'sub-new-123',
            tenantId: 'tenant-office',
            email: 'john@example.test',
            name: 'John Doe',
            avatarUrl: null,
            username: 'johndoe',
        );

        $user = app(SsoUserProvisioner::class)->provision($profile);

        $this->assertSame('johndoe', $user->username);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.test', $user->email);
    }

    public function test_existing_user_custom_name_is_not_overwritten_by_sso_login(): void
    {
        $user = User::factory()->create([
            'sso_issuer' => 'https://sso.example.test',
            'sso_subject' => 'sub-exist-456',
            'name' => 'Custom Input Name',
            'email' => 'jane@example.test',
            'username' => 'janedoe',
        ]);

        $profile = new SsoProfile(
            issuer: $user->sso_issuer,
            subject: $user->sso_subject,
            tenantId: 'tenant-office',
            email: 'jane@example.test',
            name: 'Jane From SSO',
            avatarUrl: null,
            username: 'janedoe',
        );

        $updated = app(SsoUserProvisioner::class)->provision($profile);

        $this->assertTrue($user->is($updated));
        $this->assertSame('Custom Input Name', $updated->name);
        $this->assertSame('janedoe', $updated->username);
    }

    public function test_new_user_provision_marks_was_first_login_true(): void
    {
        $profile = new SsoProfile(
            issuer: 'https://sso.example.test',
            subject: 'sub-first-login-1',
            tenantId: 'tenant-office',
            email: 'first@example.test',
            name: 'First User',
            avatarUrl: null,
            username: 'firstuser',
        );

        $user = app(SsoUserProvisioner::class)->provision($profile);

        $this->assertTrue($user->was_first_login);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_returning_user_provision_marks_was_first_login_false(): void
    {
        $user = User::factory()->create([
            'sso_issuer' => 'https://sso.example.test',
            'sso_subject' => 'sub-return-2',
            'email' => 'return@example.test',
            'name' => 'Returning User',
            'last_login_at' => now()->subDay(),
        ]);

        $profile = new SsoProfile(
            issuer: $user->sso_issuer,
            subject: $user->sso_subject,
            tenantId: 'tenant-office',
            email: 'return@example.test',
            name: 'Returning User',
            avatarUrl: null,
            username: 'returnuser',
        );

        $updated = app(SsoUserProvisioner::class)->provision($profile);

        $this->assertFalse($updated->was_first_login);
    }

    public function test_existing_user_without_last_login_at_marks_was_first_login_true(): void
    {
        $user = User::factory()->create([
            'sso_issuer' => 'https://sso.example.test',
            'sso_subject' => 'sub-never-logged-in-3',
            'email' => 'never@example.test',
            'name' => 'Never Logged In',
            'last_login_at' => null,
        ]);

        $profile = new SsoProfile(
            issuer: $user->sso_issuer,
            subject: $user->sso_subject,
            tenantId: 'tenant-office',
            email: 'never@example.test',
            name: 'Never Logged In',
            avatarUrl: null,
            username: 'neveruser',
        );

        $updated = app(SsoUserProvisioner::class)->provision($profile);

        $this->assertTrue($updated->was_first_login);
    }
}
