<?php

namespace App\Services\Identity;

use App\Data\Identity\SsoProfile;
use App\Exceptions\IdentityProviderException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SsoUserProvisioner
{
    public function provision(SsoProfile $profile): User
    {
        return DB::transaction(function () use ($profile): User {
            $user = User::query()
                ->where('sso_issuer', $profile->issuer)
                ->where('sso_subject', $profile->subject)
                ->lockForUpdate()
                ->first();

            if ($user === null && User::query()->where('email', $profile->email)->exists()) {
                throw new IdentityProviderException('Email SSO telah digunakan oleh identitas Office lain.');
            }

            if ($user !== null && ! $user->is_active) {
                throw new IdentityProviderException('Akun Office tidak aktif.');
            }

            $user ??= new User([
                'sso_issuer' => $profile->issuer,
                'sso_subject' => $profile->subject,
                'password' => null,
                'is_active' => true,
            ]);

            $user->forceFill([
                'name' => $profile->name,
                'email' => $profile->email,
                'avatar_url' => $profile->avatarUrl,
                'last_login_at' => now(),
            ])->save();

            $user->assignRole('office-user');

            return $user;
        }, 3);
    }
}
