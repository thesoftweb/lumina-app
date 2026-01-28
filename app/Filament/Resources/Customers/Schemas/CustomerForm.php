<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\City;
use App\Models\State;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading('Informações do Cliente')
                    ->description('Um cliente é representa um responsável financeiro de um ou mais alunos.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome Completo')
                                    ->prefixIcon('heroicon-o-user')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('document')
                                    ->label('CPF')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->prefixIcon('heroicon-o-identification')
                                    ->mask('999.999.999-99')
                                    ->placeholder('999.999.999-99')
                                    ->maxLength(14),
                                TextInput::make('email')
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->label('E-mail')
                                    ->email(),
                                TextInput::make('phone')
                                    ->label('Telefone')
                                    ->prefixIcon('heroicon-o-phone')
                                    ->mask('(99) 99999-9999')
                                    ->placeholder('(99) 99999-9999')
                                    ->maxLength(15),
                            ]),
                    ]),

                Section::make()
                    ->heading('Endereço')
                    ->description('Informações de endereço para o Asaas')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('address')
                                    ->label('Rua')
                                    ->prefixIcon('heroicon-o-map-pin')
                                    ->maxLength(255),
                                TextInput::make('address_number')
                                    ->label('Número')
                                    ->maxLength(10),
                                TextInput::make('address_complement')
                                    ->label('Complemento')
                                    ->maxLength(255),
                                TextInput::make('neighborhood')
                                    ->label('Bairro')
                                    ->maxLength(255),
                                Select::make('state')
                                    ->label('Estado')
                                    ->prefixIcon('heroicon-o-map')
                                    ->preload()
                                    ->live()
                                    ->options(
                                        State::query()->orderBy('name')->pluck('name', 'code')
                                    )
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('city_id', null);
                                    }),
                                Select::make('city_id')
                                    ->label('Cidade')
                                    ->prefixIcon('heroicon-o-building-office')
                                    ->reactive()
                                    ->searchable()
                                    ->preload()
                                    ->options(
                                        function (callable $get) {
                                            $state = $get('state');
                                            if (!$state) {
                                                return [];
                                            }

                                            return City::where('state_id', $state)
                                                ->orderBy('name')
                                                ->pluck('name', 'id');
                                        }
                                    )
                                    ->disabled(fn(callable $get) => !$get('state')),
                                TextInput::make('postal_code')
                                    ->label('CEP')
                                    ->mask('99999-999')
                                    ->placeholder('12345-678')
                                    ->maxLength(9),
                            ]),
                    ]),
            ]);
    }
}


