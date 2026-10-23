<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Edição rápida (valor, vencimento e observações) pela mesma operação do formulário da fatura:
 * vai para o Asaas enquanto a fatura está aberta/vencida e sem pagamentos.
 */
class EditChargeAction
{
    public static function make(): Action
    {
        return Action::make('editCharge')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencil)
            ->color('gray')
            ->authorize('update')
            ->slideOver()
            ->modalHeading(fn (Invoice $record): string => 'Fatura nº '.($record->number ?? $record->getKey()))
            ->modalDescription(fn (Invoice $record): string => ChargeOperations::canEditCharge($record)
                ? (ChargeOperations::hasActiveCharge($record) ? 'As alterações também são feitas na cobrança do Asaas.' : 'Fatura sem cobrança no Asaas: alterações só no sistema.')
                : 'Fatura paga, parcial ou cancelada: valor e vencimento não mudam mais; as observações ficam só no sistema.')
            ->fillForm(fn (Invoice $record): array => [
                'amount' => $record->amount,
                'due_date' => $record->due_date?->toDateString(),
                'notes' => $record->notes,
            ])
            ->schema([
                TextInput::make('amount')
                    ->label('Valor')
                    ->prefix('R$')
                    ->numeric()
                    ->inputMode('decimal')
                    ->minValue(0.01)
                    ->required()
                    ->disabled(fn (Invoice $record): bool => ! ChargeOperations::canEditCharge($record)),
                DatePicker::make('due_date')
                    ->label('Vencimento')
                    ->required()
                    ->disabled(fn (Invoice $record): bool => ! ChargeOperations::canEditCharge($record)),
                Textarea::make('notes')
                    ->label('Observações (descrição da cobrança)')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (Action $action, Invoice $record, array $data, ChargeOperations $operations): void {
                RunsChargeOperation::run(
                    $action,
                    fn () => $operations->update($record, $data),
                    'Fatura atualizada',
                );
            });
    }
}
