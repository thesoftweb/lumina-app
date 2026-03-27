# Implementação da Funcionalidade de Agenda

## Arquivos Criados

### 1. Migration - `2026_03_27_create_agendas_table.php`
- Cria tabela `agendas` com campos:
  - `id` (PK)
  - `title` (string)
  - `description` (text, nullable)
  - `date` (dateTime)
  - `classroom_id` (FK, nullable)
  - `global` (boolean)
  - `company_id` (FK)
  - Índices em `date`, `global`, `classroom_id`

### 2. Model - `app/Models/Agenda.php`
- Relacionamentos:
  - `belongsTo(Classroom)`
  - `belongsTo(Company)`
- Scopes:
  - `forClassroom($classroomId)` - Retorna agendas globais ou da turma
  - `upcoming()` - Retorna agendas futuras
- Casts automáticos para `date` e `global`
- Ordenação padrão por data ascendente

### 3. Service - `app/Services/AgendaService.php`
- `getStudentAgendas($classroomId, $upcoming = false)` - Agendas do aluno
- `getStudentAgendasPaginated($classroomId, $perPage = 15, $upcoming = false)` - Com paginação
- `create(array $data)` - Criar agenda
- `update(Agenda $agenda, array $data)` - Atualizar
- `delete(Agenda $agenda)` - Deletar
- `getClassroomAgendas($classroomId, $upcoming)` - Agendas específicas da turma
- `getGlobalAgendas($upcoming)` - Agendas globais

### 4. Filament Resource - `app/Filament/Resources/AgendaResource.php`
- CRUD completo
- Formulário com:
  - Título (obrigatório)
  - Descrição (rich editor, opcional)
  - Data e hora (obrigatória)
  - Toggle "Agenda Global"
  - Seletor de turma (obrigatório se não for global)
- Tabela com:
  - Título (searchable, sortable)
  - Data/hora formatada
  - Nome da turma
  - Indicador global (ícone)
  - Data de criação (toggleable)
- Filtros:
  - Por agendas globais
  - Por turma específica
- Navegação automática com ícone e label em português

### 5. Controller Portal - `app/Http/Controllers/PortalAgendaController.php`
- `index()` - Lista agendas do aluno (paginadas)
- `upcoming()` - Agendas futuras (JSON)
- `show($id)` - Detalhes de uma agenda

### 6. Relacionamento no Classroom
- Adicionado método `agendas()` para retornar agendas da turma

## Como Usar

### Criação de Agenda (Admin)
```php
$agenda = Agenda::create([
    'title' => 'Reunião de Pais',
    'description' => 'Reunião com os responsáveis',
    'date' => '2026-04-15 19:00:00',
    'global' => true,
    'company_id' => auth()->user()->company_id,
]);
```

### Buscar Agendas do Aluno
```php
$agendaService = app(AgendaService::class);

// Todas as agendas
$agendas = $agendaService->getStudentAgendas($classroomId);

// Apenas futuras
$upcoming = $agendaService->getStudentAgendas($classroomId, upcoming: true);

// Com paginação
$paginated = $agendaService->getStudentAgendasPaginated($classroomId, perPage: 15);
```

### Scope de Query
```php
// Agendas globais ou da turma específica
$agendas = Agenda::forClassroom($classroomId)->get();

// Apenas agendas futuras
$upcoming = Agenda::upcoming()->get();
```

## Próximas Etapas

1. Registrar rotas no `routes/web.php`:
   ```php
   Route::prefix('portal')->name('portal.')->middleware(['auth'])->group(function () {
       Route::get('/agendas', [PortalAgendaController::class, 'index'])->name('agendas.index');
       Route::get('/agendas/upcoming', [PortalAgendaController::class, 'upcoming'])->name('agendas.upcoming');
       Route::get('/agendas/{id}', [PortalAgendaController::class, 'show'])->name('agendas.show');
   });
   ```

2. Criar view `resources/views/portal/agendas/index.blade.php`

3. Executar migration:
   ```bash
   php artisan migrate
   ```

4. (Opcional) Adicionar policies de permissão para Filament

## Permissões Sugeridas

- `view_any_agenda` - Vizualizar lista
- `create_agenda` - Criar agenda
- `update_agenda` - Atualizar agenda
- `delete_agenda` - Deletar agenda
