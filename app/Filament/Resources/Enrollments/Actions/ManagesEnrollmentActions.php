<?php

namespace App\Filament\Resources\Enrollments\Actions;

use App\Documents\AttendanceDeclaration;
use App\Documents\EnrollmentDeclaration;
use App\Enums\EnrollmentStatus;
use App\Filament\Actions\IssueDocumentAction;
use App\Services\Asaas\ChargeOperationException;
use App\Services\Asaas\ChargeOperations;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

trait ManagesEnrollmentActions
{
    protected function getEnrollmentActions(): array
    {
        return [
            $this->createEnrollmentContractAction(),
            $this->createEnrollmentDeclarationAction(),
            $this->createAttendanceDeclarationAction(),
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
     * Action para imprimir o contrato de matrícula (abre já com a janela de impressão/salvar PDF)
     */
    private function createEnrollmentContractAction(): Action
    {
        return Action::make('printEnrollmentContract')
            ->label('Imprimir contrato')
            ->icon('heroicon-o-document-text')
            ->url(fn () => route('enrollments.contract', ['enrollment' => $this->record, 'print' => 1]))
            ->openUrlInNewTab();
    }

    /**
     * Declarações escolares (numeradas e com código de verificação), só para matrícula ativa
     */
    private function createEnrollmentDeclarationAction(): Action
    {
        return IssueDocumentAction::make(
            'issueEnrollmentDeclaration',
            'Emitir declaração de matrícula',
            fn (array $data) => EnrollmentDeclaration::issue($this->record, auth()->user(), $data['purpose'] ?? null, $data['valid_for_days']),
        )->visible(fn (): bool => EnrollmentDeclaration::canIssue($this->record));
    }

    private function createAttendanceDeclarationAction(): Action
    {
        return IssueDocumentAction::make(
            'issueAttendanceDeclaration',
            'Emitir declaração de frequência',
            fn (array $data) => AttendanceDeclaration::issue($this->record, auth()->user(), $data['purpose'] ?? null, $data['valid_for_days']),
        )
            ->visible(fn (): bool => AttendanceDeclaration::canIssue($this->record))
            // Mostra a frequência apurada antes de emitir, para conferir se as chamadas estão em dia
            ->modalDescription(fn (): string => AttendanceDeclaration::describe(AttendanceDeclaration::summary($this->record)).' '.IssueDocumentAction::DESCRIPTION);
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
            ->action(function (InvoiceService $invoiceService, ChargeOperations $operations, array $data) {
                try {
                    // A fatura nasce com a cobrança no Asaas; se o Asaas recusar, nada é criado
                    $invoice = $operations->createInvoice(fn () => $invoiceService->createEnrollmentInvoiceFromEnrollment(
                        enrollment: $this->record,
                        amount: (float) $data['enrollment_amount'],
                        companyId: 1,
                        notes: 'Taxa de matrícula pelo sistema'
                    ));

                    Notification::make()
                        ->success()
                        ->title('Sucesso!')
                        ->body("Fatura #{$invoice->number} gerada com sucesso, com a cobrança no Asaas")
                        ->send();
                } catch (\Exception $e) {
                    // A transação desfez a marcação da taxa de matrícula; o modelo em memória também
                    $this->record->refresh();

                    Notification::make()
                        ->danger()
                        ->title('A fatura não foi gerada')
                        ->body($e->getMessage())
                        ->persistent()
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
            ->action(function (InvoiceService $invoiceService, ChargeOperations $operations, array $data) {
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
                    $failures = [];

                    // Cada mês é criado com a sua cobrança no Asaas, ou não é criado (sem meio-termo)
                    for ($i = 0; $i < $quantity; $i++) {
                        $currentMonth = ($startMonth + $i - 1) % 12 + 1;
                        $currentYear = now()->year + (int) (($startMonth + $i - 1) / 12);

                        $billingStart = Carbon::createFromDate($currentYear, $currentMonth, 1)->startOfDay();
                        $billingEnd = $billingStart->copy()->endOfMonth();

                        try {
                            $operations->createInvoice(fn () => $invoiceService->createTuitionInvoiceFromEnrollment(
                                enrollment: $this->record,
                                amount: $planValue,
                                billingPeriodStart: $billingStart,
                                billingPeriodEnd: $billingEnd,
                                companyId: $companyId
                            ));

                            $createdCount++;
                        } catch (ChargeOperationException $e) {
                            $failures[] = $billingStart->locale('pt_BR')->translatedFormat('F/Y').': '.$e->getMessage();
                        }
                    }

                    $this->record->refresh();

                    if ($failures === []) {
                        Notification::make()
                            ->success()
                            ->title('Sucesso!')
                            ->body("{$createdCount} fatura(s) de mensalidade gerada(s) com sucesso, com as cobranças no Asaas")
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->color($createdCount > 0 ? 'warning' : 'danger')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->title($createdCount > 0
                            ? "{$createdCount} mensalidade(s) gerada(s); ".count($failures).' não'
                            : 'Nenhuma mensalidade foi gerada')
                        ->body(collect(['Não geradas (nem no sistema, nem no Asaas):', ...$failures])->implode("\n"))
                        ->persistent()
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
