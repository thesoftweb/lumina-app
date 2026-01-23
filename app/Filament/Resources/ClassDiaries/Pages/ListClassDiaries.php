<?php

namespace App\Filament\Resources\ClassDiaries\Pages;

use App\Filament\Resources\ClassDiaries\ClassDiaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClassDiaries extends ListRecords
{
    protected static string $resource = ClassDiaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
