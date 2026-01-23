<?php

namespace App\Filament\Resources\ClassDiaries\Pages;

use App\Filament\Resources\ClassDiaries\ClassDiaryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditClassDiary extends EditRecord
{
    protected static string $resource = ClassDiaryResource::class;

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
