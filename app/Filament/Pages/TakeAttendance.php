<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Support\TeacherPanel;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class TakeAttendance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Fazer chamada';

    protected static ?string $title = 'Fazer chamada';

    protected string $view = 'filament.pages.take-attendance';

    public ?int $classroomId = null;

    public string $date = '';

    public array $attendance = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('teacher')
            && Auth::user()?->can('create_attendance')
            && (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function updatedClassroomId(): void
    {
        $this->loadStudents();
    }

    public function updatedDate(): void
    {
        $this->loadStudents();
    }

    public function getClassrooms()
    {
        return TeacherPanel::classrooms(Auth::user());
    }

    public function loadStudents(): void
    {
        $this->attendance = [];

        if (! $this->classroomId || ! $this->date) {
            return;
        }

        TeacherPanel::ensureClassroom(Auth::user(), $this->classroomId);

        Enrollment::query()
            ->with('student')
            ->where('classroom_id', $this->classroomId)
            ->where('status', 'active')
            ->get()
            ->sortBy('student.name')
            ->each(function (Enrollment $enrollment): void {
                $record = Attendance::withTrashed()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('classroom_id', $this->classroomId)
                    ->whereDate('date', $this->date)
                    ->first();

                $this->attendance[$enrollment->id] = [
                    'name' => $enrollment->student->name,
                    'present' => $record?->present ?? true,
                    'justified' => $record?->justified ?? false,
                    'justification' => $record?->justification ?? '',
                ];
            });
    }

    public function save(): void
    {
        $this->validate([
            'classroomId' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'attendance.*.present' => ['boolean'],
            'attendance.*.justified' => ['boolean'],
            'attendance.*.justification' => ['nullable', 'string', 'max:1000'],
        ]);

        $teacher = TeacherPanel::teacher(Auth::user());
        TeacherPanel::ensureClassroom(Auth::user(), $this->classroomId);
        $validIds = Enrollment::where('classroom_id', $this->classroomId)
            ->where('status', 'active')->pluck('id');

        DB::transaction(function () use ($teacher, $validIds): void {
            foreach ($this->attendance as $enrollmentId => $state) {
                abort_unless($validIds->contains((int) $enrollmentId), 403);
                $record = Attendance::withTrashed()->firstOrNew([
                    'enrollment_id' => $enrollmentId,
                    'classroom_id' => $this->classroomId,
                    'date' => $this->date,
                ]);
                $present = (bool) ($state['present'] ?? false);
                $justified = ! $present && (bool) ($state['justified'] ?? false);
                $record->fill([
                    'teacher_id' => $teacher->id,
                    'present' => $present,
                    'justified' => $justified,
                    'justification' => $justified ? ($state['justification'] ?? null) : null,
                ]);
                $record->deleted_at = null;
                $record->save();
            }
        });

        Notification::make()->title('Frequência registrada')->success()->send();
    }
}
