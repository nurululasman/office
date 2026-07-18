<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('users.read');
    }

    public function view(User $actor, User $subject): bool
    {
        return $actor->is($subject) || $actor->hasPermissionTo('users.read');
    }

    public function update(User $actor, User $subject): bool
    {
        return ! $actor->is($subject) && $actor->hasPermissionTo('users.manage');
    }

    public function assignRoles(User $actor, User $subject): bool
    {
        return ! $actor->is($subject) && $actor->hasPermissionTo('roles.manage');
    }
}
