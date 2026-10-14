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
| Documentos | Documentos, Modelos de documentos |
| Configurações | Empresa, Ano letivo, Bimestres, Níveis, Disciplinas |
| Administração | Usuários |

Critérios: grupos por tarefa (o professor usa "Sala de aula" todo dia; secretaria usa "Acadêmico"), itens mais usados primeiro, ícone exclusivo por item.

Regras do Filament 4 que importam aqui:
- O grupo não pode ter ícone se os itens têm ícone (gera exceção). Por isso o enum só tem rótulo.
- Para um item novo: `$navigationGroup = NavGroup::X`, `$navigationSort`, `$navigationLabel`, `$navigationIcon` (ícone diferente dos demais; o teste `NavigationTest` falha se repetir).

## Visibilidade por perfil e por permissão

Arquivo único: `config/navigation.php`. Chave = classe do Resource; sem entrada = qualquer usuário do painel vê (o acesso real continua nas policies).

```php
SupplierResource::class => ['roles' => ['coordinator'], 'permission' => null],
```

- `admin` sempre vê tudo.
- Se `permission` estiver preenchida, ela vale (Spatie Permission, `$user->can(...)`) e `roles` é ignorado.
- Para migrar um item de perfis para permissões: trocar por `['permission' => 'view_suppliers']` e criar a permissão em `RolePermissionSeeder`.
- Resources usam o trait `App\Filament\Concerns\ChecksNavigationAccess`. Pages continuam com `canAccess()`.

Importante: isto só controla o **menu**. A autorização real está nas policies (painel com `strictAuthorization`) e em `canAccess()`.

## Achados que ficaram como estavam

- **Matrículas só aparece para `admin`:** `EnrollmentPolicy` aceita `admin` e `secretary`, e o papel `secretary` não existe no `RolePermissionSeeder`. A coordenação não vê o menu de Matrículas. Decidir se coordenação deve ter acesso ou se será criado o papel de secretaria.
- **Painel duplicado corrigido:** o menu registrava dois painéis iniciais (o do Filament e o do projeto). Ficou só o do projeto.
- **Disciplinas, Bimestres e Níveis** agora têm cadastro (Resources simples, com modal) em "Configurações", visíveis para coordenação e admin. Excluir é bloqueado quando há turmas, notas ou vínculos. Estados, cidades, plano de contas e perfis de aluno continuam só por seeder.
