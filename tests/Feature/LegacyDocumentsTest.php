<?php

namespace Tests\Feature;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Módulo antigo de documentos livres: fora do menu, só o admin consulta, e a impressão exige login. */
class LegacyDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $template = DocumentTemplate::create(['name' => 'Recibo antigo', 'type' => 'receipt', 'content' => '<p>Recibo de {{customer_name}}</p>']);
        // O formulário antigo grava `data` já codificado em JSON (CreateDocument), e a view de impressão o decodifica
        $this->document = Document::create([
            'document_template_id' => $template->id, 'content' => '<p>Recibo de Maria Responsável</p>',
            'data' => json_encode(['customer_name' => 'Maria Responsável']), 'status' => 'generated',
        ]);
    }

    private function actingAsRole(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_old_modules_are_out_of_the_menu(): void
    {
        $this->actingAsRole('admin');

        $this->assertFalse(DocumentResource::shouldRegisterNavigation());
        $this->assertFalse(DocumentTemplateResource::shouldRegisterNavigation());
    }

    public function test_print_requires_login_and_admin(): void
    {
        $url = route('documents.print', $this->document);

        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
        $this->get(route('documents.pdf', $this->document))->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAsRole('secretary');
        $this->get($url)->assertForbidden();

        $this->actingAsRole('admin');
        $this->get($url)->assertOk()->assertSee('Recibo de Maria Responsável');
    }

    public function test_only_admin_opens_the_old_pages(): void
    {
        $this->actingAsRole('secretary');
        $this->get(DocumentResource::getUrl('index'))->assertForbidden();
        $this->get(DocumentTemplateResource::getUrl('index'))->assertForbidden();

        $this->actingAsRole('admin');
        $this->get(DocumentResource::getUrl('index'))->assertOk();
        $this->get(DocumentTemplateResource::getUrl('index'))->assertOk();
    }
}
