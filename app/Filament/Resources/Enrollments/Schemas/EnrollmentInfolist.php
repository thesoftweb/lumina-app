<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EnrollmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Enrollment Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Matrícula')
                            ->schema([
                                Section::make()
                                    ->heading('Informações da Matrícula')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('id')
                                                    ->label('ID Matrícula'),
                                                TextEntry::make('enrollment_date')
                                                    ->label('Data da Matrícula')
                                                    ->date(),
                                                TextEntry::make('status')
                                                    ->label('Status'),
                                                TextEntry::make('day_of_payment')
                                                    ->label('Dia de Pagamento'),
                                                TextEntry::make('created_at')
                                                    ->label('Criado em')
                                                    ->dateTime(),
                                                TextEntry::make('updated_at')
                                                    ->label('Atualizado em')
                                                    ->dateTime(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Aluno')
                            ->schema([
                                Section::make()
                                    ->heading('Informações do Aluno')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('student.name')
                                                    ->label('Nome do Aluno'),
                                                TextEntry::make('student.doc_number')
                                                    ->label('Documento do Aluno'),
                                                TextEntry::make('student.date_of_birth')
                                                    ->label('Data de Nascimento')
                                                    ->date(),
                                                TextEntry::make('student.gender')
                                                    ->label('Gênero'),
                                                TextEntry::make('student.phone_primary')
                                                    ->label('Telefone Principal'),
                                                TextEntry::make('student.phone_secondary')
                                                    ->label('Telefone Secundário'),
                                                TextEntry::make('student.state_of_birth')
                                                    ->label('Estado de Nascimento'),
                                                TextEntry::make('student.city_of_birth')
                                                    ->label('Cidade de Nascimento'),
                                                TextEntry::make('student.affiliation_1')
                                                    ->label('Filiação 1')
                                                    ->columnSpanFull(),
                                                TextEntry::make('student.affiliation_2')
                                                    ->label('Filiação 2')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Responsável')
                            ->schema([
                                Section::make()
                                    ->heading('Informações do Responsável Financeiro')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('student.customer.name')
                                                    ->label('Nome do Responsável'),
                                                TextEntry::make('student.customer.document')
                                                    ->label('CPF do Responsável')
                                                    ->formatStateUsing(fn($state) => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $state ?? '')),
                                                TextEntry::make('student.customer.email')
                                                    ->label('E-mail'),
                                                TextEntry::make('student.customer.phone')
                                                    ->label('Telefone'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Acadêmico')
                            ->schema([
                                Section::make()
                                    ->heading('Informações Acadêmicas')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('classroom.name')
                                                    ->label('Turma'),
                                                TextEntry::make('plan.name')
                                                    ->label('Plano'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
