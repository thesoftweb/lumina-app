<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Conciliação diária com o Asaas: cobranças que vencem do último ano em diante, para pegar baixas
// em dinheiro e pagamentos atrasados que o webhook não trouxe.
Schedule::command('asaas:reconcile', ['--since' => now()->subYear()->toDateString()])
    ->dailyAt('03:00')
    ->withoutOverlapping();
