<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        // Captura o tenant da URL (exemplo: /api/{tenant}/settings)
        $tenantSlug = $request->route('tenant');

        if ($tenantSlug) {
            // Busca a empresa pelo slug
            $company = Company::where('slug', $tenantSlug)->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant não encontrado',
                ], 404);
            }

            // Armazena o tenant no request e no container da aplicação
            $request->merge(['tenant_id' => $company->id, 'tenant' => $company]);
            app()->bind('tenant', fn() => $company);
            app()->bind('tenant_id', fn() => $company->id);
        }

        return $next($request);
    }
}
