<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;

class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ano Escolar')
                    ->description('Configure o ano escolar, definindo datas de início e término, além de períodos importantes como matrículas e férias.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('description')->label('Descrição'),
                        TextInput::make('year')->label('Ano'),
                        DatePicker::make('start_at')->label('Início do Período'),
                        DatePicker::make('end_at')->label('Final do Período'),
                    ]),
            ]);
    }
}
