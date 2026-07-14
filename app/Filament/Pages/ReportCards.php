<?php

namespace App\Filament\Pages;

use App\Models\Enrollment;
use App\Services\ReportCardService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ReportCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Boletins';

    protected static ?string $title = 'Boletins';

    protected string $view = 'filament.pages.report-cards';

    public ?int $enrollmentId = null;

    public ?array $report = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->hasAnyRole(['coordinator', 'admin'])
            || ($user?->hasRole('teacher') && $user->can('view_report_cards') && (bool) $user->teacher);
    }

    public function getEnrollments()
    {
        $query = Enrollment::query()->with(['student', 'classroom'])->where('status', 'active');

        if (Auth::user()->hasRole('teacher')) {
            $teacherId = Auth::user()->teacher?->id;
            $query->whereHas('classroom.teachers', fn ($query) => $query->whereKey($teacherId ?? 0));
        }

        return $query->get()->sortBy('student.name');
    }

    public function updatedEnrollmentId(ReportCardService $service): void
    {
        $this->report = null;

        if (! $this->enrollmentId) {
            return;
        }

        $enrollment = Enrollment::with('student')->findOrFail($this->enrollmentId);
        abort_unless(Auth::user()->can('view', $enrollment->student), 403);
        $this->report = $service->forEnrollment($enrollment);
    }
}
