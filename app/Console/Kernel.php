<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sincronizar clientes com Asaas - uma vez por dia à meia-noite
        $schedule->command('asaas:sync-customers --limit=500')
            ->dailyAt('00:30')
            ->withoutOverlapping(10)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Asaas sync customers failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Asaas customers synced successfully');
            });

        // Gerar cobranças mensais - 1º dia do mês às 08:00
        // Gera automaticamente cobranças para todas as invoices do mês corrente
        $schedule->command('asaas:generate-charges --limit=1000')
            ->monthlyOn(1, '08:00')
            ->withoutOverlapping(30)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Asaas charge generation failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Asaas charges generated successfully');
            });

        // Sincronizar pagamentos - a cada 6 horas
        $schedule->command('asaas:sync-payments --limit=500')
            ->everyFourHours()
            ->withoutOverlapping(5)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Asaas payment sync failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Asaas payments synced successfully');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
