<?php

namespace App\Support;

use App\Models\Company;

class TenantHelper
{
    /**
     * Retorna o tenant (Company) do request atual
     */
    public static function tenant(): ?Company
    {
        return app()->has('tenant') ? app('tenant') : null;
    }

    /**
     * Retorna o ID do tenant do request atual
     */
    public static function tenantId(): ?int
    {
        return app()->has('tenant_id') ? app('tenant_id') : null;
    }

    /**
     * Verifica se há um tenant no request
     */
    public static function hasTenant(): bool
    {
        return app()->has('tenant') && app('tenant') !== null;
    }
}

/**
 * Helper global para acessar o tenant
 * Uso: tenant() ou tenant_id()
 */
if (!function_exists('tenant')) {
    function tenant(): ?\App\Models\Company
    {
        return \App\Support\TenantHelper::tenant();
    }
}

if (!function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return \App\Support\TenantHelper::tenantId();
    }
}

if (!function_exists('has_tenant')) {
    function has_tenant(): bool
    {
        return \App\Support\TenantHelper::hasTenant();
    }
}
