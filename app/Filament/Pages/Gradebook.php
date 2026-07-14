<?php

namespace App\Filament\Pages;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Term;
use App\Support\TeacherPanel;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class Gradebook extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Lançar notas';

    protected static ?string $title = 'Lançamento de notas';

    protected string $view = 'filament.pages.gradebook';

    public ?int $classroomSubjectId = null;

    public ?int $termId = null;

    public array $grades = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('teacher')
            && Auth::user()?->can('create_grade')
            && (bool) Auth::user()?->teacher;
    }

    public function getClassroomSubjects()
    {
        return TeacherPanel::classroomSubjects(Auth::user())->get();
    }

    public function getTerms()
    {
        return Term::query()->orderBy('year', 'desc')->orderBy('order')->get();
    }

    public function updatedClassroomSubjectId(): void
    {
        $this->loadStudents();
    }

    public function updatedTermId(): void
    {
        $this->loadStudents();
    }

    public function loadStudents(): void
    {
        $this->grades = [];

        if (! $this->classroomSubjectId || ! $this->termId) {
            return;
        }

        $assignment = TeacherPanel::ensureClassroomSubject(Auth::user(), $this->classroomSubjectId);

        Enrollment::query()
            ->with('student')
            ->where('classroom_id', $assignment->classroom_id)
            ->where('status', 'active')
            ->get()
            ->sortBy('student.name')
            ->each(function (Enrollment $enrollment): void {
                $grade = Grade::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('classroom_subject_id', $this->classroomSubjectId)
                    ->where('term_id', $this->termId)
                    ->first();

                $this->grades[$enrollment->id] = [
                    'name' => $enrollment->student->name,
                    'grade' => $grade?->grade,
                    'comments' => $grade?->comments ?? '',
                ];
            });
    }

    public function save(): void
    {
        $this->validate([
            'classroomSubjectId' => ['required', 'integer'],
            'termId' => ['required', 'exists:terms,id'],
            'grades.*.grade' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'grades.*.comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $teacher = TeacherPanel::teacher(Auth::user());
        $assignment = TeacherPanel::ensureClassroomSubject(Auth::user(), $this->classroomSubjectId);
        $validIds = Enrollment::where('classroom_id', $assignment->classroom_id)
            ->where('status', 'active')->pluck('id');

        DB::transaction(function () use ($teacher, $validIds): void {
            foreach ($this->grades as $enrollmentId => $state) {
                abort_unless($validIds->contains((int) $enrollmentId), 403);

                if ($state['grade'] === null || $state['grade'] === '') {
                    Grade::where([
                        'enrollment_id' => $enrollmentId,
                        'classroom_subject_id' => $this->classroomSubjectId,
                        'term_id' => $this->termId,
                        'teacher_id' => $teacher->id,
                    ])->delete();

                    continue;
                }

                Grade::updateOrCreate(
                    [
                        'enrollment_id' => $enrollmentId,
                        'classroom_subject_id' => $this->classroomSubjectId,
                        'term_id' => $this->termId,
                    ],
                    [
                        'teacher_id' => $teacher->id,
                        'grade' => $state['grade'],
                        'comments' => $state['comments'] ?: null,
                    ]
                );
            }
        });

        Notification::make()->title('Notas salvas')->success()->send();
    }
}
