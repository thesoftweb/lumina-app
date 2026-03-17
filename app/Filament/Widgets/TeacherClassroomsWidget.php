<?php

namespace App\Filament\Widgets;

use App\Models\Classroom;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherClassroomsWidget extends Widget
{
    protected string $view = 'filament.widgets.teacher-classrooms-widget';

    public function getClassrooms()
    {
        $user = Auth::user();

        if (!$user->hasRole('teacher') || !$user->teacher) {
            return collect();
        }

        return $user->teacher->classrooms()->with('level')->get();
    }
}
