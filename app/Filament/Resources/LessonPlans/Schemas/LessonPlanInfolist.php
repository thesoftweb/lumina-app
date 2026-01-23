<?php

namespace App\Filament\Resources\LessonPlans\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Badge;
use Filament\Schemas\Schema;

class LessonPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações Básicas')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Título'),

                        TextEntry::make('classroom.name')
                            ->label('Turma'),

                        TextEntry::make('subject.name')
                            ->label('Disciplina'),

                        TextEntry::make('teacher.name')
                            ->label('Professor'),

                        TextEntry::make('term.name')
                            ->label('Período'),

                        TextEntry::make('scheduled_date')
                            ->dateTime('d/m/Y')
                            ->label('Data Agendada'),

                        TextEntry::make('duration_minutes')
                            ->label('Duração')
                            ->formatStateUsing(fn($state) => $state ? $state . ' min' : '-'),

                        TextEntry::make('status')
                            ->badge()
                            ->label('Status')
                            ->formatStateUsing(fn($state) => match ($state) {
                                'draft' => 'Rascunho',
                                'scheduled' => 'Agendado',
                                'completed' => 'Concluído',
                                'cancelled' => 'Cancelado',
                                default => $state,
                            })
                            ->color(fn($state) => match ($state) {
                                'draft' => 'gray',
                                'scheduled' => 'blue',
                                'completed' => 'green',
                                'cancelled' => 'red',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Descrição')
                    ->schema([
                        TextEntry::make('description')
                            ->html(),
                    ]),

                Section::make('Objetivos')
                    ->schema([
                        TextEntry::make('objectives')
                            ->html(),
                    ]),

                Section::make('Metodologia e Recursos')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('methodology')
                            ->html()
                            ->label('Metodologia'),

                        TextEntry::make('resources')
                            ->label('Recursos'),
                    ]),
            ]);
    }
}
