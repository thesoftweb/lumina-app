<?php

namespace App\Filament\Resources\LessonPlans\Pages;

use App\Filament\Resources\LessonPlans\LessonPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLessonPlan extends ViewRecord
{
    protected static string $resource = LessonPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
