<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\DegreeOfKinship;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Customer;
use App\Models\State;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações Pessoais do Aluno')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('name')
                                    ->prefixIcon('heroicon-o-user')
                                    ->label('Nome')
                                    ->required(),
                                DatePicker::make('date_of_birth')
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->label('Data de Nascimento')
                                    ->required(),
                                Select::make('state_of_birth')
                                    ->prefixIcon('heroicon-o-map')
                                    ->label('UF de Nascimento')
                                    ->preload()
                                    ->live()
                                    ->options(
                                        State::all()->pluck('name', 'code')
                                    )->required()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('city_of_birth', null);
                                    }),
                                Select::make('city_of_birth')
                                    ->prefixIcon('heroicon-o-building-office')
                                    ->reactive()
                                    ->searchable()
                                    ->options(
                                        function (callable $get) {
                                            $state = $get('state_of_birth');
                                            if (!$state) {
                                                return [];
                                            }

                                            return \App\Models\City::where('state_id', $state)
                                                ->orderBy('name')
                                                ->pluck('name', 'name');
                                        }
                                    )->label('Cidade de Nascimento')
                                    ->required()
                                    ->disabled(fn(callable $get) => !$get('state_of_birth')),
                                TextInput::make('reg_number')
                                    ->prefixIcon('heroicon-o-identification')
                                    ->label('RG'),
                                TextInput::make('doc_number')
                                    ->prefixIcon('heroicon-o-identification')
                                    ->mask('999.999.999-99')
                                    ->label('CPF'),
                                Radio::make('gender')
                                    ->label('Gênero')
                                    ->inline()
                                    ->options([
                                        'M' => 'Masculino',
                                        'F' => 'Feminino',
                                        'O' => 'Outro',
                                    ])
                                    ->required(),
                            ]),
                    ]),
                Section::make('Filiação')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('affiliation_1')
                                    ->prefixIcon('heroicon-o-user-group')
                                    ->label('Filiação 1')
                                    ->required(),
                                TextInput::make('phone_primary')
                                    ->prefixIcon('heroicon-o-phone')
                                    ->mask('(99) 99999-9999')
                                    ->label('Contato do Responsável')
                                    ->required(),
                                TextInput::make('affiliation_2')
                                    ->prefixIcon('heroicon-o-user-group')
                                    ->label('Filiação 2'),
                                TextInput::make('phone_secondary')
                                    ->prefixIcon('heroicon-o-phone')
                                    ->mask('(99) 99999-9999')
                                    ->label('Contato do Responsável')
                            ]),
                    ]),
                Section::make('Informações Adicionais do Aluno')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make()
                            ->schema([
                                CheckboxList::make('neurodiversy')
                                    ->label('Neurodiversidade')
                                    ->columns(2)
                                    ->options(function () {
                                        return \App\Models\StudentProfile::where('is_active', true)->pluck('name', 'id');
                                    }),
                                Textarea::make('observations')
                                    ->label('Observações')
                                    ->rows(4),
                            ]),
                    ]),
                Section::make('Dados do Cliente e Responsável Financeiro')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make()
                            ->schema([
                                Select::make('customer_id')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm(function (Schema $schema) {
                                        return CustomerForm::configure($schema);
                                    })
                                    ->prefixIcon('heroicon-o-briefcase')
                                    ->label('Responsável Financeiro')
                                    ->relationship('customer', 'name')
                                    ->required()
                                    ->options(function () {
                                        return Customer::all()->pluck('name', 'id');
                                    }),
                                Select::make('degree_of_kinship')
                                    ->label('Grau Parentesco')
                                    ->options(DegreeOfKinship::options())
                                    ->required()
                            ]),
                    ]),
            ]);
    }
}
