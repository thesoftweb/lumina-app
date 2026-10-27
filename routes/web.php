<?php

use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\EnrollmentContractController;
use App\Http\Controllers\EnrollmentSheetController;
use App\Http\Controllers\IssuedDocumentController;
use App\Http\Controllers\PortalAgendaController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalEventController;
use App\Http\Controllers\PortalPixQrCodeController;
use App\Http\Controllers\PreEnrollmentController;
use App\Http\Controllers\ReenrollmentController;
use App\Http\Controllers\ReportCardController;
use App\Http\Middleware\VerifyAsaasWebhookToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Pré-matrícula pública: uma ficha por ano letivo (/pre-matricula/2027); o protocolo só volta pela sessão
Route::prefix('pre-matricula')->name('pre-enrollment.')->group(function () {
    Route::get('/', [PreEnrollmentController::class, 'index'])->name('index');
    Route::get('{year}', [PreEnrollmentController::class, 'create'])->whereNumber('year')->name('create');
    Route::post('{year}', [PreEnrollmentController::class, 'store'])->whereNumber('year')->middleware('throttle:10,1')->name('store');
    Route::get('{year}/enviada', [PreEnrollmentController::class, 'done'])->whereNumber('year')->name('done');

    // Rematrícula: link próprio de cada família, enviado pela secretaria
    Route::get('{year}/rematricula/{token}', [ReenrollmentController::class, 'show'])
        ->whereNumber('year')->where('token', '[A-Za-z0-9]{20,64}')->name('reenrollment');
    Route::post('{year}/rematricula/{token}', [ReenrollmentController::class, 'confirm'])
        ->whereNumber('year')->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:10,1')->name('reenrollment.store');
});

// Webhook do Asaas (sem CSRF; autenticado pelo token do cabeçalho asaas-access-token)
Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle'])
    ->middleware(VerifyAsaasWebhookToken::class)
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
Route::get('documentos-emitidos/{issuedDocument}/pdf', [IssuedDocumentController::class, 'pdf'])
    ->middleware('auth')
    ->name('issued-documents.pdf');
Route::get('carteirinhas/imprimir', [IssuedDocumentController::class, 'cards'])
    ->middleware('auth')
    ->name('pickup-cards.print');

Route::middleware('throttle:30,1')->group(function () {
    Route::get('verificar', [DocumentVerificationController::class, 'form'])->name('documents.verify.form');
    Route::get('verificar/{code}', [DocumentVerificationController::class, 'show'])->name('documents.verify');
});

Route::prefix('enrollments')->name('enrollments.')->group(function () {
    Route::get('{enrollment}/sheet', [EnrollmentSheetController::class, 'show'])->middleware('auth')->name('sheet');
    Route::get('{enrollment}/contract', [EnrollmentContractController::class, 'show'])->middleware('auth')->name('contract');
});

Route::middleware('auth')->prefix('report-cards')->name('report-cards.')->group(function () {
    Route::get('{enrollment}', [ReportCardController::class, 'show'])->name('show');
    Route::get('{enrollment}/pdf', [ReportCardController::class, 'download'])->name('pdf');
});
