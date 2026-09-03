<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados básicos')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nome completo'),
                        TextEntry::make('date_of_birth')->label('Data de nascimento')->date('d/m/Y'),
                        TextEntry::make('affiliation_1')->label('Filiação 1')->placeholder('-'),
                        TextEntry::make('affiliation_2')->label('Filiação 2')->placeholder('-'),
                        TextEntry::make('enrollments.classroom.name')->label('Turmas')->badge(),
                        TextEntry::make('observations')->label('Observações')->placeholder('-')->columnSpanFull(),
                    ]),
            ]);
    }
}
