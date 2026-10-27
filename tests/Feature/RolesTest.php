<?php

namespace Tests\Feature;

use App\Filament\Pages\Gradebook;
use App\Filament\Pages\ReportCards;
use App\Filament\Pages\TakeAttendance;
use App\Models\AcademicYear;
use App\Models\AccountPlan;
use App\Models\Attendance;
use App\Models\ClassDiary;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\IssuedDocument;
use App\Models\LessonPlan;
use App\Models\Level;
use App\Models\Plan;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Supplier;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    /** Secretaria cadastra e edita, mas só o admin exclui. */
    private const OFFICE_MODELS = [
        Enrollment::class, Student::class, Customer::class, Invoice::class,
        Classroom::class, Teacher::class, AcademicYear::class, Term::class, Subject::class, Level::class,
        Event::class, Schedule::class,
    ];

    /** Registros lançados pelo professor: a secretaria só consulta (notas ela corrige, com motivo registrado). */
    private const PEDAGOGICAL_MODELS = [Attendance::class, ClassDiary::class, LessonPlan::class];

    /** Usuários, empresa, preços e contas a pagar: só a direção. */
    private const ADMIN_ONLY_MODELS = [User::class, Company::class, Plan::class, Supplier::class, AccountPlan::class];

    private function signInAs(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_seeder_defines_exactly_admin_secretary_and_teacher(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing(['admin', 'secretary', 'teacher'], Role::pluck('name')->all());
    }

    public function test_secretary_runs_the_school_office_without_admin_powers(): void
    {
        $user = $this->signInAs('secretary');

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        foreach (self::OFFICE_MODELS as $model) {
            $this->assertTrue($user->can('viewAny', $model), "{$model}: viewAny");
            $this->assertTrue($user->can('create', $model), "{$model}: create");
            $this->assertTrue($user->can('update', new $model), "{$model}: update");
            $this->assertFalse($user->can('delete', new $model), "{$model}: delete");
        }

        foreach (self::PEDAGOGICAL_MODELS as $model) {
            $this->assertTrue($user->can('viewAny', $model), "{$model}: viewAny");
            $this->assertTrue($user->can('view', new $model), "{$model}: view");
            $this->assertFalse($user->can('create', $model), "{$model}: create");
            $this->assertFalse($user->can('update', new $model), "{$model}: update");
        }

        foreach (self::ADMIN_ONLY_MODELS as $model) {
            $this->assertFalse($user->can('viewAny', $model), "{$model}: viewAny");
        }

        // Notas: o professor lança pelo diário; a secretaria lança o que faltou e corrige, mas não exclui
        $this->assertTrue($user->can('create', Grade::class));
        $this->assertTrue($user->can('update', new Grade));
        $this->assertFalse($user->can('delete', new Grade));

        // Documento emitido: a secretaria emite e reimprime; não se edita nem se exclui, e só o admin cancela
        $this->assertTrue($user->can('viewAny', IssuedDocument::class));
        $this->assertTrue($user->can('create', IssuedDocument::class));
        $this->assertFalse($user->can('update', new IssuedDocument));
        $this->assertFalse($user->can('cancel', new IssuedDocument));

        $this->assertTrue(ReportCards::canAccess());
        $this->assertFalse(TakeAttendance::canAccess());
        $this->assertFalse(Gradebook::canAccess());
    }

    public function test_admin_can_do_everything_including_deleting(): void
    {
        $user = $this->signInAs('admin');

        foreach ([...self::OFFICE_MODELS, ...self::PEDAGOGICAL_MODELS, ...self::ADMIN_ONLY_MODELS] as $model) {
            $this->assertTrue($user->can('viewAny', $model), "{$model}: viewAny");
            $this->assertTrue($user->can('delete', new $model), "{$model}: delete");
        }
    }

    public function test_teacher_stays_out_of_office_and_admin_areas(): void
    {
        $user = $this->signInAs('teacher');

        foreach ([Enrollment::class, Customer::class, Invoice::class, IssuedDocument::class, AcademicYear::class, ...self::ADMIN_ONLY_MODELS] as $model) {
            $this->assertFalse($user->can('viewAny', $model), "{$model}: viewAny");
        }

        foreach (self::PEDAGOGICAL_MODELS as $model) {
            $this->assertTrue($user->can('create', $model), "{$model}: create");
        }
    }

    public function test_migration_turns_existing_coordinators_into_secretaries(): void
    {
        $coordinator = Role::create(['name' => 'coordinator', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($coordinator);

        (require database_path('migrations/2026_10_22_000001_replace_coordinator_role_with_secretary.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->fresh()->hasRole('secretary'));
        $this->assertDatabaseMissing('roles', ['name' => 'coordinator']);
    }
}
