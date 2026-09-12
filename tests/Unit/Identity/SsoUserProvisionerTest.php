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
}
