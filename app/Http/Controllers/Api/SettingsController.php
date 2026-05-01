<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Retorna as configurações da empresa (tenant)
     *
     * GET /api/{tenant}/settings
     */
    public function index(): JsonResponse
    {
        try {
            $tenant = app('tenant');

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant não encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'email' => $tenant->email,
                    'address' => $tenant->address,
                    'phone' => $tenant->phone,
                    'website' => $tenant->website,
                    'tax_id' => $tenant->tax_id,
                    'logo_path' => $tenant->logo_path,
                    'status' => $tenant->status,
                    'environment' => $tenant->environment,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar configurações',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
