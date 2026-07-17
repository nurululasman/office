<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SsoUserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_supports_passwordless_sso_shadow_users(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'sso_issuer',
            'sso_subject',
            'avatar_url',
            'is_active',
            'last_login_at',
        ]));

        $user = User::query()->create([
            'sso_issuer' => 'https://sso.example.test',
            'sso_subject' => '019f72be-3540-7f9f-bd15-cf407a3993bf',
            'name' => 'office.user',
            'email' => 'office.user@example.test',
            'password' => null,
        ]);

        $user->refresh();

        $this->assertNull($user->password);
        $this->assertTrue($user->is_active);
    }
}
