<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Descrição do Plano'),
                TextInput::make('base_amount')
                    ->label('Valor Base')
                    ->numeric()
                    ->reactive()
                    ->required(),
                ToggleButtons::make('discount_type')
                    ->label('Tipo de Desconto')
                    ->inline()
                    ->options([
                        'percentage' => 'Percentual',
                        'fixed'      => 'Fixo',
                        'none'       => 'Nenhum',
                    ])
                    ->default('none')
                    ->reactive()
                    ->required(),
                TextInput::make('discount_value')
                    ->label('Valor do Desconto')
                    ->numeric()
                    ->reactive()
                    ->required()
                    ->hidden(fn (Get $get) => $get('discount_type') === 'none'),
                TextInput::make('final_value')
                    ->label('Valor Final')
                    ->numeric()
                    ->readOnly()
                    ->reactive()
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        self::calculateFinalValue($set, $get);
                    })
                    ->dehydrated(),
                Toggle::make('is_active')
                    ->label('Ativo')
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
