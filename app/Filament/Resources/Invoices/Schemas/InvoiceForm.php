<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Models\Customer;
use App\Models\Company;
use App\Models\AccountPlan;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading('Informações da Fatura')
                    ->description('Preencha os dados para criar uma nova fatura')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Cliente')
                                    ->required()
                                    ->options(fn() => Customer::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),

                                Select::make('billing_type')
                                    ->label('Tipo de Fatura')
                                    ->required()
                                    ->options([
                                        'enrollment' => 'Taxa de Matrícula',
                                        'tuition' => 'Mensalidade',
                                        'service' => 'Serviço Adicional',
                                        'material' => 'Material Didático',
                                        'other' => 'Outra Entrada',
                                    ])
                                    ->default('tuition'),

                                DatePicker::make('issue_date')
                                    ->label('Data de Emissão')
                                    ->required()
                                    ->default(now()),

                                DatePicker::make('due_date')
                                    ->label('Data de Vencimento')
                                    ->required(),

                                TextInput::make('amount')
                                    ->label('Valor')
                                    ->required()
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->prefix('R$'),

                                Select::make('company_id')
                                    ->label('Empresa')
                                    ->options(fn() => Company::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),

                                Select::make('account_id')
                                    ->label('Plano de Contas')
                                    ->options(fn() => AccountPlan::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('reference')
                                    ->label('Referência')
                                    ->helperText('Deixe em branco para gerar automaticamente'),
                            ]),
                    ]),
                    Textarea::make('notes')
                            ->columnSpanFull()
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),

            ]);
    }
}
