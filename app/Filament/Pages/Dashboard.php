<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BirthdaysWidget;
use App\Filament\Widgets\EnrollmentsCountWidget;
use App\Filament\Widgets\NextMonthInvoicesTotalWidget;
use App\Filament\Widgets\PaidInvoicesStatsWidget;
use App\Filament\Widgets\TeacherClassroomsWidget;
use App\Filament\Widgets\TeacherStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        if (Auth::user()->hasRole('teacher')) {
            return [
                TeacherStatsWidget::class,
                BirthdaysWidget::class,
                TeacherClassroomsWidget::class,
            ];
        }

        return [
            EnrollmentsCountWidget::class,
            PaidInvoicesStatsWidget::class,
            NextMonthInvoicesTotalWidget::class,
        ];
    }
}
