<?php

namespace App\Filament\Resources\ClassDiaries\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Schemas\Schema;

class ClassDiaryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Aula')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('classroom.name')
                            ->label('Turma'),
                        TextEntry::make('teacher.name')
                            ->label('Professor'),
                        TextEntry::make('subject.name')
                            ->label('Disciplina'),
                        TextEntry::make('date')
                            ->label('Data')
                            ->dateTime('d/m/Y'),
                    ]),

                Section::make('Conteúdo Ministrado')
                    ->schema([
                        TextEntry::make('content')
                            ->html(),
                    ]),

                Section::make('Atividades e Tarefas')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('activities')
                            ->label('Atividades Realizadas'),
                        TextEntry::make('homework')
                            ->label('Tarefas de Casa'),
                    ]),

                TextEntry::make('observations')
                    ->label('Observações'),
            ]);
    }
}
