<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Enrollment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TeacherStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $teacher = Auth::user()->teacher;
        $classroomIds = $teacher->classrooms()->pluck('classrooms.id');
        $students = Enrollment::whereIn('classroom_id', $classroomIds)->where('status', 'active')->distinct('student_id')->count('student_id');
        $attendance = Attendance::where('teacher_id', $teacher->id)->whereMonth('date', now()->month)->get();
        $frequency = $attendance->count() ? round($attendance->where('present', true)->count() / $attendance->count() * 100, 1) : null;

        return [
            Stat::make('Minhas turmas', $classroomIds->count()),
            Stat::make('Meus alunos', $students),
            Stat::make('Frequência no mês', $frequency !== null ? $frequency.'%' : '-'),
        ];
    }
}
