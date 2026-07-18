<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('quotations.read');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        if (! in_array($quotation->status, ['draft', 'rejected'], true)) {
            return false;
        }

        return $user->hasPermissionTo('quotations.update-any')
            || ($quotation->created_by === $user->getKey() && $user->hasPermissionTo('quotations.update-own'));
    }

    public function preview(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.pdf.read');
    }

    public function viewPdf(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.pdf.read');
    }

    public function completeDirect(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.complete-direct')
            && ($quotation->created_by === $user->getKey() || $user->hasPermissionTo('quotations.update-any'));
    }

    public function submit(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.submit')
            && ($quotation->created_by === $user->getKey() || $user->hasPermissionTo('quotations.update-any'));
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.approve');
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.reject');
    }

    public function void(User $user, Quotation $quotation): bool
    {
        return $user->hasPermissionTo('quotations.void');
    }
}
