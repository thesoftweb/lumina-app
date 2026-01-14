<?php

use App\Http\Controllers\DocumentPrintController;
use App\Http\Controllers\SchoolContractController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::post('/artisan', App\Http\Controllers\ArtisanController::class);

Route::get('/', function () {
    return view('welcome');
});

// Portal Routes
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('login', [PortalController::class, 'login'])->name('login');
    Route::post('access', [PortalController::class, 'accessPortal'])->name('access');
    Route::get('student', [PortalController::class, 'showStudent'])->name('show');
});

// Document Routes
Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('{document}/print', [DocumentPrintController::class, 'show'])->name('print');
    Route::get('{document}/pdf', [DocumentPrintController::class, 'pdf'])->name('pdf');
});

// Enrollment Contract Routes
Route::prefix('enrollments')->name('enrollments.')->group(function () {
    Route::get('{enrollment}/contract/print', [SchoolContractController::class, 'show'])->name('contract.print');
    Route::get('{enrollment}/contract/pdf', [SchoolContractController::class, 'pdf'])->name('contract.pdf');
});
