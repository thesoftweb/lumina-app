<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->createEnrollmentInvoiceAction(),
            $this->createTuitionInvoiceAction(),
        ];
    }

    /**
     * Action para gerar fatura de matrícula
     */
    private function createEnrollmentInvoiceAction(): Action
    {
        return Action::make('createEnrollmentInvoice')
            ->label('Gerar Fatura de Matrícula')
            ->icon('heroicon-o-document-text')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Gerar Fatura de Matrícula')
            ->modalDescription('Tem certeza que deseja gerar a fatura de matrícula para este aluno?')
            ->modalSubmitActionLabel('Gerar')
            ->action(function (InvoiceService $invoiceService) {
                try {
                    $invoice = $invoiceService->createEnrollmentInvoiceFromEnrollment(
                        enrollment: $this->record,
                        amount: 200, // Ajuste conforme necessário
                        companyId: 1,   // Ajuste conforme necessário
                        notes: "Taxa de matrícula gerada automaticamente"
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
            ->color('info')
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
                                $month => Carbon::createFromDate(now()->year, $month, 1)->locale('pt_BR')->format('F \d\e Y')
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
            ->action(function (InvoiceService $invoiceService, array $data) {
                try {
                    $quantity = (int) $data['quantity'];
                    $startMonth = (int) $data['start_month'];
                    $companyId = (int) $data['company_id'];

                    // Get the plan value from classroom
                    $enrollment = $this->record->load('classroom.plans');
                    $plan = $enrollment->classroom->plans->first();

                    if (!$plan) {
                        Notification::make()
                            ->danger()
                            ->title('Erro!')
                            ->body('Nenhum plano associado à sala de aula')
                            ->send();
                        return;
                    }

                    $planValue = (float) $plan->final_value;
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
                            companyId: $companyId,
                            notes: "Mensalidade gerada automaticamente"
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
