Sistema escolar em Laravel

Stack:
- PHP 8+
- Laravel
- MySQL
- FilamentPHP v4 (admin)
- Painel do aluno separado

Arquitetura:
- Admin gerencia dados via Filament
- Portal do aluno consome dados
- Estrutua monolitica

Entidades existentes:
- Students
- Classroom
- Enrollments
- Teachers

## Filament v4 - Padrão de Implementação

### Importações Corretas de Components

```php
// ✅ CORRETO - Estrutura, Layout e Containers
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

// ✅ CORRETO - Form Inputs
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;

// ✅ CORRETO - Ações
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

// ❌ ERRADO - Não usar esses namespaces
// use Filament\Tables\Actions\EditAction;
// use Filament\Forms\Components\Section;
```

### Estrutura do Form (Schema)

```php
// ✅ CORRETO
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        Section::make('Título')
            ->columns(2)
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                // Mais components aqui
            ]),
    ]);
}
```

### Ações na Tabela
```php
// ✅ CORRETO
->recordActions([
    EditAction::make(),
    DeleteAction::make(),
])
->toolbarActions([
    BulkActionGroup::make([
        DeleteBulkAction::make(),
    ]),
])

// ❌ ERRADO
// ->actions([...])
// ->bulkActions([...])
```

### Callbacks com Get/Set

```php
// ✅ CORRETO em Filament v4
->required(fn (callable $get) => !$get('global'))
->hidden(fn (callable $get) => $get('global'))

// ❌ ERRADO (v3 style)
// ->required(fn (Forms\Get $get) => !$get('global'))
```

### Ícones Heroicon
```php
// ✅ CORRETO
use Filament\Support\Icons\Heroicon;
protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

// Outros ícones comuns:
// Heroicon::OutlinedUsers
// Heroicon::OutlinedAcademicCap
// Heroicon::OutlinedCalendarDays
```

### Padrão de Separação em Filament v4

Organizar em Schemas separados para reutilização:
```
app/Filament/Resources/AgendaResource/
    - Schemas/
        - AgendaForm.php (form schema)
        - AgendaInfolist.php (view schema)
    - Tables/
        - AgendasTable.php (table config)
    - Pages/
        - ListAgendas.php
        - CreateAgenda.php
        - EditAgenda.php
```

## Padrão MultiTenant (company_id) - CRÍTICO

### Regra #1: TODA entidade que pertence a empresa DEVE ter company_id

Sem falhar:
```php
// ❌ SEMPRE FALHA - company_id fica null
$data['company_id'] = auth()->user()->company_id;

// ✅ SEMPRE FUNCIONA - tem fallback
$data['company_id'] = auth()->user()->company_id ?? 1;
```

### Regra #2: CreateRecord DEVE setar company_id

```php
// app/Filament/Resources/AgendaResource/Pages/CreateAgenda.php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['company_id'] = auth()->user()->company_id ?? 1;
    return $data;
}
```

### Regra #3: EditRecord DEVE proteger company_id

```php
// app/Filament/Resources/AgendaResource/Pages/EditAgenda.php
protected function mutateFormDataBeforeSave(array $data): array
{
    // Nunca permitir alterar company_id
    $data['company_id'] = $this->record->company_id;
    return $data;
}
```

### Regra #4: Model DEVE ter escopo global (opcional mas recomendado)

```php
// app/Models/Agenda.php
protected static function booted()
{
    static::addGlobalScope('company', function ($query) {
        if (auth()->check() && auth()->user()->company_id) {
            $query->where('company_id', auth()->user()->company_id);
        }
    });
}
```

## Padrão Portal do Aluno - PortalControllers

### Fluxo de Autenticação
1. Aluno acessa `/portal/login`
2. PortalController salva `customer_document` em Session
3. Controllers subsequentes leem `session('customer_document')`

### Padrão Correto para Buscar Dados do Aluno

```php
// ✅ CORRETO - em PortalAgendaController
private function getCustomerFromSession()
{
    $document = session('customer_document');
    return Customer::where('document', $document)->first();
}

public function index()
{
    $customer = $this->getCustomerFromSession();
    
    if (!$customer) {
        return response()->json(['error' => 'Student not found'], 404);
    }

    // Buscar enrollment via student
    $enrollment = Enrollment::whereHas('student', function ($query) use ($customer) {
        $query->where('customer_id', $customer->id);
    })
        ->where('status', 'active')
        ->first();

    if (!$enrollment) {
        return response()->json(['error' => 'No active enrollment'], 404);
    }
    
    // Agora usar classroom_id do enrollment
    $agendas = $this->agendaService->getStudentAgendasPaginated($enrollment->classroom_id);
}
```

### Estrutura de Relacionamentos
```
Customer (document="123.456.789-00") 
    ↓ hasMany
    Student (customer_id)
        ↓ hasMany
        Enrollment (student_id, classroom_id, status)
            ↓ belongsTo
            Classroom (id)
                ↓ hasMany
                Agenda (classroom_id, global)
```

### ❌ ERRADO - Não usar
```php
Student::whereRelation('student.person', 'cpf', $document)  // person() não existe!
Enrollment::where('cpf', $document)  // Enrollment não tem cpf!
```

## Padrão de Views do Portal

### Estrutura de Diretórios
```
resources/views/portal/
    ├── login.blade.php
    ├── student.blade.php
    ├── events.blade.php
    ├── agendas/
    │   └── index.blade.php
    └── events/
        └── show.blade.php
```

### Retornar View no Controller
```php
// ✅ CORRETO
public function index()
{
    return view('portal.agendas.index', [
        'agendas' => $agendas,
        'enrollment' => $enrollment,
    ]);
}
```

### Estrutura Básica da View
```blade
@extends('layouts.app')

@section('title', 'Agendas - Portal do Aluno')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Agendas</h1>
    </div>

    @if($agendas->isEmpty())
        {{-- Estado vazio --}}
    @else
        {{-- Listar agendas --}}
    @endif
</div>
@endsection
```

### Paginação em Blade
```blade
@if($agendas->hasPages())
    <div class="mt-8">
        {{ $agendas->links() }}
    </div>
@endif
```

Objetivo atual:
Criar sistema de Agenda para alunos
