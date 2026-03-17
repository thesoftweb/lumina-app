<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NextMonthInvoicesTotalWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $nextMonth = now()->addMonth();
        $startOfMonth = $nextMonth->copy()->startOfMonth();
        $endOfMonth = $nextMonth->copy()->endOfMonth();

        $invoices = Invoice::whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where('status', '!=', 'canceled')
            ->get();

        $totalAmount = $invoices->sum('final_amount') ?? $invoices->sum('amount');
        $openInvoices = $invoices->where('status', '!=', 'paid')->count();

        return [
            Stat::make('Total do Próximo Mês', 'R$ ' . number_format($totalAmount, 2, ',', '.'))
                ->description($openInvoices . ' faturas em aberto')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Faturas Abertas - Próx. Mês', $openInvoices)
                ->description('Invoices pendentes')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning')
                ->icon('heroicon-o-exclamation-circle'),

            Stat::make('Invoices Pagas - Próx. Mês', $invoices->where('status', 'paid')->count())
                ->description('Invoices pagas')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}
