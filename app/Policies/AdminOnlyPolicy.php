<?php

namespace App\Policies;

use App\Models\User;

/** Áreas só da direção: usuários, dados da empresa, preços (planos) e contas a pagar. */
class AdminOnlyPolicy extends AdministrativePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
