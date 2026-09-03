<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\EnrollmentsCountWidget;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_only_registers_the_academic_overview_widget(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertSame([
            EnrollmentsCountWidget::class,
        ], (new Dashboard)->getWidgets());
    }

    public function test_academic_overview_contains_only_the_requested_counts(): void
    {
        $birthdayStudent = $this->createStudent('Aniversariante', now()->subYears(10)->toDateString(), '10000000001');
        $otherStudent = $this->createStudent('Outro aluno', now()->subMonth()->subYears(10)->toDateString(), '10000000002');

        Enrollment::create([
            'student_id' => $birthdayStudent->id,
            'classroom_id' => 1,
            'enrollment_date' => now(),
            'status' => 'active',
            'new_student' => true,
        ]);
        Enrollment::create([
            'student_id' => $otherStudent->id,
            'classroom_id' => 1,
            'enrollment_date' => now(),
            'status' => 'active',
            'new_student' => false,
        ]);
        Enrollment::create([
            'student_id' => $otherStudent->id,
            'classroom_id' => 1,
            'enrollment_date' => now()->subYear(),
            'status' => 'canceled',
            'new_student' => false,
        ]);

        $widget = new class extends EnrollmentsCountWidget
        {
            public function stats(): array
            {
                return $this->getStats();
            }
        };

        $stats = collect($widget->stats())
            ->mapWithKeys(fn ($stat) => [(string) $stat->getLabel() => $stat->getValue()])
            ->all();

        $this->assertSame([
            'Total de matrículas' => 3,
            'Matrículas ativas' => 2,
            'Novatos' => 1,
            'Aniversariantes' => 1,
        ], $stats);
    }

    private function createStudent(string $name, string $dateOfBirth, string $document): Student
    {
        $customer = Customer::create([
            'name' => "Responsável por {$name}",
            'document' => $document,
        ]);

        return Student::create([
            'customer_id' => $customer->id,
            'name' => $name,
            'date_of_birth' => $dateOfBirth,
            'gender' => 'M',
        ]);
    }
}
