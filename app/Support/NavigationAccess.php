<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Decide se um item aparece no menu, a partir de config/navigation.php.
 * Afeta apenas a visibilidade no menu: o acesso real continua nas policies e em canAccess().
 */
class NavigationAccess
{
    public static function allows(string $class): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $rule = config("navigation.access.{$class}");

        if (! $rule || $user->hasRole('admin')) {
            return true;
        }

        if (! empty($rule['permission'])) {
            return $user->can($rule['permission']);
        }

        if (! empty($rule['roles'])) {
            return $user->hasAnyRole($rule['roles']);
        }

        return true;
    }
}
