<?php

use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\EnrollmentSheetController;
use App\Http\Controllers\IssuedDocumentController;
use App\Http\Controllers\PortalAgendaController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalEventController;
use App\Http\Controllers\PortalPixQrCodeController;
use App\Http\Controllers\ReportCardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Webhook Routes (público, sem CSRF)
Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle'])
    ->name('webhook.asaas');

// Portal Routes
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('login', [PortalController::class, 'login'])->name('login');
    Route::post('access', [PortalController::class, 'accessPortal'])->name('access');
    Route::get('aluno', [PortalController::class, 'showStudent'])->name('show');
    Route::get('matriculas', [PortalController::class, 'showEnrollments'])->name('enrollments.index');
    Route::get('faturas', [PortalController::class, 'showInvoices'])->name('invoices.index');
    Route::redirect('student', '/portal/aluno');

    // PIX QR Code Routes
    Route::get('invoices/{invoice}/pix-qrcode', [PortalPixQrCodeController::class, 'show'])->name('invoices.pix-qrcode');

    // Event Routes
    Route::get('events', [PortalEventController::class, 'index'])->name('events.index');
    Route::post('events/{event}/pay', [PortalEventController::class, 'store'])->name('events.store');
    Route::get('events/{event}/payment-link', [PortalEventController::class, 'paymentLink'])->name('events.payment-link');

    // Agenda Routes
    Route::get('agendas', [PortalAgendaController::class, 'index'])->name('agendas.index');
    Route::get('agendas/upcoming', [PortalAgendaController::class, 'upcoming'])->name('agendas.upcoming');
    Route::get('agendas/{id}', [PortalAgendaController::class, 'show'])->name('agendas.show');
});

// Documentos emitidos: impressão para a equipe e verificação pública de autenticidade
Route::get('documentos-emitidos/{issuedDocument}', [IssuedDocumentController::class, 'show'])
    ->middleware('auth')
    ->name('issued-documents.print');

Route::middleware('throttle:30,1')->group(function () {
    Route::get('verificar', [DocumentVerificationController::class, 'form'])->name('documents.verify.form');
    Route::get('verificar/{code}', [DocumentVerificationController::class, 'show'])->name('documents.verify');
});

Route::prefix('enrollments')->name('enrollments.')->group(function () {
    Route::get('{enrollment}/sheet', [EnrollmentSheetController::class, 'show'])->middleware('auth')->name('sheet');
});

Route::middleware('auth')->prefix('report-cards')->name('report-cards.')->group(function () {
    Route::get('{enrollment}', [ReportCardController::class, 'show'])->name('show');
    Route::get('{enrollment}/pdf', [ReportCardController::class, 'pdf'])->name('pdf');
});
