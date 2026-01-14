<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassDiary;
use App\Models\Grade;
use App\Models\LessonPlan;
use App\Models\Teacher;
use App\Policies\AttendancePolicy;
use App\Policies\ClassDiaryPolicy;
use App\Policies\ClassroomPolicy;
use App\Policies\GradePolicy;
use App\Policies\LessonPlanPolicy;
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
        //
    }
}
