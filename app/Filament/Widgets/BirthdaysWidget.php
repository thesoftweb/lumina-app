<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class BirthdaysWidget extends Widget
{
    protected string $view = 'filament.widgets.birthdays-widget';

    public function getStudents()
    {
        $teacherId = Auth::user()->teacher?->id;

        return Student::query()
            ->whereMonth('date_of_birth', now()->month)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('classroom.teachers', fn ($query) => $query->whereKey($teacherId ?? 0)))
            ->with(['enrollments' => fn ($query) => $query->where('status', 'active')->with('classroom')])
            ->get()
            ->sortBy(fn (Student $student) => $student->date_of_birth->day)
            ->values();
    }
}
