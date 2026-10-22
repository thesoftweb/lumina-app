<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Não há rota "login" padrão: visitantes em rotas com "auth" vão para o login do painel.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        $middleware->validateCsrfTokens(except: [
            'artisan',
            'webhooks/asaas',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
