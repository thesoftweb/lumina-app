<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Realizar login do usuário admin
     *
     * POST /api/login
     * Body: { "email": "admin@example.com", "password": "password123" }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Validações já feitas pelo LoginRequest
            $email = $request->validated()['email'];
            $password = $request->validated()['password'];

            // Buscar usuário pelo email
            $user = User::where('email', $email)->first();

            // Validar senha
            if (!$user || !Hash::check($password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciais inválidas',
                ], 401);
            }

            // Validar que é admin
            if (!$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar',
                ], 403);
            }

            // Validar que tem um tenant (company)
            if (!$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não está associado a nenhuma empresa',
                ], 400);
            }

            // Buscar tenant (company)
            $tenant = $user->company;

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não encontrada',
                ], 404);
            }

            // Retornar dados do usuário e tenant
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'admin',
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao realizar login',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

