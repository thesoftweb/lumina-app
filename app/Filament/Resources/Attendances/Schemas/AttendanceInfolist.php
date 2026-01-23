<?php

namespace App\Filament\Resources\Attendances\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Badge;
use Filament\Schemas\Schema;

class AttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Frequência')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('date')
                            ->dateTime('d/m/Y')
                            ->label('Data'),

                        TextEntry::make('classroom.name')
                            ->label('Turma'),

                        TextEntry::make('enrollment.student.name')
                            ->label('Aluno'),

                        TextEntry::make('teacher.name')
                            ->label('Professor'),

                        Badge::make('present')
                            ->label('Presença')
                            ->formatStateUsing(fn($state) => $state ? 'Presente' : 'Ausente')
                            ->color(fn($state) => $state ? 'green' : 'red'),

                        Badge::make('justified')
                            ->label('Justificativa')
                            ->formatStateUsing(fn($state) => $state ? 'Sim' : 'Não')
                            ->color(fn($state) => $state ? 'blue' : 'gray'),
                    ]),

                TextEntry::make('justification')
                    ->label('Motivo da Ausência')
                    ->columnSpanFull(),
            ]);
    }
}
