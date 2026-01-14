<?php

namespace App\Filament\Resources\ClassDiaries\Pages;

use App\Filament\Resources\ClassDiaries\ClassDiaryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClassDiary extends ViewRecord
{
    protected static string $resource = ClassDiaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
