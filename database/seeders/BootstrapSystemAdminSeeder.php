<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Seeder;
use RuntimeException;

class BootstrapSystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        $issuer = config('authorization.bootstrap_admin.issuer');
        $subject = config('authorization.bootstrap_admin.subject');

        if (! is_string($issuer) || $issuer === '' || ! is_string($subject) || $subject === '') {
            throw new RuntimeException('OFFICE_BOOTSTRAP_ADMIN_SSO_ISSUER dan OFFICE_BOOTSTRAP_ADMIN_SSO_SUBJECT wajib diisi.');
        }

        $user = User::query()
            ->where('sso_issuer', $issuer)
            ->where('sso_subject', $subject)
            ->first();

        if ($user === null) {
            throw new RuntimeException('User bootstrap belum tersedia. Login ke Office satu kali sebelum menjalankan seeder ini.');
        }

        if (! $user->is_active) {
            throw new RuntimeException('User bootstrap harus berstatus aktif.');
        }

        $alreadyAssigned = $user->hasRole('system-admin');
        $user->assignRole('system-admin');

        if (! $user->hasRole('system-admin')) {
            throw new RuntimeException('Role system-admin belum tersedia. Jalankan RolePermissionSeeder terlebih dahulu.');
        }

        if (! $alreadyAssigned) {
            app(AuditLogger::class)->record(
                'authorization.role.assigned',
                actor: $user,
                subject: $user,
                context: ['role' => 'system-admin', 'source' => 'bootstrap_seeder'],
            );
        }
    }
}
