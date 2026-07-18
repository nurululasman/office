<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('documents.read');
    }

    public function view(User $user, Document $document): bool
    {
        return $document->issued_by === $user->getKey() || $user->hasPermissionTo('documents.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('documents.issue');
    }

    public function void(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('documents.void');
    }
}
