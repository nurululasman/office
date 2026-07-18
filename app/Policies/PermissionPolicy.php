<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('roles.read');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('roles.read');
    }

    public function update(User $user, Permission $permission): bool
    {
        return ! $permission->is_system && $user->hasPermissionTo('roles.manage');
    }
}
