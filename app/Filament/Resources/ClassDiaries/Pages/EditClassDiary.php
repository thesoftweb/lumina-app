<?php

namespace App\Filament\Resources\ClassDiaries\Pages;

use App\Filament\Resources\ClassDiaries\ClassDiaryResource;
use App\Models\ClassroomSubject;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditClassDiary extends EditRecord
{
    protected static string $resource = ClassDiaryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (Auth::user()->hasRole('teacher')) {
            $teacher = Auth::user()->teacher;
            abort_unless($teacher && ClassroomSubject::query()
                ->where('classroom_id', $data['classroom_id'])
                ->where('subject_id', $data['subject_id'])
                ->whereHas('teachers', fn ($query) => $query->whereKey($teacher->id))
                ->exists(), 403);
            $data['teacher_id'] = $teacher->id;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
