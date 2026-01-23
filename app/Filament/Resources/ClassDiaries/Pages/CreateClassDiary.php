<?php

namespace App\Filament\Resources\ClassDiaries\Pages;

use App\Filament\Resources\ClassDiaries\ClassDiaryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateClassDiary extends CreateRecord
{
    protected static string $resource = ClassDiaryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If user is a teacher, automatically set teacher_id
        if (Auth::user()->hasRole('teacher')) {
            $teacher = Auth::user()->teacher;
            if ($teacher) {
                $data['teacher_id'] = $teacher->id;
            }
        }

        return $data;
    }
}
