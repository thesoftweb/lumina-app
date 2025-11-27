<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;


class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')->label('Descrição'),
            	TextInput::make('year')->label('Ano'),
        		DatePicker::make('start_at')->label('Início do Período'),
        		DatePicker::make('end_at')->label('Final do Período'),
            ]);
    }
}
