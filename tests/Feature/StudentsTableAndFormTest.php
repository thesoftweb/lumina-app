<?php

namespace Tests\Feature;

use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\StudentResource;
use App\Models\City;
use App\Models\Classroom;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\State;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentsTableAndFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    private function student(string $name, string $birthday): Student
    {
        $customer = Customer::create([
            'name' => 'Responsável de '.$name,
            'document' => str_pad((string) Customer::query()->count() + 1, 11, '0', STR_PAD_LEFT),
        ]);

        return Student::create([
            'name' => $name,
            'date_of_birth' => $birthday,
            'gender' => 'F',
            'customer_id' => $customer->id,
            'affiliation_1' => 'Mãe de '.$name,
            'phone_primary' => '(11) 99999-9999',
            'degree_of_kinship' => 'mãe',
        ]);
    }

    public function test_row_and_action_open_edit_without_view_button(): void
    {
        $student = $this->student('Ana', '2015-03-10');

        $page = Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$student])
            ->assertTableActionExists('edit')
            ->assertTableActionDoesNotExist('view');

        $this->assertSame(StudentResource::getUrl('edit', ['record' => $student]), $page->instance()->getTable()->getRecordUrl($student));
        $page->searchTable('Mãe de Ana')->assertCanSeeTableRecords([$student]);
    }

    public function test_filters_find_classroom_status_unenrolled_students_and_birth_month(): void
    {
        $ana = $this->student('Ana', '2015-03-10');
        $bia = $this->student('Bia', '2015-04-10');
        $semMatricula = $this->student('Clara', '2015-05-10');
        $level = Level::create(['name' => 'Fundamental']);
        $turma = Classroom::create(['name' => 'Turma A', 'level_id' => $level->id]);
        $outraTurma = Classroom::create(['name' => 'Turma B', 'level_id' => $level->id]);
        Enrollment::create(['student_id' => $ana->id, 'classroom_id' => $turma->id, 'enrollment_date' => '2026-02-01', 'status' => 'active']);
        Enrollment::create(['student_id' => $bia->id, 'classroom_id' => $outraTurma->id, 'enrollment_date' => '2026-02-01', 'status' => 'canceled']);

        Livewire::test(ListStudents::class)->filterTable('classroom_id', $turma->id)
            ->assertCanSeeTableRecords([$ana])->assertCanNotSeeTableRecords([$bia, $semMatricula]);
        Livewire::test(ListStudents::class)->filterTable('enrollment_status', 'canceled')
            ->assertCanSeeTableRecords([$bia])->assertCanNotSeeTableRecords([$ana, $semMatricula]);
        Livewire::test(ListStudents::class)->filterTable('has_enrollments', false)
            ->assertCanSeeTableRecords([$semMatricula])->assertCanNotSeeTableRecords([$ana, $bia]);
        Livewire::test(ListStudents::class)->filterTable('birth_month', 3)
            ->assertCanSeeTableRecords([$ana])->assertCanNotSeeTableRecords([$bia, $semMatricula]);
    }

    public function test_edit_saves_fields_across_tabs(): void
    {
        $student = $this->student('Ana', '2015-03-10');
        $state = new State;
        $state->code = 35;
        $state->name = 'São Paulo';
        $state->abbreviation = 'SP';
        $state->save();
        $city = new City;
        $city->code = 3550308;
        $city->name = 'São Paulo';
        $city->state_id = $state->id;
        $city->save();

        Livewire::test(EditStudent::class, ['record' => $student->id])
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('customer_id')
            ->assertFormFieldExists('observations')
            ->set('data.state_of_birth', 35)
            ->assertFormFieldExists('city_of_birth', fn ($field): bool => array_key_exists('São Paulo', $field->getOptions()))
            ->fillForm([
                'name' => 'Ana Maria', 'state_of_birth' => 35, 'city_of_birth' => 'São Paulo',
                'affiliation_1' => 'Maria', 'observations' => 'Acompanhamento pedagógico',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $student->refresh();
        $this->assertSame('Ana Maria', $student->name);
        $this->assertSame('Maria', $student->affiliation_1);
        $this->assertSame('Acompanhamento pedagógico', $student->observations);
    }

    public function test_classroom_and_status_filters_follow_the_latest_enrollment(): void
    {
        $student = $this->student('Ana', '2015-03-10');
        $level = Level::create(['name' => 'Fundamental']);
        $oldClassroom = Classroom::create(['name' => 'Turma antiga', 'level_id' => $level->id]);
        $newClassroom = Classroom::create(['name' => 'Turma atual', 'level_id' => $level->id]);
        Enrollment::create(['student_id' => $student->id, 'classroom_id' => $oldClassroom->id, 'enrollment_date' => '2025-02-01', 'status' => 'completed']);
        Enrollment::create(['student_id' => $student->id, 'classroom_id' => $newClassroom->id, 'enrollment_date' => '2026-02-01', 'status' => 'active']);

        Livewire::test(ListStudents::class)->filterTable('classroom_id', $oldClassroom->id)
            ->assertCanNotSeeTableRecords([$student]);
        Livewire::test(ListStudents::class)->filterTable('classroom_id', $newClassroom->id)
            ->assertCanSeeTableRecords([$student]);
        Livewire::test(ListStudents::class)->filterTable('enrollment_status', 'completed')
            ->assertCanNotSeeTableRecords([$student]);
    }

    public function test_teacher_keeps_read_only_access_to_assigned_students(): void
    {
        $student = $this->student('Ana', '2015-03-10');
        $level = Level::create(['name' => 'Fundamental']);
        $classroom = Classroom::create(['name' => 'Turma A', 'level_id' => $level->id]);
        Enrollment::create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'enrollment_date' => '2026-02-01', 'status' => 'active']);
        $user = User::factory()->create();
        $user->assignRole('teacher');
        $teacher = Teacher::create(['name' => 'Professor', 'email' => 'professor@example.test', 'user_id' => $user->id]);
        $classroom->teachers()->attach($teacher);
        $this->actingAs($user);

        $page = Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$student])
            ->assertTableActionHidden('edit', $student)
            ->assertTableActionDoesNotExist('view');

        $this->assertSame(StudentResource::getUrl('view', ['record' => $student]), $page->instance()->getTable()->getRecordUrl($student));
    }
}
