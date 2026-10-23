<?php

namespace App\Filament\Widgets;

use App\Models\Enrollment;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EnrollmentsCountWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total de matrículas', Enrollment::count())
                ->description('Histórico completo de matrículas')
                ->color('primary')
                ->icon('heroicon-o-academic-cap'),

            Stat::make('Matrículas ativas', Enrollment::where('status', 'active')->count())
                ->description('Alunos atualmente matriculados')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Novatos', Enrollment::where('new_student', true)->count())
                ->description('Primeira matrícula na instituição')
                ->color('info')
                ->icon('heroicon-o-sparkles'),

            Stat::make('Aniversariantes', Student::query()
                ->whereMonth('date_of_birth', now()->month)
                ->whereHas('enrollments', fn ($query) => $query->where('status', 'active'))
                ->count())
                ->description('Alunos aniversariantes neste mês')
                ->color('warning')
                ->icon('heroicon-o-gift'),
        ];
    }
}
