<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaidInvoicesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $currentMonth = now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // Faturas pagas no mês atual
        $paidThisMonth = Invoice::whereBetween('updated_at', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where('status', 'paid')
            ->count();

        // Total geral de faturas pagas
        $totalPaid = Invoice::where('status', 'paid')->count();

        // Total geral de faturas em aberto
        $totalOpen = Invoice::where('status', '!=', 'paid')
            ->where('status', '!=', 'canceled')
            ->count();

        // Total de faturas canceladas
        $totalCanceled = Invoice::where('status', 'canceled')->count();

        // Valor total recebido
        $totalReceivedAmount = Invoice::where('status', 'paid')->sum('amount');

        return [
            Stat::make('Faturas Pagas - Este Mês', $paidThisMonth)
                ->description('Pagamentos recebidos neste mês')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Total de Faturas Pagas', $totalPaid)
                ->description('Todas as faturas pagas')
                ->descriptionIcon('heroicon-m-check')
                ->color('success')
                ->icon('heroicon-o-check'),

            Stat::make('Faturas em Aberto', $totalOpen)
                ->description('Aguardando pagamento')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Faturas Canceladas', $totalCanceled)
                ->description('Invoices canceladas')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->icon('heroicon-o-x-circle'),

            Stat::make('Total Recebido', 'R$ ' . number_format($totalReceivedAmount, 2, ',', '.'))
                ->description('Valor total de pagamentos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
