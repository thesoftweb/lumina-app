# Navegação do painel e visibilidade por permissão

## Estrutura do menu

Grupos e ordem definidos em `app/Enums/NavGroup.php` (a ordem dos `case` é a ordem no menu). Ordem dos itens dentro do grupo: `$navigationSort` de cada Resource/Page.

| Grupo | Itens (em ordem) |
| --- | --- |
| (sem grupo) | Painel, Meu perfil (professor) |
| Sala de aula | Fazer chamada, Lançar notas, Diário de classe, Planos de aula, Aniversariantes |
| Acadêmico | Alunos, Turmas, Matrículas, Boletins, Frequências, Agendas |
| Financeiro | Faturas, Planos e mensalidades, Eventos e contribuições |
| Cadastros | Clientes, Professores, Fornecedores |
| Documentos | Documentos emitidos, Documentos, Modelos de documentos |
| Configurações | Empresa, Ano letivo, Bimestres, Níveis, Disciplinas |
| Administração | Usuários |

Critérios: grupos por tarefa (o professor usa "Sala de aula" todo dia; secretaria usa "Acadêmico"), itens mais usados primeiro, ícone exclusivo por item.

Regras do Filament 4 que importam aqui:
- O grupo não pode ter ícone se os itens têm ícone (gera exceção). Por isso o enum só tem rótulo.
- Para um item novo: `$navigationGroup = NavGroup::X`, `$navigationSort`, `$navigationLabel`, `$navigationIcon` (ícone diferente dos demais; o teste `NavigationTest` falha se repetir).

## Papéis

O sistema tem três papéis, criados pelo `RolePermissionSeeder`. Quem tinha o antigo papel `coordinator` passou a `secretary` (migration `2026_10_22_000001_replace_coordinator_role_with_secretary`).

| Papel | Quem é | Em resumo |
| --- | --- | --- |
| `admin` | Direção / gestão | Acesso total. É o único que exclui registros, gerencia usuários e papéis, altera os dados da empresa (inclusive a chave do Asaas), os planos e mensalidades (preços) e os fornecedores. |
| `secretary` | Secretaria escolar | Rotina da escola: matrículas, alunos, responsáveis, cobranças, documentos e estrutura do ano letivo. Cadastra e edita, mas não exclui. Consulta o pedagógico sem alterar. |
| `teacher` | Professor | Só as próprias turmas: chamada, notas, diário de classe, planos de aula, boletins e aniversariantes. Precisa estar vinculado a um cadastro de professor para entrar no painel. |

| Área | `admin` | `secretary` | `teacher` |
| --- | --- | --- | --- |
| Matrículas (ficha e contrato inclusos) | total | ver, cadastrar, editar | — |
| Alunos | total | ver, cadastrar, editar | ver os das suas turmas |
| Clientes (responsáveis financeiros) | total | ver, cadastrar, editar | — |
| Faturas (cobranças dos alunos) | total | ver, gerar, editar | — |
| Planos e mensalidades, Fornecedores | total | — | — |
| Turmas | total | ver, cadastrar, editar | ver as suas |
| Professores | total | ver, cadastrar, editar | o próprio cadastro |
| Ano letivo, Bimestres, Níveis, Disciplinas | total | ver, cadastrar, editar | — |
| Eventos e contribuições | total | ver, cadastrar, editar | ver os das suas turmas |
| Agendas | total | ver, cadastrar, editar | — |
| Documentos e modelos | total | ver, emitir, editar | — |
| Documentos emitidos (declarações, recibos) | emitir, reimprimir, cancelar | emitir, reimprimir | — |
| Frequência, diário de classe, planos de aula, notas | total, inclusive corrigir | só consulta | lança e edita os próprios |
| Boletins | todos | todos | das suas turmas |
| Fazer chamada, Lançar notas, Aniversariantes, Meu perfil | — | — | sim |
| Empresa, Usuários | total | — | — |
| Excluir registros | sim | não | só os próprios lançamentos |

Onde cada regra mora:
- Policies em `app/Policies`. `AdministrativePolicy` (secretaria e admin; só o admin exclui) cobre os cadastros da secretaria; `AdminOnlyPolicy` cobre usuários, empresa, planos, fornecedores e plano de contas. O mapeamento está em `AppServiceProvider::boot()`.
- Entrada no painel: `User::canAccessPanel()`.
- Páginas: `canAccess()` de cada Page (`ReportCards`, `TakeAttendance`, `Gradebook`, ...).
- O teste `RolesTest` descreve esta tabela; ao mudar um acesso, atualize os dois.

## Visibilidade por perfil e por permissão

Arquivo único: `config/navigation.php`. Chave = classe do Resource; sem entrada = qualquer usuário do painel vê (o acesso real continua nas policies).

```php
SupplierResource::class => ['roles' => ['admin'], 'permission' => null],
```

- `admin` sempre vê tudo.
- Se `permission` estiver preenchida, ela vale (Spatie Permission, `$user->can(...)`) e `roles` é ignorado.
- Para migrar um item de perfis para permissões: trocar por `['permission' => 'view_suppliers']` e criar a permissão em `RolePermissionSeeder`.
- Resources usam o trait `App\Filament\Concerns\ChecksNavigationAccess`. Pages continuam com `canAccess()`.

Importante: isto só controla o **menu**. A autorização real está nas policies (painel com `strictAuthorization`) e em `canAccess()`.

## Achados que ficaram como estavam

- **Matrículas só aparecia para `admin`:** resolvido com a criação do papel `secretary` (ver "Papéis").
- **Painel duplicado corrigido:** o menu registrava dois painéis iniciais (o do Filament e o do projeto). Ficou só o do projeto.
- **Disciplinas, Bimestres e Níveis** agora têm cadastro (Resources simples, com modal) em "Configurações", visíveis para secretaria e admin. Excluir é bloqueado quando há turmas, notas ou vínculos. Estados, cidades, plano de contas e perfis de aluno continuam só por seeder.
