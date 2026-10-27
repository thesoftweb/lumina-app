<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentStatus;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\PreEnrollments\Pages\ViewPreEnrollment;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Plan;
use App\Models\PreEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Admissions\PreEnrollmentConverter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Desconto do plano: valor fixo, percentual ou nenhum, configurado no plano e só aplicado na matrícula em que
 * for negociado (rematrícula). Novato não tem desconto. Relatório de testes, falha 1.
 */
class PlanDiscountNegotiationTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year2026;

    private AcademicYear $year2027;

    private Level $level;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(10, 0));
        $this->seed(RolePermissionSeeder::class);

        $this->year2026 = AcademicYear::create(['description' => 'Ano letivo 2026', 'year' => 2026, 'is_default' => true]);
        $this->year2027 = AcademicYear::create(['description' => 'Ano letivo 2027', 'year' => 2027, 'is_default' => false]);
        $this->level = Level::create(['name' => 'Infantil']);
    }

    public function test_admin_saves_a_plan_without_discount_from_the_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(CreatePlan::class)
            ->fillForm([
                'name' => 'MENSALIDADE INFANTIL',
                'academic_year_id' => $this->year2027->id,
                'base_amount' => 235,
                'discount_type' => 'none',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::query()->sole();
        $this->assertSame('none', $plan->discount_type);
        $this->assertEquals(0, $plan->discount_value);
        $this->assertFalse($plan->has_discount);
        $this->assertEquals(235, $plan->final_value);
    }

    public function test_plan_discount_is_normalized_on_save(): void
    {
        $percentage = $this->plan(200, 'percentage', 10);
        $this->assertTrue($percentage->has_discount);
        $this->assertEquals(180, $percentage->final_value);

        $fixed = $this->plan(200, 'fixed', 30);
        $this->assertTrue($fixed->has_discount);
        $this->assertEquals(170, $fixed->final_value);

        $none = Plan::create(['name' => 'Sem', 'base_amount' => 200, 'discount_type' => 'none', 'discount_value' => 50, 'is_active' => true]);
        $this->assertFalse($none->has_discount);
        $this->assertEquals(0, $none->discount_value);
        $this->assertEquals(200, $none->final_value);

        // Plano antigo gravado como "Fixo" com valor 0 (ex.: BALLET) vira "Nenhum" em vez de travar a edição
        $zero = $this->plan(30, 'fixed', 0);
        $this->assertSame('none', $zero->discount_type);
        $this->assertFalse($zero->has_discount);
    }

    public function test_invalid_plan_discounts_are_rejected(): void
    {
        foreach ([['percentage', 120], ['fixed', 250]] as [$type, $value]) {
            try {
                $this->plan(200, $type, $value);
                $this->fail("Desconto {$type} de {$value} deveria ser recusado.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('discount_value', $exception->errors());
            }
        }

        $this->assertSame(0, Plan::query()->count());
    }

    public function test_plan_discount_only_applies_to_the_enrollment_that_negotiated_it(): void
    {
        $plan = $this->plan(200, 'percentage', 10);
        $classroom = $this->classroom($this->year2026, $plan);
        [$student] = $this->student('Ana', '529.982.247-25');

        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $this->year2026->id,
            'plan_id' => $plan->id, 'enrollment_date' => '2026-02-01', 'status' => EnrollmentStatus::Active->value,
        ]);

        $this->assertEqualsWithDelta(200.0, $enrollment->tuitionBreakdown()['final_amount'], 0.001, 'sem negociação, vale o valor base');
        $this->assertNull($enrollment->tuitionDiscountLabel());

        $enrollment->update(['apply_plan_discount' => true]);

        $this->assertEqualsWithDelta(180.0, $enrollment->fresh()->tuitionBreakdown()['final_amount'], 0.001);
        $this->assertSame('10% (do plano)', $enrollment->fresh()->tuitionDiscountLabel());
    }

    public function test_enrollment_rejects_plan_discount_for_newcomers_and_both_discounts_together(): void
    {
        $plan = $this->plan(200, 'fixed', 20);
        $classroom = $this->classroom($this->year2026, $plan);
        [$student] = $this->student('Ana', '529.982.247-25');

        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $this->year2026->id,
            'plan_id' => $plan->id, 'enrollment_date' => '2026-02-01', 'status' => EnrollmentStatus::Active->value, 'new_student' => true,
        ]);

        $this->assertValidationError(fn () => $enrollment->update(['apply_plan_discount' => true]), 'use_custom_discount', Enrollment::NEW_STUDENT_DISCOUNT_MESSAGE);

        $enrollment->refresh();
        $this->assertValidationError(fn () => $enrollment->update([
            'new_student' => false, 'apply_plan_discount' => true, 'use_custom_discount' => true, 'discount_type' => 'fixed', 'discount_value' => 10,
        ]), 'apply_plan_discount', Enrollment::DISCOUNT_CHOICE_MESSAGE);
    }

    public function test_reenrollment_conversion_applies_the_plan_discount_from_the_modal(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole('secretary');
        $this->actingAs($secretary);

        $plan2027 = $this->plan(300, 'percentage', 10, $this->year2027);
        $classroom2027 = $this->classroom($this->year2027, $plan2027);
        $this->returningStudent();
        $lead = $this->lead();

        Livewire::test(ViewPreEnrollment::class, ['record' => $lead->id])
            ->mountAction('convert')
            ->setActionData([
                'classroom_id' => $classroom2027->id, 'plan_id' => $plan2027->id, 'day_of_payment' => 10,
                'enrollment_date' => '2026-10-27', 'status' => EnrollmentStatus::Reserved->value, 'discount_mode' => 'plan',
            ])
            ->assertMountedActionModalSee('Desconto do plano (10%)')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $enrollment = Enrollment::query()->where('academic_year_id', $this->year2027->id)->sole();
        $this->assertTrue($enrollment->apply_plan_discount);
        $this->assertFalse($enrollment->use_custom_discount);
        $this->assertEqualsWithDelta(270.0, $enrollment->tuitionBreakdown()['final_amount'], 0.001);
        $this->assertSame(PreEnrollmentStatus::Converted, $lead->fresh()->status);
    }

    public function test_conversion_rejects_plan_discount_for_newcomers_and_plans_without_discount(): void
    {
        $discounted = $this->plan(300, 'fixed', 30, $this->year2027);
        $plain = $this->plan(300, 'none', 0, $this->year2027);
        $classroom = $this->classroom($this->year2027, $discounted);
        $classroom->plans()->attach($plain);
        $converter = app(PreEnrollmentConverter::class);
        $base = ['classroom_id' => $classroom->id, 'day_of_payment' => 10, 'apply_plan_discount' => true];

        // Novato: nem o desconto do plano
        $this->assertValidationError(fn () => $converter->convert($this->lead(), [...$base, 'plan_id' => $discounted->id]), 'use_custom_discount', Enrollment::NEW_STUDENT_DISCOUNT_MESSAGE);

        // Veterano com plano sem desconto configurado
        $this->returningStudent('390.533.447-05', 'Beto');
        $lead = $this->lead(['student_name' => 'Beto', 'guardian_document' => '390.533.447-05']);
        $this->assertValidationError(fn () => $converter->convert($lead, [...$base, 'plan_id' => $plain->id]), 'apply_plan_discount');

        $this->assertSame(0, Enrollment::query()->where('academic_year_id', $this->year2027->id)->count());
        $this->assertTrue($lead->fresh()->status->isOpen());
    }

    private function assertValidationError(callable $callback, string $key, ?string $message = null): void
    {
        try {
            $callback();
            $this->fail("Era esperado um erro de validação em {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());

            if ($message) {
                $this->assertContains($message, $exception->errors()[$key]);
            }
        }
    }

    private function plan(float $base, string $type, float $value, ?AcademicYear $year = null): Plan
    {
        return Plan::create([
            'academic_year_id' => $year?->id, 'name' => "Plano {$type} {$value}", 'base_amount' => $base,
            'discount_type' => $type, 'discount_value' => $value, 'is_active' => true,
        ]);
    }

    private function classroom(AcademicYear $year, Plan $plan): Classroom
    {
        $classroom = Classroom::create(['name' => 'Infantil '.$year->year.' '.uniqid(), 'academic_year_id' => $year->id, 'level_id' => $this->level->id]);
        $classroom->plans()->attach($plan);

        return $classroom;
    }

    /** @return array{0: Student, 1: Customer} */
    private function student(string $name, string $document): array
    {
        $customer = Customer::query()->firstOrCreate(['document' => $document], ['name' => "Responsável {$name}"]);

        return [Student::create(['name' => $name, 'customer_id' => $customer->id, 'date_of_birth' => '2021-05-20', 'gender' => 'F']), $customer];
    }

    /** Aluno com matrícula em 2026: veterano na ficha de 2027. */
    private function returningStudent(string $document = '529.982.247-25', string $name = 'Lucas Andrade'): void
    {
        [$student, $customer] = $this->student($name, $document);
        $plan = $this->plan(250, 'none', 0, $this->year2026);

        Enrollment::create([
            'student_id' => $student->id, 'customer_id' => $customer->id, 'classroom_id' => $this->classroom($this->year2026, $plan)->id,
            'academic_year_id' => $this->year2026->id, 'plan_id' => $plan->id, 'enrollment_date' => '2026-02-01', 'status' => EnrollmentStatus::Active->value,
        ]);
    }

    private function lead(array $attributes = []): PreEnrollment
    {
        return PreEnrollment::create([
            'academic_year_id' => $this->year2027->id, 'status' => PreEnrollmentStatus::New,
            'student_name' => 'Lucas Andrade', 'student_birth_date' => '2021-05-20',
            'guardian_name' => 'Carla Andrade', 'guardian_document' => '529.982.247-25', 'guardian_phone' => '(83) 99876-5432',
            ...$attributes,
        ]);
    }
}
