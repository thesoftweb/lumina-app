<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\PaymentService;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Faturas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('faturas')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('faturas')
            ->columns([
                TextColumn::make('number')->label('Número')->sortable()->searchable(),
                TextColumn::make('amount')->label('Valor')->money('BRL')->sortable(),
                TextColumn::make('balance')->label('Saldo')->money('BRL')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable()->searchable(),
                TextColumn::make('due_date')->label('Data de Vencimento')->date('d/M/Y')->sortable(),
                TextColumn::make('updated_at')->label('Data Atualização')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                $this->paymentAction(),
                $this->updateInvoiceAction(),
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
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
                            ->default(fn(Invoice $record) => $record->number),

                        TextInput::make('amount')
                            ->label('Valor Total')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn(Invoice $record) => $record->amount)
                            ->formatStateUsing(fn($value) => $value ? 'R$ ' . number_format($value, 2, ',', '.') : ''),

                        Select::make('status')
                            ->label('Status')
                            ->options(InvoiceStatus::class)
                            ->default(fn(Invoice $record) => $record->status),

                        TextInput::make('balance')
                            ->label('Saldo')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(fn(Invoice $record) => $record->balance)
                            ->formatStateUsing(fn($value) => $value ? 'R$ ' . number_format($value, 2, ',', '.') : ''),

                        DatePicker::make('due_date')
                            ->label('Data de Vencimento')
                            ->required()
                            ->default(fn(Invoice $record) => $record->due_date),

                        TextInput::make('discount_value')
                            ->label('Desconto')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->default(fn(Invoice $record) => $record->discount_value),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull()
                            ->default(fn(Invoice $record) => $record->notes),
                    ]),
            ])
            ->action(function (Invoice $record, array $data) {
                try {
                    $record->update([
                        'due_date' => $data['due_date'],
                        'status' => $data['status'] ?? $record->status,
                        'discount_value' => $data['discount_value'] ?? 0,
                        'notes' => $data['notes'] ?? $record->notes,
                    ]);

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
            ->visible(fn(Invoice $record) => $record->status !== InvoiceStatus::Paid)
            ->schema([
                Section::make('Informações da Fatura')
                    ->disabled()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('number')
                            ->label('Número')
                            ->default(fn(Invoice $record) => $record->number),
                        TextEntry::make('amount')
                            ->label('Valor Total')
                            ->default(fn(Invoice $record) => $record->amount),
                        TextEntry::make('balance')
                            ->label('Saldo Pendente')
                            ->default(fn(Invoice $record) => $record->balance),
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
                    ->hidden(fn(Get $get) => $get('payment_type') === 'cancel')
                    ->schema([
                        TextInput::make('payment_amount')
                            ->label('Valor do Pagamento')
                            ->numeric()
                            ->step('0.01')
                            ->required()
                            ->visible(fn(Get $get) => $get('payment_type') === 'partial')
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
                    ->hidden(fn(Get $get) => $get('payment_type') !== 'cancel')
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
                            ->body("Pagamento de R$ " . number_format($paymentAmount, 2, ',', '.') . " registrado para fatura #{$record->number}")
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
        if (!$enum) {
            return ucfirst($status);
        }

        $icons = [
            InvoiceStatus::Open => '🔴',
            InvoiceStatus::Partial => '🟡',
            InvoiceStatus::Paid => '🟢',
            InvoiceStatus::Canceled => '⚫',
            InvoiceStatus::Overdue => '⚠️',
        ];

        return ($icons[$enum] ?? '') . ' ' . $enum->getLabel();
    }
}
