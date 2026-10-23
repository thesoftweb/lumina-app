<?php

namespace App\Filament\Resources\IssuedDocuments\Pages;

use App\Filament\Resources\IssuedDocuments\IssuedDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListIssuedDocuments extends ListRecords
{
    protected static string $resource = IssuedDocumentResource::class;

    public function getSubheading(): ?string
    {
        return 'Declarações e recibos emitidos. Para emitir, use a matrícula do aluno.';
    }
}
