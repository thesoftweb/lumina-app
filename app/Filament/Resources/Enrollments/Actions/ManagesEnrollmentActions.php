<?php

namespace App\Filament\Resources\Enrollments\Actions;

use App\Documents\EnrollmentDeclaration;
use App\Enums\EnrollmentStatus;
use App\Models\IssuedDocument;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait ManagesEnrollmentActions
{
    protected function getEnrollmentActions(): array
    {
        return [
            $this->createEnrollmentDeclarationAction(),
            $this->createSchoolContractPrintAction(),
            $this->createSchoolContractPdfAction(),
            $this->createEnrollmentInvoiceAction(),
            $this->createTuitionInvoiceAction(),
        ];
    }

    /**
     * Action para imprimir a ficha de matrícula (abre já com a janela de impressão/salvar PDF)
     */
    protected function createEnrollmentSheetAction(): Action
    {
        return Action::make('printEnrollmentSheet')
            ->label('Imprimir ficha')
            ->icon('heroicon-o-printer')
            ->url(fn () => route('enrollments.sheet', ['enrollment' => $this->record, 'print' => 1]))
            ->openUrlInNewTab();
    }

    /**
     * Action para emitir a declaração de matrícula (numerada e com código de verificação)
     */
    private function createEnrollmentDeclarationAction(): Action
    {
        return Action::make('issueEnrollmentDeclaration')
            ->label('Emitir declaração de matrícula')
            ->icon('heroicon-o-document-check')
            ->visible(fn () => EnrollmentDeclaration::canIssue($this->record) && Gate::allows('create', IssuedDocument::class))
            ->modalHeading('Emitir declaração de matrícula')
            ->modalDescription('A declaração recebe um número e um código de verificação. Depois de emitida, não pode ser alterada; se houver erro, o admin a cancela e uma nova é emitida.')
            ->modalSubmitActionLabel('Emitir')
            ->schema([
                TextInput::make('purpose')
                    ->label('Finalidade')
                    ->placeholder('Ex.: transporte escolar')
                    ->helperText('Opcional. Sai no texto como "para fins de ...".')
                    ->maxLength(150),
                Select::make('valid_for_days')
                    ->label('Validade')
                    ->options([30 => '30 dias', 60 => '60 dias', 90 => '90 dias'])
                    ->placeholder('Sem prazo de validade')
                    ->default(90),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    $document = EnrollmentDeclaration::issue(
                        $this->record,
                        auth()->user(),
                        $data['purpose'] ?? null,
                        filled($data['valid_for_days'] ?? null) ? (int) $data['valid_for_days'] : null,
                    );
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Declaração não emitida')->body(collect($e->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Declaração emitida')
                    ->body("Nº {$document->number}")
                    ->actions([
                        Action::make('open')
                            ->label('Abrir para imprimir')
                            ->button()
                            ->url(route('issued-documents.print', ['issuedDocument' => $document, 'print' => 1]), shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Action para imprimir contrato escolar
     */
    private function createSchoolContractPrintAction(): Action
    {
        return Action::make('printSchoolContract')
            ->label('Imprimir Contrato')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('enrollments.contract.print', $this->record))
            ->openUrlInNewTab();
    }

    /**
     * Action para baixar contrato escolar em PDF
     */
    private function createSchoolContractPdfAction(): Action
    {
        return Action::make('pdfSchoolContract')
            ->label('Baixar Contrato PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->url(fn () => route('enrollments.contract.pdf', $this->record))
            ->openUrlInNewTab();
    }

    /**
     * Action para gerar fatura de matrícula
     */
    private function createEnrollmentInvoiceAction(): Action
    {
        return Action::make('createEnrollmentInvoice')
            ->label('Gerar Fatura de Matrícula')
            ->icon('heroicon-o-document-text')
            ->schema([
                TextInput::make('enrollment_amount')
                    ->label('Valor da Matrícula')
                    ->numeric()
                    ->required()
                    ->prefix('R$')
                    ->helperText('Informe o valor da taxa de matrícula'),
                Checkbox::make('generate_invoice')
                    ->label('Gerar fatura')
                    ->default(true),
                Checkbox::make('send_notification')
                    ->label('Encaminhar por email/WhatsApp')
                    ->helperText('Enviar notificação ao responsável sobre a fatura gerada'),
            ])
            ->modalHeading('Gerar Fatura de Matrícula')
            ->modalDescription('Tem certeza que deseja gerar a fatura de matrícula para este aluno?')
            ->modalSubmitActionLabel('Gerar')
            ->visible(fn () => $this->record->status !== EnrollmentStatus::Canceled)
            ->action(function (InvoiceService $invoiceService, array $data) {
                try {
                    $invoice = $invoiceService->createEnrollmentInvoiceFromEnrollment(
                        enrollment: $this->record,
                        amount: (float) $data['enrollment_amount'],
                        companyId: 1,
                        notes: 'Taxa de matrícula pelo sistema'
                    );

                    Notification::make()
                        ->success()
                        ->title('Sucesso!')
                        ->body("Fatura #{$invoice->number} gerada com sucesso")
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Erro!')
                        ->body("Erro ao gerar fatura: {$e->getMessage()}")
                        ->send();
                }
            });
    }

    /**
     * Action para gerar faturas de mensalidade
     */
    private function createTuitionInvoiceAction(): Action
    {
        return Action::make('createTuitionInvoice')
            ->label('Gerar Mensalidades')
            ->icon('heroicon-o-calendar')
            ->schema([
                TextInput::make('quantity')
                    ->label('Quantidade de Meses')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(12)
                    ->helperText('Quantos meses de mensalidade deseja gerar'),
                Select::make('start_month')
                    ->label('Mês de Início')
                    ->options(function () {
                        return collect(range(1, 12))->mapWithKeys(function ($month) {
                            return [
                                $month => Carbon::createFromDate(now()->year, $month, 1)->locale('pt_BR')->format('F \d\e Y'),
                            ];
                        })->toArray();
                    })
                    ->required()
                    ->default(now()->month)
                    ->helperText('Mês de início para as mensalidades'),
                TextInput::make('company_id')
                    ->label('ID da Empresa')
                    ->numeric()
                    ->required()
                    ->default(1),
            ])
            ->visible(fn () => $this->record->status !== EnrollmentStatus::Canceled)
            ->action(function (InvoiceService $invoiceService, array $data) {
                try {
                    $quantity = (int) $data['quantity'];
                    $startMonth = (int) $data['start_month'];
                    $companyId = (int) $data['company_id'];

                    // Get the plan value from classroom
                    $enrollment = $this->record->load('classroom.plans');
                    $plan = $enrollment->classroom->plans->first();

                    if (! $plan) {
                        Notification::make()
                            ->danger()
                            ->title('Erro!')
                            ->body('Nenhum plano associado à sala de aula')
                            ->send();

                        return;
                    }

                    $planValue = (float) $plan->base_amount;
                    $createdCount = 0;

                    // Create invoices for each month
                    for ($i = 0; $i < $quantity; $i++) {
                        $currentMonth = ($startMonth + $i - 1) % 12 + 1;
                        $currentYear = now()->year + (int) (($startMonth + $i - 1) / 12);

                        $billingStart = Carbon::createFromDate($currentYear, $currentMonth, 1);
                        $billingEnd = $billingStart->copy()->endOfMonth();

                        $invoiceService->createTuitionInvoiceFromEnrollment(
                            enrollment: $this->record,
                            amount: $planValue,
                            billingPeriodStart: $billingStart,
                            billingPeriodEnd: $billingEnd,
                            companyId: $companyId
                        );

                        $createdCount++;
                    }

                    Notification::make()
                        ->success()
                        ->title('Sucesso!')
                        ->body("{$createdCount} fatura(s) de mensalidade gerada(s) com sucesso")
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Erro!')
                        ->body("Erro ao gerar faturas: {$e->getMessage()}")
                        ->send();
                }
            });
    }
}
