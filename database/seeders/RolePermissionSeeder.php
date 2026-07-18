<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = [];

        foreach (config('authorization.permissions') as $group => $permissions) {
            foreach ($permissions as $slug) {
                $permission = Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => Str::headline(str_replace('.', ' ', $slug)),
                        'group' => $group,
                        'is_system' => true,
                    ],
                );
                $permissionIds[$slug] = $permission->getKey();
            }
        }

        foreach (config('authorization.roles') as $slug => $permissions) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline($slug),
                    'description' => $slug === 'office-user'
                        ? 'Role dasar JIT tanpa permission domain.'
                        : 'Role sistem sesuai matriks akses Office.',
                    'is_system' => true,
                ],
            );

            $role->permissions()->sync(array_map(
                fn (string $permission): int => $permissionIds[$permission],
                $permissions,
            ));
        }
    }
}
