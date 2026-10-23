<?php

namespace App\Policies;

use App\Models\IssuedDocument;
use App\Models\User;

/** Secretaria e admin emitem e reimprimem; só o admin cancela. Documento emitido não se edita nem se exclui. */
class IssuedDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['secretary', 'admin']);
    }

    public function view(User $user, IssuedDocument $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, IssuedDocument $document): bool
    {
        return $user->hasRole('admin') && $document->canceled_at === null;
    }

    public function update(User $user, IssuedDocument $document): bool
    {
        return false;
    }

    public function delete(User $user, IssuedDocument $document): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
