<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria uma empresa de teste
        $this->company = Company::create([
            'name' => 'Criança Inteligente',
            'slug' => 'crianca-inteligente',
            'email' => 'contato@criancainteligente.com',
            'address' => 'Rua X, 123',
            'phone' => '(11) 99999-9999',
            'website' => 'https://criancainteligente.com',
            'tax_id' => '12.345.678/0001-90',
            'status' => 'active',
            'environment' => 'production',
        ]);
    }

    /**
     * Testa se o endpoint de settings retorna as configurações da empresa
     */
    public function test_settings_endpoint_returns_company_settings()
    {
        $response = $this->getJson('/api/crianca-inteligente/settings');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $this->company->id,
                'name' => 'Criança Inteligente',
                'slug' => 'crianca-inteligente',
                'email' => 'contato@criancainteligente.com',
                'status' => 'active',
                'environment' => 'production',
            ],
        ]);
    }

    /**
     * Testa se retorna 404 para um tenant inexistente
     */
    public function test_settings_endpoint_returns_404_for_invalid_tenant()
    {
        $response = $this->getJson('/api/tenant-inexistente/settings');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Tenant não encontrado',
        ]);
    }

    /**
     * Testa se o students endpoint funciona com tenant
     */
    public function test_students_endpoint_with_tenant()
    {
        $response = $this->getJson("/api/{$this->company->slug}/students");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }
}
