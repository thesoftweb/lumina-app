<?php

namespace App\Filament\Concerns;

use App\Support\NavigationAccess;

trait ChecksNavigationAccess
{
    public static function shouldRegisterNavigation(): bool
    {
        return NavigationAccess::allows(static::class);
    }
}
