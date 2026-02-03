<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\EnrollmentsCountWidget;
use App\Filament\Widgets\NextMonthInvoicesTotalWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            EnrollmentsCountWidget::class,
            NextMonthInvoicesTotalWidget::class,
        ];
    }
}
