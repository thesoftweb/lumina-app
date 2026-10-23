<?php

namespace App\Filament\Resources\Invoices\Widgets;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** Totais no topo da listagem de faturas: em aberto, vencido e recebido no mês corrente. */
class InvoiceTotalsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $open = Invoice::query()->unpaid();
        $overdue = Invoice::query()->overdue();
        $receivedThisMonth = InvoicePayment::query()
            ->whereDate('payment_date', '>=', today()->startOfMonth())
            ->whereDate('payment_date', '<=', today()->endOfMonth());

        $openCount = (clone $open)->count();
        $overdueCount = (clone $overdue)->count();
        $paymentsCount = (clone $receivedThisMonth)->count();

        return [
            Stat::make('Total em aberto', self::money((clone $open)->sum('balance')))
                ->description($openCount === 1 ? '1 fatura com saldo a pagar' : "{$openCount} faturas com saldo a pagar")
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info'),
            Stat::make('Total vencido', self::money((clone $overdue)->sum('balance')))
                ->description($overdueCount === 1 ? '1 fatura vencida' : "{$overdueCount} faturas vencidas")
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($overdueCount > 0 ? 'danger' : 'gray'),
            Stat::make('Recebido em '.today()->locale('pt_BR')->translatedFormat('F/Y'), self::money((clone $receivedThisMonth)->sum('amount')))
                ->description($paymentsCount === 1 ? '1 pagamento registrado' : "{$paymentsCount} pagamentos registrados")
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }

    private static function money(float|int|string|null $value): string
    {
        return Number::currency((float) $value, 'BRL', 'pt_BR');
    }
}
