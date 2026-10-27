<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\AccountPlan;
use App\Models\AdmissionOffer;
use App\Models\PreEnrollment;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\ClassDiary;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\IssuedDocument;
use App\Models\LessonPlan;
use App\Models\Plan;
use App\Models\Student;
use App\Models\StudentPickupAuthorization;
use App\Models\Supplier;
use App\Models\Teacher;
use App\Models\Level;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TranscriptYear;
use App\Models\User;
use App\Observers\EnrollmentObserver;
use App\Policies\AdminOnlyPolicy;
use App\Policies\AdministrativePolicy;
use App\Policies\AttendancePolicy;
use App\Policies\ClassDiaryPolicy;
use App\Policies\ClassroomPolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\EventPolicy;
use App\Policies\GradePolicy;
use App\Policies\IssuedDocumentPolicy;
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
        IssuedDocument::class => IssuedDocumentPolicy::class,
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
            AdmissionOffer::class,
            PreEnrollment::class,
            Schedule::class,
            ClassroomSubject::class,
            Customer::class,
            Invoice::class,
            StudentPickupAuthorization::class,
            Subject::class,
            Term::class,
            TranscriptYear::class,
            Level::class,
        ] as $model) {
            Gate::policy($model, AdministrativePolicy::class);
        }

        foreach ([
            AccountPlan::class,
            Company::class,
            Plan::class,
            Supplier::class,
            User::class,
        ] as $model) {
            Gate::policy($model, AdminOnlyPolicy::class);
        }
    }
}
