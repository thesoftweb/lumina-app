<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_every_group_in_the_defined_order(): void
    {
        $groups = $this->navigationFor('admin');

        $this->assertSame(
            ['Sala de aula', 'Acadêmico', 'Financeiro', 'Cadastros', 'Documentos', 'Configurações', 'Administração'],
            array_values(array_filter(array_keys($groups))),
        );
    }

    public function test_items_inside_groups_follow_the_defined_order(): void
    {
        $groups = $this->navigationFor('admin');

        $this->assertSame(['Alunos', 'Turmas', 'Matrículas', 'Pré-matrículas', 'Boletins', 'Frequências', 'Agendas'], $groups['Acadêmico']);
        $this->assertSame(['Faturas', 'Inadimplência', 'Planos e mensalidades', 'Eventos e contribuições'], $groups['Financeiro']);
        $this->assertSame(['Clientes', 'Professores', 'Fornecedores'], $groups['Cadastros']);
        $this->assertSame(['Empresa', 'Ano letivo', 'Bimestres', 'Níveis', 'Disciplinas'], $groups['Configurações']);
    }

    public function test_teacher_only_sees_classroom_and_academic_items(): void
    {
        $groups = $this->navigationFor('teacher');

        $this->assertSame(['Sala de aula', 'Acadêmico', 'Financeiro'], array_values(array_filter(array_keys($groups))));
        $this->assertNotContains('Matrículas', $groups['Acadêmico']);
        $this->assertNotContains('Pré-matrículas', $groups['Acadêmico']);
        $this->assertSame(['Eventos e contribuições'], $groups['Financeiro']);
        $this->assertContains('Fazer chamada', $groups['Sala de aula']);
        $this->assertContains('Lançar notas', $groups['Sala de aula']);
        $this->assertArrayNotHasKey('Administração', $groups);
        $this->assertArrayNotHasKey('Configurações', $groups);
    }

    public function test_secretary_sees_office_items_but_not_admin_only_ones(): void
    {
        $groups = $this->navigationFor('secretary');

        $this->assertSame(['Diário de classe', 'Planos de aula'], $groups['Sala de aula']);
        $this->assertSame(['Alunos', 'Turmas', 'Matrículas', 'Pré-matrículas', 'Boletins', 'Frequências', 'Agendas'], $groups['Acadêmico']);
        $this->assertSame(['Faturas', 'Inadimplência', 'Eventos e contribuições'], $groups['Financeiro']);
        $this->assertSame(['Clientes', 'Professores'], $groups['Cadastros']);
        $this->assertSame(['Documentos emitidos'], $groups['Documentos']);
        $this->assertSame(['Ano letivo', 'Bimestres', 'Níveis', 'Disciplinas'], $groups['Configurações']);
        $this->assertArrayNotHasKey('Administração', $groups);
    }

    public function test_each_menu_item_has_a_distinct_icon(): void
    {
        $this->navigationFor('admin');
        $icons = collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->map(fn ($item) => $item->getIcon() instanceof \BackedEnum ? $item->getIcon()->value : (string) $item->getIcon())
            ->filter();

        $dupes = $icons->duplicates()->values()->all();
        $this->assertSame([], $dupes, 'Ícones repetidos: '.implode(', ', $dupes));
    }

    /** @return array<string, list<string>> grupo => rótulos dos itens, na ordem do menu */
    private function navigationFor(string $role): array
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::create(['name' => 'Escola', 'email' => 'escola@example.test']);
        $user = User::create(['name' => ucfirst($role), 'email' => "{$role}@example.test", 'password' => 'password', 'company_id' => $company->id]);
        $user->assignRole($role);

        if ($role === 'teacher') {
            Teacher::create(['name' => 'Professor', 'email' => 'teacher@example.test', 'user_id' => $user->id]);
        }

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $result = [];
        foreach (Filament::getNavigation() as $group) {
            $result[(string) ($group->getLabel() ?? '')] = collect($group->getItems())
                ->map(fn ($item) => $item->getLabel())->values()->all();
        }

        return $result;
    }
}
