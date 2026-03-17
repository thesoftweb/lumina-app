<?php

namespace App\Filament\Resources\Classrooms\Schemas;

use Filament\Schemas\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClassroomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Classroom Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Informações da Turma')
                            ->schema([
                                Section::make()
                                    ->heading('Dados da Turma')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Nome da Turma'),
                                                TextEntry::make('level.name')
                                                    ->label('Nível'),
                                                TextEntry::make('teachers_count')
                                                    ->label('Quantidade de Professores')
                                                    ->counts('teachers'),
                                                TextEntry::make('enrollments_count')
                                                    ->label('Quantidade de Alunos')
                                                    ->counts('enrollments'),
                                                TextEntry::make('whatsapp_group')
                                                    ->label('Grupo WhatsApp')
                                                    ->url(fn($state) => $state)
                                                    ->openUrlInNewTab(),
                                                TextEntry::make('created_at')
                                                    ->label('Criada em')
                                                    ->dateTime(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Alunos')
                            ->schema([
                                Section::make()
                                    ->heading('Lista de Alunos Matriculados')
                                    ->schema([
                                        RepeatableEntry::make('enrollments')
                                            ->label('Alunos')
                                            // ->columnSpanFull()
                                            ->columns(4)
                                            ->schema([
                                                TextEntry::make('student.name')
                                                    ->label('Nome Completo')
                                                    ->columnSpanFull()
                                                    ->weight('bold'),
                                                TextEntry::make('student.date_of_birth')
                                                    ->label('Data de Nascimento')
                                                    ->date('d/m/Y'),
                                                TextEntry::make('student.customer.name')
                                                    ->label('Responsável')
                                                    ->columnSpan(2),
                                                TextEntry::make('student.affiliation_1')
                                                    ->label('Pai/Mãe 1')
                                                    ->columnSpanFull(),
                                                TextEntry::make('student.affiliation_2')
                                                    ->label('Pai/Mãe 2')
                                                    ->columnSpanFull(),
                                                TextEntry::make('student.customer.phone')
                                                    ->label('Telefone Responsável'),
                                                TextEntry::make('enrollment_date')
                                                    ->label('Data da Matrícula')
                                                    ->date('d/m/Y'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
