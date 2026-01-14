# Modelos Pedagógicos - Implementação Completa

## 📋 Resumo da Implementação

Foram criados 3 novos modelos pedagógicos com suas tabelas, relações, políticas de acesso e validações para o painel do professor no FilamentPHP v4.

---

## ✅ Modelos Criados

### 1️⃣ **Attendance (Frequência/Presença)**

**Tabela:** `attendances`

**Campos:**

```sql
- id (PK)
- enrollment_id (FK → enrollments) - Vinculado à matrícula do aluno
- classroom_id (FK → classrooms) - Qual turma a frequência pertence
- teacher_id (FK → teachers) - Qual professor registrou
- date (date) - Data da aula
- present (boolean) - True se presente, false se ausente
- justified (boolean) - Se a ausência foi justificada
- justification (text, nullable) - Motivo da ausência
- created_at, updated_at (timestamps)
- deleted_at (soft delete)
```

**Relações:**

```php
- belongsTo(Enrollment) → Aluno/Matrícula
- belongsTo(Classroom) → Turma
- belongsTo(Teacher) → Professor que registrou
```

**Model File:** [app/Models/Attendance.php](app/Models/Attendance.php)

**Migration:** [2026_01_13_234201_create_attendances_table.php](database/migrations/2026_01_13_234201_create_attendances_table.php)

**Soft Deletes:** ✅ Habilitado para manter histórico

---

### 2️⃣ **ClassDiary (Diário de Classe)**

**Tabela:** `class_diaries`

**Campos:**

```sql
- id (PK)
- classroom_id (FK → classrooms) - Qual turma
- teacher_id (FK → teachers) - Qual professor
- subject_id (FK → subjects) - Qual disciplina/matéria
- date (date) - Data da aula
- content (text) - Conteúdo ministrado
- activities (text, nullable) - Atividades realizadas em sala
- homework (text, nullable) - Tarefa de casa
- observations (text, nullable) - Observações/anotações
- created_at, updated_at (timestamps)
- deleted_at (soft delete)
```

**Relações:**

```php
- belongsTo(Classroom) → Turma
- belongsTo(Teacher) → Professor que registrou
- belongsTo(Subject) → Disciplina
```

**Model File:** [app/Models/ClassDiary.php](app/Models/ClassDiary.php)

**Migration:** [2026_01_13_234202_create_class_diaries_table.php](database/migrations/2026_01_13_234202_create_class_diaries_table.php)

**Soft Deletes:** ✅ Habilitado para auditoria

---

### 3️⃣ **LessonPlan (Plano de Aula)**

**Tabela:** `lesson_plans`

**Campos:**

```sql
- id (PK)
- classroom_id (FK → classrooms) - Para qual turma
- teacher_id (FK → teachers) - Qual professor planeja
- subject_id (FK → subjects) - Qual disciplina
- term_id (FK → terms) - Qual período letivo/bimestre
- title (string) - Título da aula
- description (text) - Descrição do plano
- objectives (text, nullable) - Objetivos de aprendizado
- methodology (text, nullable) - Metodologia de ensino
- resources (text, nullable) - Recursos necessários
- duration_minutes (unsigned integer, nullable) - Duração em minutos
- scheduled_date (date) - Data agendada para aula
- status (enum) - draft|scheduled|completed|cancelled
- created_at, updated_at (timestamps)
- deleted_at (soft delete)
```

**Relações:**

```php
- belongsTo(Classroom) → Turma
- belongsTo(Teacher) → Professor
- belongsTo(Subject) → Disciplina
- belongsTo(Term) → Período letivo
```

**Model File:** [app/Models/LessonPlan.php](app/Models/LessonPlan.php)

**Migration:** [2026_01_13_234202_create_lesson_plans_table.php](database/migrations/2026_01_13_234202_create_lesson_plans_table.php)

**Soft Deletes:** ✅ Habilitado para histórico

---

## 🔐 Políticas de Acesso (Authorization)

### **AttendancePolicy**

**Arquivo:** [app/Policies/AttendancePolicy.php](app/Policies/AttendancePolicy.php)

| Ação                        | Teacher    | Coordinator | Admin    |
| --------------------------- | ---------- | ----------- | -------- |
| viewAny (listar)            | ✅ Só suas | ✅ Todas    | ✅ Todas |
| view (visualizar)           | ✅ Suas    | ✅ Todas    | ✅ Todas |
| create (criar)              | ✅         | ✅          | ✅       |
| update (editar)             | ✅ Suas    | ✅ Todas    | ✅ Todas |
| delete (deletar)            | ✅ Suas    | ❌          | ✅       |
| restore (restaurar)         | ❌         | ❌          | ✅       |
| forceDelete (força deletar) | ❌         | ❌          | ✅       |

**Lógica:**

-   Teacher acessa **apenas frequências que ele registrou** (via `teacher_id`)
-   Coordinator/Admin acessam **todas** as frequências
-   Apenas Admin pode deletar permanentemente

---

### **ClassDiaryPolicy**

**Arquivo:** [app/Policies/ClassDiaryPolicy.php](app/Policies/ClassDiaryPolicy.php)

| Ação                        | Teacher    | Coordinator | Admin    |
| --------------------------- | ---------- | ----------- | -------- |
| viewAny (listar)            | ✅ Só seus | ✅ Todos    | ✅ Todos |
| view (visualizar)           | ✅ Seus    | ✅ Todos    | ✅ Todos |
| create (criar)              | ✅         | ✅          | ✅       |
| update (editar)             | ✅ Seus    | ✅ Todos    | ✅ Todos |
| delete (deletar)            | ✅ Seus    | ❌          | ✅       |
| restore (restaurar)         | ❌         | ❌          | ✅       |
| forceDelete (força deletar) | ❌         | ❌          | ✅       |

**Lógica:**

-   Teacher acessa **apenas diários que ele criou** (via `teacher_id`)
-   Coordinator/Admin acessam **todos** os diários
-   Apenas Admin pode deletar permanentemente

---

### **LessonPlanPolicy**

**Arquivo:** [app/Policies/LessonPlanPolicy.php](app/Policies/LessonPlanPolicy.php)

| Ação                        | Teacher    | Coordinator | Admin    |
| --------------------------- | ---------- | ----------- | -------- |
| viewAny (listar)            | ✅ Só seus | ✅ Todos    | ✅ Todos |
| view (visualizar)           | ✅ Seus    | ✅ Todos    | ✅ Todos |
| create (criar)              | ✅         | ✅          | ✅       |
| update (editar)             | ✅ Seus    | ✅ Todos    | ✅ Todos |
| delete (deletar)            | ✅ Seus    | ❌          | ✅       |
| restore (restaurar)         | ❌         | ❌          | ✅       |
| forceDelete (força deletar) | ❌         | ❌          | ✅       |

**Lógica:**

-   Teacher acessa **apenas planos que ele criou** (via `teacher_id`)
-   Coordinator/Admin acessam **todos** os planos
-   Apenas Admin pode deletar permanentemente

---

## 📦 Registro das Policies

As 3 policies foram registradas no `AppServiceProvider`:

**Arquivo:** [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)

```php
protected $policies = [
    Teacher::class => TeacherPolicy::class,
    Grade::class => GradePolicy::class,
    Classroom::class => ClassroomPolicy::class,
    Attendance::class => AttendancePolicy::class,      // Nova
    ClassDiary::class => ClassDiaryPolicy::class,      // Nova
    LessonPlan::class => LessonPlanPolicy::class,      // Nova
];
```

Isso permite que o Laravel Gate/Filament autorize automaticamente todas as ações.

---

## 🧪 Como Testar

### **Login como Professor**

```
Email: professor@example.com
Senha: password123
```

O professor verá **apenas seus dados**:

-   Frequências que registrou
-   Diários que escreveu
-   Planos de aula que criou

### **Login como Admin**

```
Email: admin@example.com
Senha: password123
```

Admin vê **tudo** e pode gerenciar todos os dados.

---

## 📋 Casos de Uso

### **Scenario 1: Professor registra frequência**

1. Acessa `/admin` com email `professor@example.com`
2. Abre Attendance (quando Resource for criado)
3. Filtra por sua turma
4. Marca presente/ausente para cada aluno
5. Salva registro → **Policy valida** que é seu registro (teacher_id = seu id)

### **Scenario 2: Professor cria diário de classe**

1. Acessa painel como professor
2. Abre ClassDiary
3. Preenche: conteúdo, atividades, tarefa de casa
4. Salva → **Policy valida** que é seu diário

### **Scenario 3: Professor cria plano de aula**

1. Acessa painel
2. Abre LessonPlan
3. Preenche: título, objetivos, metodologia, data agendada
4. Salva como **draft**
5. Depois marca como **scheduled** ou **completed**

### **Scenario 4: Coordinator tenta deletar frequência de outro professor**

1. Acessa como coordinator
2. Tenta deletar frequência → **❌ Policy nega acesso** (only admin can delete)

### **Scenario 5: Teacher tenta ver diário de outro professor**

1. Acessa como professor1
2. Tenta visualizar diário de professor2 → **❌ Policy nega acesso**

---

## 🔗 Relações do Banco de Dados

```
Teacher
├── Attendance (1:N) - Frequências que registrou
├── ClassDiary (1:N) - Diários que escreveu
└── LessonPlan (1:N) - Planos que criou

Classroom
├── Attendance (1:N) - Frequências da turma
├── ClassDiary (1:N) - Diários da turma
└── LessonPlan (1:N) - Planos para turma

Subject
├── ClassDiary (1:N) - Diários da disciplina
└── LessonPlan (1:N) - Planos da disciplina

Enrollment
└── Attendance (1:N) - Frequência do aluno

Term
└── LessonPlan (1:N) - Planos do período letivo
```

---

## 🎯 Funcionalidades Adicionadas

### **Soft Deletes**

-   Todos os 3 modelos possuem soft deletes
-   Registros deletados por um professor **não são removidos**, apenas marcados como deletados
-   Admin pode restaurar registros

### **Casts**

-   `date` campos são automaticamente castados para `Carbon\Carbon`
-   `boolean` campos (present, justified) são castados para bool
-   `enum` status em LessonPlan é validado automaticamente

### **Timestamps**

-   `created_at`, `updated_at` rastreiam criação e modificação
-   Útil para auditoria

---

## 📊 Diagrama de Fluxo de Autorização

```
Professor tenta acessar Attendance::view($attendance)
    ↓
LaravelGate chama AttendancePolicy::view($user, $attendance)
    ↓
Verifica: if user.teacher_id == attendance.teacher_id
    ↓
SIM → ✅ Autoriza acesso
NÃO → ❌ 403 Unauthorized
```

---

## 🚀 Próximos Passos

1. **Criar Filament Resources** para cada modelo:

    - `AttendanceResource` com tabela por turma
    - `ClassDiaryResource` com calendario
    - `LessonPlanResource` com filtros por termo

2. **Adicionar validações** nas policies:

    - Impedir lançamento de frequência para datas futuras
    - Impedir edição de diários antigos (ex: +30 dias)

3. **Criar Dashboard do Professor** com:

    - Número de alunos por turma
    - Taxa de frequência média
    - Próximas aulas planejadas

4. **Integração entre modelos:**
    - Sugerir criar ClassDiary após registrar Attendance
    - Validar se aula foi planejada antes de registrar frequência

---

## ✨ Status Final

✅ **Migrations:** Criadas e executadas  
✅ **Modelos:** Implementados com relações  
✅ **Policies:** Implementadas e registradas  
✅ **Autorização:** Funcional (testado via policy)  
✅ **Soft Deletes:** Habilitados em todos os 3 modelos  
✅ **Banco de Dados:** 3 novas tabelas criadas

**Total de Tabelas Novas:** 3  
**Total de Policies Novas:** 3  
**Total de Modelos Novos:** 3
