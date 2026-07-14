<?php

namespace App\Filament\Pages;

use App\Models\Student;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Birthdays extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Aniversariantes';

    protected static ?string $title = 'Aniversariantes';

    protected string $view = 'filament.pages.birthdays';

    public int $month;

    public function mount(): void
    {
        $this->month = now()->month;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('teacher')
            && Auth::user()?->can('view_birthdays')
            && (bool) Auth::user()?->teacher;
    }

    public function getStudents()
    {
        $teacherId = Auth::user()->teacher?->id;

        return Student::query()
            ->whereMonth('date_of_birth', $this->month)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('classroom.teachers', fn ($query) => $query->whereKey($teacherId ?? 0)))
            ->with(['enrollments' => fn ($query) => $query->where('status', 'active')->with('classroom')])
            ->get()
            ->sortBy(fn (Student $student) => $student->date_of_birth->day)
            ->values();
    }
}
