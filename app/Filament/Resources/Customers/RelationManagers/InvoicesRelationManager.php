<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\Invoices\Actions\IssuePaymentReceiptAction;
use App\Filament\Resources\Invoices\Actions\SyncAsaasPaymentAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Models\Invoice;
use App\Services\AsaasService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
                    ->color(fn (Invoice $record): string => self::isPastDue($record) ? 'danger' : 'gray'),
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
                SelectFilter::make('status')->label('Situação')->options(InvoiceStatus::class),
                Filter::make('past_due')->label('Vencidas com saldo')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [InvoiceStatus::Open->value, InvoiceStatus::Partial->value, InvoiceStatus::Overdue->value])
                        ->whereDate('due_date', '<', today())
                        ->where('balance', '>', 0)),
                Filter::make('due_soon')->label('Vencem nos próximos 7 dias')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [InvoiceStatus::Open->value, InvoiceStatus::Partial->value, InvoiceStatus::Overdue->value])
                        ->whereBetween('due_date', [today(), today()->addDays(7)])
                        ->where('balance', '>', 0)),
                Filter::make('with_balance')->label('Com saldo pendente')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [InvoiceStatus::Open->value, InvoiceStatus::Partial->value, InvoiceStatus::Overdue->value])
                        ->where('balance', '>', 0)),
                SelectFilter::make('billing_type')->label('Tipo de fatura')->options(InvoiceType::class),
                Filter::make('due_period')->label('Período de vencimento')
                    ->schema([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até')->afterOrEqual('from'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        filled($data['from'] ?? null) ? 'Vence a partir de '.Carbon::parse($data['from'])->format('d/m/Y') : null,
                        filled($data['until'] ?? null) ? 'Vence até '.Carbon::parse($data['until'])->format('d/m/Y') : null,
                    ]))),
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
                CreateAction::make()
                    ->label('Nova fatura')
                    ->fillForm(fn (): array => ['customer_id' => $this->getOwnerRecord()->getKey()])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'customer_id' => $this->getOwnerRecord()->getKey(),
                    ]),
                AssociateAction::make()->label('Vincular fatura'),
            ])
            ->recordActions([
                SyncAsaasPaymentAction::make(),
                $this->paymentAction(),
                $this->updateInvoiceAction(),
                ActionGroup::make([
                    IssuePaymentReceiptAction::make(),
                    Action::make('editFullInvoice')
                        ->label('Editar todos os dados')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record])),
                    DissociateAction::make()->label('Desvincular do cliente'),
                    DeleteAction::make()->label('Excluir fatura'),
                ])->label('Mais ações')->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    $this->bulkUpdateInvoicesAction(),
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function isPastDue(Invoice $invoice): bool
    {
        return (float) $invoice->balance > 0
            && in_array($invoice->status, [InvoiceStatus::Open, InvoiceStatus::Partial, InvoiceStatus::Overdue], true)
            && $invoice->due_date?->isBefore(today());
    }

    /**
     * Ação em lote para atualizar vencimento e status das faturas
     */
    private function bulkUpdateInvoicesAction(): BulkAction
    {
        return BulkAction::make('bulkUpdate')
            ->label('Atualizar em Lote')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->form([
                Section::make('Atualizar Faturas')
                    ->columns(2)
                    ->schema([
                        TextInput::make('due_day')
                            ->label('Dia do Vencimento')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->helperText('Deixe em branco para não alterar (ex: 15)'),

                        Select::make('status')
                            ->label('Status')
                            ->options(InvoiceStatus::class)
                            ->helperText('Deixe em branco para não alterar'),
                    ]),
            ])
            ->action(function (Collection $records, array $data) {
                try {
                    $updateData = [];

                    if (! empty($updateData) || ($data['due_day'] ?? null) || ($data['status'] ?? null)) {
                        $records->each(function (Invoice $record) use ($data, &$updateData) {
                            $recordUpdateData = [];

                            // Se o usuário forneceu um novo dia
                            if ($data['due_day'] ?? null) {
                                $day = (int) $data['due_day'];
                                $month = $record->due_date->month;
                                $year = $record->due_date->year;

                                // Valida se o dia é válido para o mês
                                try {
                                    $newDate = Carbon::createFromDate($year, $month, $day);
                                    $recordUpdateData['due_date'] = $newDate;
                                } catch (\Exception $e) {
                                    throw new \Exception("Dia {$day} inválido para {$month}/{$year}");
                                }
                            }

                            if ($data['status'] ?? null) {
                                $recordUpdateData['status'] = $data['status'];
                            }

                            if (! empty($recordUpdateData)) {
                                $record->update($recordUpdateData);
                            }
                        });

                        Notification::make()
                            ->success()
                            ->title('Faturas Atualizadas')
                            ->body("Foram atualizadas {$records->count()} fatura(s) com sucesso")
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Nenhuma Alteração')
                            ->body('Selecione pelo menos um campo para atualizar')
                            ->send();
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Erro!')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /**
     * Ação para atualizar informações da fatura
     */
    private function updateInvoiceAction(): Action
    {
        return Action::make('update')
            ->label('Atualizar')
            ->icon('heroicon-o-pencil')
            ->color('info')
            ->slideOver()
            ->schema([
                Section::make('Informações da Fatura')
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Número da Fatura')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn (Invoice $record) => $record->number),

                        TextInput::make('amount')
                            ->label('Valor Total')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn (Invoice $record) => $record->amount)
                            ->formatStateUsing(fn ($value) => $value ? 'R$ '.number_format($value, 2, ',', '.') : ''),

                        Select::make('status')
                            ->label('Status')
                            ->options(InvoiceStatus::class)
                            ->default(fn (Invoice $record) => $record->status),

                        TextInput::make('balance')
                            ->label('Saldo')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn (Invoice $record) => $record->balance)
                            ->formatStateUsing(fn ($value) => $value ? 'R$ '.number_format($value, 2, ',', '.') : ''),

                        TextInput::make('due_day')
                            ->label('Data de Vencimento')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn (Invoice $record) => $record->due_date->format('d/m/Y')),

                        DatePicker::make('due_date')
                            ->label('Nova Data de Vencimento')
                            ->default(fn (Invoice $record) => $record->due_date),

                        TextInput::make('discount_value')
                            ->label('Desconto')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->default(fn (Invoice $record) => $record->discount_value),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull()
                            ->default(fn (Invoice $record) => $record->notes),
                    ]),
            ])
            ->action(function (Invoice $record, array $data) {
                try {
                    $updateData = [];

                    // Se o usuário forneceu uma nova data completa
                    if ($data['due_date'] ?? null) {
                        $updateData['due_date'] = $data['due_date'];
                    }

                    if ($data['status'] ?? null) {
                        $updateData['status'] = $data['status'];
                    }

                    if ($data['discount_value'] ?? null) {
                        $updateData['discount_value'] = $data['discount_value'];
                    }

                    if ($data['notes'] ?? null) {
                        $updateData['notes'] = $data['notes'];
                    }

                    $record->update($updateData);

                    Notification::make()
                        ->success()
                        ->title('Fatura Atualizada')
                        ->body("Fatura #{$record->number} foi atualizada com sucesso")
                        ->send();

                    // Recarrega o registro
                    $record->refresh();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Erro!')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /**
     * Ação para registrar pagamentos e atualizar status
     */
    private function paymentAction(): Action
    {
        return Action::make('payment')
            ->label('Pagamento')
            ->icon('heroicon-o-credit-card')
            ->color('success')
            ->slideOver()
            ->visible(fn (Invoice $record) => $record->status !== InvoiceStatus::Paid)
            ->schema([
                Section::make('Informações da Fatura')
                    ->disabled()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('number')
                            ->label('Número')
                            ->default(fn (Invoice $record) => $record->number),
                        TextEntry::make('amount')
                            ->label('Valor Total')
                            ->default(fn (Invoice $record) => $record->amount),
                        TextEntry::make('balance')
                            ->label('Saldo Pendente')
                            ->default(fn (Invoice $record) => $record->balance),
                        TextEntry::make('status')
                            ->label('Status Atual')
                            ->disabled(),
                    ]),

                Radio::make('payment_type')
                    ->label('Tipo de Pagamento')
                    ->options([
                        'full' => 'Pagar Tudo',
                        'partial' => 'Pagamento Parcial',
                        'cancel' => 'Cancelar Fatura',
                    ])
                    ->required()
                    ->default('full')
                    ->live(),

                Section::make('Detalhes do Pagamento')
                    ->hidden(fn (Get $get) => $get('payment_type') === 'cancel')
                    ->schema([
                        TextInput::make('payment_amount')
                            ->label('Valor do Pagamento')
                            ->numeric()
                            ->step('0.01')
                            ->required()
                            ->visible(fn (Get $get) => $get('payment_type') === 'partial')
                            ->minValue(0.01),

                        Select::make('payment_method')
                            ->label('Método de Pagamento')
                            ->options([
                                'pix' => 'PIX',
                                'boleto' => 'Boleto',
                                'credit_card' => 'Cartão de Crédito',
                                'debit_card' => 'Cartão de Débito',
                                'transfer' => 'Transferência Bancária',
                                'cash' => 'Dinheiro',
                                'check' => 'Cheque',
                                'other' => 'Outro',
                            ])
                            ->required()
                            ->default('other'),

                        TextInput::make('reference')
                            ->label('Referência / Comprovante')
                            ->placeholder('Ex: ID do PIX, número do boleto, etc.')
                            ->maxLength(100),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->placeholder('Notas adicionais sobre o pagamento')
                            ->rows(3),
                    ]),

                Section::make('Confirmação de Cancelamento')
                    ->hidden(fn (Get $get) => $get('payment_type') !== 'cancel')
                    ->schema([
                        Textarea::make('cancel_notes')
                            ->label('Motivo do Cancelamento')
                            ->placeholder('Por que a fatura está sendo cancelada?')
                            ->rows(3)
                            ->required(),
                    ]),
            ])
            ->action(function (Invoice $record, array $data, PaymentService $paymentService) {
                try {
                    $paymentType = $data['payment_type'];

                    if ($paymentType === 'cancel') {
                        $paymentService->cancelInvoice(
                            invoice: $record,
                            notes: $data['cancel_notes'] ?? ''
                        );

                        Notification::make()
                            ->success()
                            ->title('Fatura Cancelada')
                            ->body("Fatura #{$record->number} foi cancelada com sucesso")
                            ->send();
                    } elseif ($paymentType === 'full') {
                        $paymentService->payFullInvoice(
                            invoice: $record,
                            paymentMethod: $data['payment_method'],
                            reference: $data['reference'] ?? null,
                            notes: $data['notes'] ?? 'Pagamento total'
                        );

                        Notification::make()
                            ->success()
                            ->title('Pagamento Registrado')
                            ->body("Fatura #{$record->number} foi paga com sucesso")
                            ->send();
                    } elseif ($paymentType === 'partial') {
                        $paymentAmount = (float) $data['payment_amount'];

                        $paymentService->payPartialInvoice(
                            invoice: $record,
                            amount: $paymentAmount,
                            paymentMethod: $data['payment_method'],
                            reference: $data['reference'] ?? null,
                            notes: $data['notes'] ?? 'Pagamento parcial'
                        );

                        Notification::make()
                            ->success()
                            ->title('Pagamento Parcial Registrado')
                            ->body('Pagamento de R$ '.number_format($paymentAmount, 2, ',', '.')." registrado para fatura #{$record->number}")
                            ->send();
                    }

                    // Recarrega o registro
                    $record->refresh();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Erro!')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /**
     * Formata o status para exibição usando a enum
     */
    private function formatStatus(string $status): string
    {
        $enum = InvoiceStatus::tryFrom($status);
        if (! $enum) {
            return ucfirst($status);
        }

        $icons = [
            InvoiceStatus::Open => '🔴',
            InvoiceStatus::Partial => '🟡',
            InvoiceStatus::Paid => '🟢',
            InvoiceStatus::Canceled => '⚫',
            InvoiceStatus::Overdue => '⚠️',
        ];

        return ($icons[$enum] ?? '').' '.$enum->getLabel();
    }
}
