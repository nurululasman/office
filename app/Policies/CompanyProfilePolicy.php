<?php

namespace App\Policies;

use App\Models\CompanyProfile;
use App\Models\User;

class CompanyProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('company-profiles.read');
    }

    public function view(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->hasPermissionTo('company-profiles.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('company-profiles.manage');
    }

    public function update(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->hasPermissionTo('company-profiles.manage');
    }

    public function delete(User $user, CompanyProfile $companyProfile): bool
    {
        return $user->hasPermissionTo('company-profiles.manage');
    }
}
