<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait HandlesBulkActions
{
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
