<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Invoices\Actions\CancelInvoiceAction;
use App\Filament\Resources\Invoices\Actions\DeleteInvoiceActions;
use App\Filament\Resources\Invoices\Actions\EditChargeAction;
use App\Filament\Resources\Invoices\Actions\IssuePaymentReceiptAction;
use App\Filament\Resources\Invoices\Actions\ReceivePaymentAction;
use App\Filament\Resources\Invoices\Actions\RestoreInvoiceAction;
use App\Filament\Resources\Invoices\Actions\SyncAsaasPaymentAction;
use App\Filament\Resources\Invoices\Actions\UndoPaymentAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Tables\InvoiceFilters;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperationException;
use App\Services\Asaas\ChargeOperations;
use App\Services\AsaasService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Aba Faturas do responsável. Criar, receber, editar, desfazer, cancelar e restaurar passam pelas
 * mesmas operações da tela de faturas (ChargeOperations), que refletem tudo no Asaas.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Faturas';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-banknotes';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->invoices()->count();
    }

    public function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('due_date', 'desc')
            ->searchPlaceholder('Buscar número ou referência')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading('Nenhuma fatura encontrada')
            ->emptyStateDescription('Crie uma fatura ou ajuste os filtros aplicados.')
            ->columns([
                TextColumn::make('number')
                    ->label('Fatura')
                    ->weight('medium')
                    ->placeholder('Sem número')
                    ->description(fn (Invoice $record): ?string => $record->reference ?: null)
                    ->searchable(['number', 'reference'])
                    ->sortable(),
                TextColumn::make('billing_type')
                    ->label('Tipo')->badge()->sortable()->toggleable(),
                TextColumn::make('due_date')
                    ->label('Vencimento')->date('d/m/Y')->sortable()
                    ->color(fn (Invoice $record): string => $record->isPastDue() ? 'danger' : 'gray'),
                TextColumn::make('status')
                    ->label('Situação')->badge()->sortable(),
                TextColumn::make('amount')
                    ->label('Valor')->money('BRL')->sortable()->alignEnd(),
                TextColumn::make('balance')
                    ->label('Saldo')->money('BRL')->sortable()->alignEnd()
                    ->color(fn (Invoice $record): string => (float) $record->balance > 0 ? 'warning' : 'gray'),
                TextColumn::make('issue_date')
                    ->label('Emissão')->date('d/m/Y')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                InvoiceFilters::status(),
                InvoiceFilters::pastDue(),
                InvoiceFilters::dueSoon(),
                InvoiceFilters::withBalance(),
                InvoiceFilters::billingType(),
                InvoiceFilters::duePeriod(),
            ])
            ->headerActions([
                Action::make('syncCustomerInvoices')
                    ->label('Sincronizar com Asaas')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar faturas com o Asaas?')
                    ->modalDescription('Consulta o status atual das cobranças vinculadas ao Asaas e atualiza as faturas deste cliente.')
                    ->modalSubmitActionLabel('Sincronizar agora')
                    ->action(function (AsaasService $asaasService): void {
                        $query = $this->getOwnerRecord()->invoices()
                            ->whereNotNull('asaas_invoice_id')
                            ->where('asaas_invoice_id', '!=', '');
                        $eligible = (clone $query)->count();

                        if ($eligible === 0) {
                            Notification::make()->warning()
                                ->title('Nenhuma cobrança vinculada ao Asaas')
                                ->body('As faturas deste cliente ainda não têm um ID de cobrança no Asaas.')
                                ->send();

                            return;
                        }

                        $synced = 0;
                        $failed = 0;

                        $query->chunkById(50, function ($invoices) use ($asaasService, &$synced, &$failed): void {
                            foreach ($invoices as $invoice) {
                                if ($asaasService->syncPaymentStatus($invoice)) {
                                    $synced++;
                                } else {
                                    $failed++;
                                }
                            }
                        });

                        Notification::make()
                            ->title('Sincronização concluída')
                            ->body("{$synced} fatura(s) sincronizada(s); {$failed} com falha.")
                            ->color($failed > 0 ? 'warning' : 'success')
                            ->send();
                    }),
                // A fatura nasce com a cobrança no Asaas; se o Asaas recusar, não é criada
                CreateAction::make()
                    ->label('Nova fatura')
                    ->fillForm(fn (): array => ['customer_id' => $this->getOwnerRecord()->getKey()])
                    ->using(function (CreateAction $action, array $data, ChargeOperations $operations): Invoice {
                        try {
                            return $operations->createInvoice(fn (): Invoice => Invoice::create([
                                ...$data,
                                'customer_id' => $this->getOwnerRecord()->getKey(),
                                'balance' => $data['amount'],
                                'status' => 'open',
                            ]));
                        } catch (ChargeOperationException $e) {
                            Notification::make()->danger()
                                ->title('A fatura não foi criada')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();

                            $action->halt();
                        }
                    }),
                // Só faturas sem cobrança no Asaas: a cobrança não muda de cliente
                AssociateAction::make()
                    ->label('Vincular fatura')
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->whereNull('asaas_invoice_id')),
            ])
            ->recordActions([
                SyncAsaasPaymentAction::make(),
                ReceivePaymentAction::make(),
                EditChargeAction::make(),
                ActionGroup::make([
                    IssuePaymentReceiptAction::make(),
                    Action::make('editFullInvoice')
                        ->label('Editar todos os dados')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record])),
                    UndoPaymentAction::make(),
                    CancelInvoiceAction::make(),
                    RestoreInvoiceAction::make(),
                    DeleteInvoiceActions::single(),
                ])->label('Mais ações')->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    $this->bulkDueDayAction(),
                    DeleteInvoiceActions::bulk(),
                ]),
            ]);
    }

    /**
     * Muda o dia de vencimento das faturas selecionadas (mesmo mês), uma a uma pela operação de
     * editar: as que têm cobrança são alteradas no Asaas; as recusadas ficam como estavam.
     */
    private function bulkDueDayAction(): BulkAction
    {
        return BulkAction::make('bulkDueDay')
            ->label('Alterar dia de vencimento')
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->schema([
                TextInput::make('due_day')
                    ->label('Novo dia de vencimento')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->required()
                    ->helperText('Só faturas abertas ou vencidas e sem pagamentos. Em meses mais curtos, vale o último dia.'),
            ])
            ->action(function (Collection $records, array $data, ChargeOperations $operations): void {
                $updated = 0;
                $failures = [];

                foreach ($records as $invoice) {
                    $label = 'nº '.($invoice->number ?? $invoice->getKey());

                    if (! ChargeOperations::canEditCharge($invoice)) {
                        $failures[] = "{$label}: paga, parcial ou cancelada";

                        continue;
                    }

                    $dueDate = $invoice->due_date->copy()->day(min((int) $data['due_day'], $invoice->due_date->daysInMonth));

                    try {
                        $operations->update($invoice, ['due_date' => $dueDate->toDateString()]);
                        $updated++;
                    } catch (ChargeOperationException $e) {
                        $failures[] = "{$label}: {$e->getMessage()}";
                    }
                }

                Notification::make()
                    ->title($failures === [] ? 'Vencimentos alterados' : 'Alguns vencimentos não foram alterados')
                    ->body(collect(["{$updated} fatura(s) alterada(s).", ...$failures])->implode("\n"))
                    ->color($failures === [] ? 'success' : 'warning')
                    ->persistent($failures !== [])
                    ->send();
            });
    }
}
