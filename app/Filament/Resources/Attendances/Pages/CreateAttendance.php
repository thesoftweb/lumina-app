<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

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
