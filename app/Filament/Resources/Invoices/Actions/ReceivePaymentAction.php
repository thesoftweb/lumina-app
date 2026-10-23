<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\PaymentType;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

/**
 * Recebimento na secretaria (total ou parcial). Com cobrança no Asaas: o total dá baixa manual na
 * cobrança; o parcial muda o valor da cobrança para o saldo restante.
 */
class ReceivePaymentAction
{
    public static function make(): Action
    {
        return Action::make('receivePayment')
            ->label('Receber pagamento')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->authorize('update')
            ->visible(fn (Invoice $record): bool => ChargeOperations::canReceive($record))
            ->modalHeading('Receber pagamento')
            ->modalDescription(fn (Invoice $record): string => 'Saldo a pagar: '.self::money((float) $record->balance).'.')
            ->modalSubmitActionLabel('Registrar pagamento')
            ->schema([
                Radio::make('kind')
                    ->label('Pagamento')
                    ->options([
                        'full' => 'Total (quita a fatura)',
                        'partial' => 'Parcial',
                    ])
                    ->default('full')
                    ->inline()
                    ->required()
                    ->live(),
                TextInput::make('amount')
                    ->label('Valor recebido')
                    ->prefix('R$')
                    ->numeric()
                    ->inputMode('decimal')
                    ->minValue(0.01)
                    ->required()
                    ->default(fn (Invoice $record): float => (float) $record->balance)
                    ->helperText(fn (Get $get, Invoice $record): ?string => self::amountHelp($get('kind'), $record)),
                Select::make('payment_method')
                    ->label('Forma de pagamento')
                    ->options(collect(ChargeOperations::RECEIVABLE_METHODS)->mapWithKeys(fn (PaymentType $type): array => [$type->value => $type->getLabel()])->all())
                    ->default(PaymentType::CASH->value)
                    ->required()
                    ->native(false),
                DatePicker::make('payment_date')
                    ->label('Data do pagamento')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                TextInput::make('reference')
                    ->label('Comprovante / referência')
                    ->placeholder('Ex.: ID do PIX, nº do comprovante')
                    ->maxLength(100),
                Textarea::make('notes')
                    ->label('Observações')
                    ->rows(2),
                Toggle::make('notify_customer')
                    ->label('Avisar o responsável pelo Asaas')
                    ->default(false)
                    ->visible(fn (Get $get, Invoice $record): bool => $get('kind') === 'full' && ChargeOperations::hasActiveCharge($record)),
            ])
            ->action(function (Action $action, Invoice $record, array $data, ChargeOperations $operations): void {
                $partial = $data['kind'] === 'partial';

                RunsChargeOperation::run(
                    $action,
                    fn () => $operations->receive(
                        invoice: $record,
                        amount: (float) $data['amount'],
                        method: PaymentType::from($data['payment_method']),
                        date: Carbon::parse($data['payment_date']),
                        partial: $partial,
                        reference: $data['reference'] ?? null,
                        notes: $data['notes'] ?? null,
                        notifyCustomer: (bool) ($data['notify_customer'] ?? false),
                    ),
                    $partial ? 'Pagamento parcial registrado' : 'Pagamento registrado',
                    fn (): string => $partial
                        ? 'Saldo restante: '.self::money((float) $record->balance).'.'
                        : 'Fatura nº '.($record->number ?? $record->getKey()).' quitada.',
                );
            });
    }

    private static function amountHelp(?string $kind, Invoice $record): ?string
    {
        if (! ChargeOperations::hasActiveCharge($record)) {
            return $kind === 'partial' ? 'Menor que o saldo; a fatura fica parcial.' : null;
        }

        return $kind === 'partial'
            ? 'Menor que o saldo. A cobrança no Asaas passa a valer o saldo restante, para o responsável pagar o resto por PIX/boleto.'
            : 'A cobrança é baixada no Asaas como recebida (baixa manual) e o responsável deixa de ser cobrado.';
    }

    private static function money(float $value): string
    {
        return Number::currency($value, 'BRL', 'pt_BR');
    }
}
