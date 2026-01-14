<?php

namespace App\Filament\Resources\LessonPlans\Pages;

use App\Filament\Resources\LessonPlans\LessonPlanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateLessonPlan extends CreateRecord
{
    protected static string $resource = LessonPlanResource::class;

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
