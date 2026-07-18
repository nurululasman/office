<?php

namespace App\Policies;

use App\Models\DocumentType;
use App\Models\User;

class DocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('document-types.read');
    }

    public function view(User $user, DocumentType $documentType): bool
    {
        return $user->hasPermissionTo('document-types.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('document-types.manage');
    }

    public function update(User $user, DocumentType $documentType): bool
    {
        return $user->hasPermissionTo('document-types.manage');
    }

    public function delete(User $user, DocumentType $documentType): bool
    {
        return $user->hasPermissionTo('document-types.manage');
    }
}
