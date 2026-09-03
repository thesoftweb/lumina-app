<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\AccountPlan;
use App\Models\Agenda;
use App\Models\Attendance;
use App\Models\ClassDiary;
use App\Models\Classroom;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\LessonPlan;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Supplier;
use App\Models\Teacher;
use App\Models\User;
use App\Observers\EnrollmentObserver;
use App\Policies\AdministrativePolicy;
use App\Policies\AttendancePolicy;
use App\Policies\ClassDiaryPolicy;
use App\Policies\ClassroomPolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\EventPolicy;
use App\Policies\GradePolicy;
use App\Policies\LessonPlanPolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Teacher::class => TeacherPolicy::class,
        Grade::class => GradePolicy::class,
        Classroom::class => ClassroomPolicy::class,
        Attendance::class => AttendancePolicy::class,
        ClassDiary::class => ClassDiaryPolicy::class,
        LessonPlan::class => LessonPlanPolicy::class,
        Enrollment::class => EnrollmentPolicy::class,
        Event::class => EventPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        TextInput::configureUsing(function (TextInput $component): void {
            $component->trim();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Enrollment::observe(EnrollmentObserver::class);

        Gate::policy(Student::class, StudentPolicy::class);

        foreach ([
            AcademicYear::class,
            AccountPlan::class,
            Agenda::class,
            Company::class,
            Customer::class,
            Document::class,
            DocumentTemplate::class,
            Invoice::class,
            Plan::class,
            Supplier::class,
            User::class,
        ] as $model) {
            Gate::policy($model, AdministrativePolicy::class);
        }
    }
}
