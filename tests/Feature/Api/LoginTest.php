<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar empresa de teste
        $this->company = Company::create([
            'name' => 'Criança Inteligente',
            'slug' => 'crianca-inteligente',
            'email' => 'admin@criancainteligente.com',
        ]);

        // Criar usuário admin de teste
        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'company_id' => $this->company->id,
        ]);

        // Atribuir role admin
        $this->admin->assignRole('admin');
    }

    public function test_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'user' => ['id', 'name', 'email', 'role'],
            'tenant' => ['id', 'name', 'slug'],
        ]);
        $response->assertJsonPath('user.email', 'admin@test.com');
        $response->assertJsonPath('tenant.slug', 'crianca-inteligente');
    }

    public function test_login_with_invalid_email()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_login_with_invalid_password()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Credenciais inválidas',
        ]);
    }

    public function test_login_requires_email()
    {
        $response = $this->postJson('/api/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_login_requires_password()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_login_with_non_admin_user()
    {
        // Criar um usuário teacher
        $teacher = User::create([
            'name' => 'Teacher Test',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password123'),
            'company_id' => $this->company->id,
        ]);
        $teacher->assignRole('teacher');

        $response = $this->postJson('/api/login', [
            'email' => 'teacher@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Você não tem permissão para acessar',
        ]);
    }

    public function test_login_with_user_without_company()
    {
        // Criar um usuário sem company_id
        $userNoCompany = User::create([
            'name' => 'No Company User',
            'email' => 'nocompany@test.com',
            'password' => bcrypt('password123'),
            'company_id' => null,
        ]);
        $userNoCompany->assignRole('admin');

        $response = $this->postJson('/api/login', [
            'email' => 'nocompany@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Usuário não está associado a nenhuma empresa',
        ]);
    }
}