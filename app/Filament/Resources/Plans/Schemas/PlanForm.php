<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Models\AcademicYear;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Planos de Pagamento')
                    ->description('Configure os detalhes do plano de pagamento para mensalidades ou multiplas opções de pagamentos. Configure Descontos e valores finais automaticamente.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->prefixIcon('heroicon-o-tag')
                            ->label('Descrição do Plano'),
                        Select::make('academic_year_id')
                            ->prefixIcon('heroicon-o-calendar')
                            ->label('Ano letivo')
                            ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                            ->helperText('Deixe em branco só para planos antigos sem ano. Para reajustar valores, crie um plano novo no ano seguinte em vez de editar este.'),
                        TextInput::make('base_amount')
                            ->prefix('R$')
                            ->label('Valor Base')
                            ->numeric()
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                self::calculateFinalValue($set, $get);
                            }),
                        ToggleButtons::make('discount_type')
                            ->columnSpanFull()
                            ->label('Tipo de Desconto')
                            ->helperText('O desconto do plano não é automático: ele só vale na matrícula em que for negociado (rematrícula). Aluno novato paga o valor base.')
                            ->inline()
                            ->options([
                                'percentage' => 'Percentual',
                                'fixed'      => 'Fixo',
                                'none'       => 'Nenhum',
                            ])
                            ->default('none')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                if ($get('discount_type') === 'none') {
                                    $set('discount_value', 0);
                                }

                                self::calculateFinalValue($set, $get);
                            })
                            ->required(),
                        // Escondido no "Nenhum", o campo não é enviado; o modelo grava 0 nesse caso
                        TextInput::make('discount_value')
                            ->prefix(fn(Get $get) => $get('discount_type') === 'percentage' ? '%' : 'R$')
                            ->label('Valor do Desconto')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): ?float => $get('discount_type') === 'percentage' ? 100 : ((float) $get('base_amount') ?: null))
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                self::calculateFinalValue($set, $get);
                            })
                            ->hidden(fn(Get $get) => $get('discount_type') === 'none'),
                        TextInput::make('final_value')
                            ->prefix('R$')
                            ->label(fn (Get $get): string => $get('discount_type') === 'none' ? 'Valor Final' : 'Valor com desconto (quando negociado)')
                            ->numeric()
                            ->readOnly()
                            ->reactive()
                            ->afterStateHydrated(function (Set $set, Get $get) {
                                self::calculateFinalValue($set, $get);
                            })
                            ->dehydrated(),
                        Toggle::make('is_active')
                            ->columnSpanFull()
                            ->label('Ativo')
                    ])
            ])
            ->reactive();
    }

    private static function calculateFinalValue(Set $set, Get $get): void
    {
        $base = (float) $get('base_amount');
        $discountType = $get('discount_type');
        $discountValue = (float) $get('discount_value');

        $final = $base;

        if ($discountType === 'percentage') {
            // Exemplo: 30% → aplica desconto percentual
            $final = $base - ($base * ($discountValue / 100));
        }

        if ($discountType === 'fixed') {
            $final = $base - $discountValue;
        }

        if ($final < 0) {
            $final = 0; // Nunca deixa ficar negativo
        }

        $set('final_value', number_format($final, 2, '.', ''));
    }
}
