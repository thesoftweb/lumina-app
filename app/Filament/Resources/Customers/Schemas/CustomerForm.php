<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\InvoicesRelationManager;
use App\Models\City;
use App\Models\Customer;
use App\Models\State;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Cadastro do cliente')
                    ->contained(false)
                    ->persistTabInQueryString('cadastro')
                    ->tabs([
                        Tab::make('Cadastro')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Dados do responsável')
                                    ->description('Identificação e contato do responsável financeiro.')
                                    ->columns(['default' => 1, 'md' => 2])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nome completo')
                                            ->prefixIcon('heroicon-o-user')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        TextInput::make('document')
                                            ->label('CPF')
                                            ->prefixIcon('heroicon-o-identification')
                                            ->mask('999.999.999-99')
                                            ->placeholder('000.000.000-00')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(14),
                                        TextInput::make('email')
                                            ->label('E-mail')
                                            ->prefixIcon('heroicon-o-envelope')
                                            ->email()
                                            ->maxLength(255)
                                            ->autocomplete('email'),
                                        TextInput::make('phone')
                                            ->label('Telefone')
                                            ->prefixIcon('heroicon-o-phone')
                                            ->mask('(99) 99999-9999')
                                            ->placeholder('(00) 00000-0000')
                                            ->maxLength(15)
                                            ->autocomplete('tel'),
                                    ]),
                            ]),
                        Tab::make('Endereço')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Endereço de cobrança')
                                    ->description('Usado quando a cobrança precisa dos dados de localização do responsável.')
                                    ->columns(['default' => 1, 'md' => 4])
                                    ->schema([
                                        TextInput::make('postal_code')
                                            ->label('CEP')
                                            ->mask('99999-999')
                                            ->placeholder('00000-000')
                                            ->maxLength(9),
                                        Select::make('state')
                                            ->label('Estado')
                                            ->options(fn (): array => State::query()->orderBy('name')->pluck('name', 'code')->all())
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(fn (callable $set) => $set('city_id', null)),
                                        Select::make('city_id')
                                            ->label('Cidade')
                                            ->options(function (callable $get): array {
                                                $stateCode = $get('state');

                                                if (blank($stateCode)) {
                                                    return [];
                                                }

                                                $stateId = State::query()->where('code', $stateCode)->value('id');

                                                return $stateId
                                                    ? City::query()->where('state_id', $stateId)->orderBy('name')->pluck('name', 'id')->all()
                                                    : [];
                                            })
                                            ->searchable()
                                            ->disabled(fn (callable $get): bool => blank($get('state'))),
                                        TextInput::make('neighborhood')
                                            ->label('Bairro')
                                            ->maxLength(255),
                                        TextInput::make('address')
                                            ->label('Rua / avenida')
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 1, 'md' => 2]),
                                        TextInput::make('address_number')
                                            ->label('Número')
                                            ->maxLength(10),
                                        TextInput::make('address_complement')
                                            ->label('Complemento')
                                            ->placeholder('Apartamento, bloco, referência...')
                                            ->maxLength(255)
                                            ->columnSpan(['default' => 1, 'md' => 2]),
                                    ]),
                            ]),
                        Tab::make('Faturas')
                            ->icon('heroicon-o-banknotes')
                            ->visibleOn('edit')
                            ->schema([
                                LivewireComponent::make(
                                    InvoicesRelationManager::class,
                                    fn (Customer $record): array => [
                                        'ownerRecord' => $record,
                                        'pageClass' => EditCustomer::class,
                                    ],
                                )->key('customer-invoices'),
                            ]),
                    ]),
            ]);
    }
}
