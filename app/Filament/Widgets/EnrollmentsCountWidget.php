<?php

namespace App\Filament\Widgets;

use App\Models\Enrollment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EnrollmentsCountWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total de Matrículas', Enrollment::count())
                ->description('Quantidade total de matrículas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->icon('heroicon-o-user-plus'),

            Stat::make('Matrículas Ativas', Enrollment::where('status', 'active')->count())
                ->description('Matrículas ativas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Matrículas Reservadas', Enrollment::where('status', 'reserved')->count())
                ->description('Matrículas reservadas')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->icon('heroicon-o-exclamation-circle'),

            Stat::make('Matrículas Canceladas', Enrollment::where('status', 'canceled')->count())
                ->description('Matrículas canceladas')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
