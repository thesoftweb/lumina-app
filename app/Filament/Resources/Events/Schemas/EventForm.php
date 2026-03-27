<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Classroom;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Evento')
                    ->description('Preencha os dados do evento/contribuição.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->prefixIcon('heroicon-o-calendar')
                            ->label('Nome do Evento')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->prefixIcon('heroicon-o-tag')
                            ->label('Tipo')
                            ->options([
                                'celebration' => 'Festa/Celebração',
                                'trip' => 'Viagem',
                                'extracurricular' => 'Atividade Extraclasse',
                                'contribution' => 'Contribuição',
                            ])
                            ->required()
                            ->native(false),
                        MultiSelect::make('classrooms')
                            ->prefixIcon('heroicon-o-book-open')
                            ->label('Selecionar Turmas que Podem Participar')
                            ->description('Escolha uma ou mais turmas. O evento estará disponível apenas para alunos dessas turmas.')
                            ->relationship('classrooms', 'name')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('amount')
                            ->prefixIcon('heroicon-o-currency-dollar')
                            ->label('Valor (R$)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required(),
                        Select::make('status')
                            ->prefixIcon('heroicon-o-check-circle')
                            ->label('Status')
                            ->options([
                                'active' => 'Ativo',
                                'closed' => 'Fechado',
                                'canceled' => 'Cancelado',
                            ])
                            ->required()
                            ->native(false)
                            ->default('active'),
                        DateTimePicker::make('due_date')
                            ->prefixIcon('heroicon-o-clock')
                            ->label('Data Limite de Pagamento')
                            ->required(),
                        Textarea::make('description')
                            ->label('Descrição')
                            ->columnSpanFull()
                            ->rows(4)
                            ->maxLength(65535),
                    ]),
            ]);
    }
}
