<?php

use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas públicas da API sem autenticação
| Separadas por tenant na URL: /api/{tenant}/...
|
*/

Route::prefix('{tenant}')
    ->middleware(ResolveTenant::class)
    ->group(function () {
        // Rota de configurações da empresa (tenant)
        Route::get('/settings', [SettingsController::class, 'index'])->name('api.settings.index');

        // Rotas de turmas
        Route::prefix('classes')->group(function () {
            Route::get('/', [ClassroomController::class, 'index'])->name('api.classes.index');
            Route::get('/{classroomId}', [ClassroomController::class, 'show'])->name('api.classes.show');
        });

        // Rotas de estudantes
        Route::prefix('students')->group(function () {
            Route::get('/', [StudentController::class, 'index'])->name('api.students.index');
            Route::get('/{studentId}', [StudentController::class, 'show'])->name('api.students.show');
            Route::get('/classroom/{classroomId}', [StudentController::class, 'byClassroom'])->name('api.students.by-classroom');
        });
    });

// Rotas legadas (sem tenant) - Mantidas para compatibilidade
Route::prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'index'])->name('students.index');
    Route::get('/{studentId}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/classroom/{classroomId}', [StudentController::class, 'byClassroom'])->name('students.by-classroom');
});

