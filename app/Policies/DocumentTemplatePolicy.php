<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;

class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('quotation-template.view');
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        return $template->type === 'quotation'
            && $user->hasPermissionTo('quotation-template.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('quotation-template.create');
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $template->type === 'quotation'
            && $template->status === 'draft'
            && $user->hasPermissionTo('quotation-template.update');
    }

    public function createVersion(User $user, DocumentTemplate $template): bool
    {
        return $template->type === 'quotation'
            && $user->hasPermissionTo('quotation-template.create');
    }

    public function activate(User $user, DocumentTemplate $template): bool
    {
        return $template->type === 'quotation'
            && $template->status === 'draft'
            && $user->hasPermissionTo('quotation-template.activate');
    }

    public function archive(User $user, DocumentTemplate $template): bool
    {
        return $template->type === 'quotation'
            && in_array($template->status, ['draft', 'active'], true)
            && $user->hasPermissionTo('quotation-template.archive');
    }
}
