<?php

use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rotas públicas da API sem autenticação
|
*/

Route::prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'index'])->name('students.index');
    Route::get('/{studentId}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/classroom/{classroomId}', [StudentController::class, 'byClassroom'])->name('students.by-classroom');
});
