# Painel do Professor - Sistema de Autenticação e Autorização Implementado

## 📋 Resumo da Implementação

Um sistema completo de autenticação e autorização foi implementado para permitir que professores acessem o painel Filament com acesso restrito apenas a seus dados e turmas.

---

## ✅ O que foi implementado

### 1. **Vinculação User ↔ Teacher**

-   ✅ Migration `add_user_id_to_teachers_table` criada
-   ✅ Coluna `user_id` (foreign key) adicionada à tabela `teachers`
-   ✅ Relação `Teacher::user()` - belongsTo(User)
-   ✅ Relação `User::teacher()` - hasOne(Teacher)

**Arquivo:** [database/migrations/2026_01_13_231553_add_user_id_to_teachers_table.php](database/migrations/2026_01_13_231553_add_user_id_to_teachers_table.php)

---

### 2. **Sistema de Roles e Permissões (Spatie Permission)**

-   ✅ Package `spatie/laravel-permission` instalado
-   ✅ Trait `HasRoles` adicionado ao model `User`
-   ✅ Migrations de roles/permissions publicadas e executadas

**Roles criados:**

-   `teacher` - Acesso básico para professores
-   `coordinator` - Acesso expandido para coordenadores pedagógicos
-   `admin` - Acesso total

**Permissões para Teacher:**

```
- view_own_classroom
- view_own_grades
- create_grade
- edit_own_grade
- view_own_attendance
- create_attendance
- view_own_class_diary
- create_class_diary
- view_own_lesson_plan
- create_lesson_plan
```

**Arquivo:** [database/seeders/RolePermissionSeeder.php](database/seeders/RolePermissionSeeder.php)

---

### 3. **Autenticação do Filament Restrita**

-   ✅ Método `canAccessPanel()` do User atualizado
-   ✅ Apenas usuários com roles `teacher`, `coordinator` ou `admin` podem acessar o painel

**Arquivo:** [app/Models/User.php](app/Models/User.php) - linha ~62

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->hasAnyRole(['teacher', 'coordinator', 'admin']);
}
```

---

### 4. **Autorização baseada em Policy (Authorization)**

#### **TeacherPolicy** - Controla acesso a perfis de professores

-   ✅ Teacher pode visualizar/editar apenas seu próprio perfil
-   ✅ Coordinator e Admin podem gerenciar qualquer professor
-   ✅ Apenas Admin pode deletar

**Arquivo:** [app/Policies/TeacherPolicy.php](app/Policies/TeacherPolicy.php)

#### **GradePolicy** - Controla acesso a notas/grades

-   ✅ Teacher pode visualizar/editar apenas suas próprias notas
-   ✅ Teacher pode criar notas
-   ✅ Coordinator e Admin têm acesso total

**Arquivo:** [app/Policies/GradePolicy.php](app/Policies/GradePolicy.php)

#### **ClassroomPolicy** - Controla acesso a turmas

-   ✅ Teacher pode visualizar apenas turmas em que leciona
-   ✅ Coordinator e Admin podem gerenciar qualquer turma
-   ✅ Apenas Coordinator e Admin podem criar/editar turmas

**Arquivo:** [app/Policies/ClassroomPolicy.php](app/Policies/ClassroomPolicy.php)

---

### 5. **Registro de Policies no ServiceProvider**

-   ✅ Policies registradas em `AppServiceProvider`
-   ✅ Authorization automática via Laravel Gate

**Arquivo:** [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)

---

### 6. **Seeders para População de Dados**

#### **RolePermissionSeeder**

-   Cria 3 roles (teacher, coordinator, admin)
-   Cria permissões específicas para cada role
-   Executa: `php artisan db:seed --class=RolePermissionSeeder`

#### **TeacherUserSeeder**

-   Cria usuário professor de teste:
    -   **Email:** professor@example.com
    -   **Senha:** password123
    -   **Role:** teacher
-   Cria usuário admin de teste:

    -   **Email:** admin@example.com
    -   **Senha:** password123
    -   **Role:** admin

-   Executa: `php artisan db:seed --class=TeacherUserSeeder`

**Arquivos:**

-   [database/seeders/RolePermissionSeeder.php](database/seeders/RolePermissionSeeder.php)
-   [database/seeders/TeacherUserSeeder.php](database/seeders/TeacherUserSeeder.php)

---

## 🔧 Como Usar

### **Login como Professor**

1. Acesse `/admin`
2. Email: `professor@example.com`
3. Senha: `password123`

Professor terá acesso apenas a:

-   Suas próprias turmas
-   Suas próprias notas lançadas
-   Suas próprias presenças registradas
-   Seus próprios registros de diário de classe

### **Login como Admin**

1. Acesse `/admin`
2. Email: `admin@example.com`
3. Senha: `password123`

Admin tem acesso total ao sistema.

---

## 📦 Estrutura do Banco de Dados

### Nova Coluna em `teachers`

```sql
ALTER TABLE teachers ADD COLUMN user_id BIGINT UNSIGNED NULLABLE UNIQUE;
ALTER TABLE teachers ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
```

### Novas Tabelas (Spatie Permission)

-   `roles` - Papéis do sistema
-   `permissions` - Permissões disponíveis
-   `role_has_permissions` - Relação roles ↔ permissions
-   `model_has_roles` - Relação users ↔ roles
-   `model_has_permissions` - Relação users ↔ permissions diretas

---

## 🔐 Fluxo de Autorização

```
Usuário tenta acessar um recurso
    ↓
Verifica canAccessPanel() → tem role?
    ↓
SIM → Acessa Filament
    ↓
Tenta visualizar um recurso (Grade, Classroom, etc)
    ↓
Verifica Policy (TeacherPolicy, GradePolicy, etc)
    ↓
SIM → Acessa recurso
NÃO → 403 Unauthorized
```

---

## 🚀 Próximos Passos Sugeridos

1. **Criar modelos pedagógicos adicionais:**

    - `Attendance` - Presença/Frequência
    - `ClassDiary` - Diário de Classe
    - `LessonPlan` - Plano de Aula

2. **Criar Resources Filament customizados:**

    - `AttendanceResource`
    - `ClassDiaryResource`
    - `GradeResource` (com UI otimizada para lançamento rápido)
    - `LessonPlanResource`

3. **Criar Dashboard do Professor:**

    - Página inicial com resumo de turmas
    - Cards informativos (alunos, frequência média)
    - Gráficos de desempenho
    - Links rápidos para ações principais

4. **Adicionar filtros em Resources:**
    - Filtrar grades por turma/período
    - Filtrar turmas por ano letivo
    - Buscar alunos por matrícula

---

## 📁 Arquivos Criados/Modificados

### Criados

-   `database/migrations/2026_01_13_231553_add_user_id_to_teachers_table.php`
-   `database/seeders/RolePermissionSeeder.php`
-   `database/seeders/TeacherUserSeeder.php`
-   `app/Policies/TeacherPolicy.php`
-   `app/Policies/GradePolicy.php`
-   `app/Policies/ClassroomPolicy.php`
-   `app/Policies/EnrollmentPolicy.php` (criado mas não implementado)
-   `config/permission.php` (publicado do Spatie)
-   `database/migrations/2026_01_13_232345_create_permission_tables.php` (Spatie)

### Modificados

-   [app/Models/User.php](app/Models/User.php)
-   [app/Models/Teacher.php](app/Models/Teacher.php)
-   [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)
-   [database/seeders/DatabaseSeeder.php](database/seeders/DatabaseSeeder.php)

---

## ✨ Features Implementadas

| Feature                   | Status | Descrição                               |
| ------------------------- | ------ | --------------------------------------- |
| Vinculação User ↔ Teacher | ✅     | Cada professor tem uma conta de usuário |
| Sistema de Roles          | ✅     | Teacher, Coordinator, Admin             |
| Autenticação Filament     | ✅     | Apenas usuários com roles podem acessar |
| Autorização por Policy    | ✅     | Controle granular de acesso por recurso |
| Seeders de Teste          | ✅     | Usuários e roles pré-populados          |
| TeacherPolicy             | ✅     | Professores acessam apenas seu perfil   |
| GradePolicy               | ✅     | Professores lançam apenas suas notas    |
| ClassroomPolicy           | ✅     | Professores veem apenas suas turmas     |

---

## 🎯 Status Final

✅ **Autenticação de Professores:** Funcional
✅ **Autorização por Roles:** Funcional  
✅ **Políticas de Acesso:** Funcional
✅ **Banco de Dados:** Migrado e Populado

**Próxima etapa:** Implementar Resources Filament customizados e Dashboard do Professor
