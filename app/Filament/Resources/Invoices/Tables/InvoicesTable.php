<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\AsaasChargeState;
use App\Filament\Resources\Invoices\Actions\CancelInvoiceAction;
use App\Filament\Resources\Invoices\Actions\DeleteInvoiceActions;
use App\Filament\Resources\Invoices\Actions\ReceivePaymentAction;
use App\Filament\Resources\Invoices\Actions\RestoreInvoiceAction;
use App\Filament\Resources\Invoices\Actions\UndoPaymentAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // A linha abre direto a edição; o resumo fica no "Visualizar" (painel lateral)
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'enrollment.student', 'enrollment.classroom']))
            ->defaultSort('due_date', 'desc')
            ->searchPlaceholder('Buscar número, responsável ou aluno')
            ->emptyStateHeading('Nenhuma fatura encontrada')
            ->emptyStateDescription('Crie uma fatura ou ajuste a busca, a aba e os filtros aplicados.')
            ->columns([
                TextColumn::make('number')
                    ->label('Nº')
                    ->weight(FontWeight::Medium)
                    ->placeholder('Sem número')
                    ->searchable(['number', 'reference'])
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Responsável')
                    ->description(fn (Invoice $record): ?string => $record->enrollment?->student?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('customer', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('enrollment.student', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%")))
                    ->sortable()
                    ->wrap(),
                TextColumn::make('competence')
                    ->label('Competência')
                    ->state(fn (Invoice $record): ?string => $record->competenceLabel())
                    ->description(fn (Invoice $record): ?string => $record->billing_type?->getLabel())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw('coalesce(billing_period_start, due_date) '.($direction === 'asc' ? 'asc' : 'desc'))),
                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Invoice $record): ?string => $record->isPastDue() ? 'danger' : null)
                    ->weight(fn (Invoice $record): ?FontWeight => $record->isPastDue() ? FontWeight::SemiBold : null)
                    ->icon(fn (Invoice $record): ?Heroicon => $record->isPastDue() ? Heroicon::OutlinedExclamationCircle : null)
                    ->tooltip(fn (Invoice $record): ?string => $record->isPastDue()
                        ? 'Vencida há '.(int) $record->due_date->diffInDays(today()).' dia(s)'
                        : null),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable()
                    ->alignEnd()
                    ->color(fn (Invoice $record): string => (float) $record->balance > 0 ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),
                IconColumn::make('asaas_state')
                    ->label('Asaas')
                    ->state(fn (Invoice $record): AsaasChargeState => AsaasChargeState::of($record))
                    ->tooltip(fn (Invoice $record): string => AsaasChargeState::of($record)->getLabel())
                    ->alignCenter(),

                TextColumn::make('reference')
                    ->label('Referência')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('billing_type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('enrollment.classroom.name')
                    ->label('Turma')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issue_date')
                    ->label('Emissão')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('asaas_invoice_id')
                    ->label('ID no Asaas')
                    ->placeholder('-')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                InvoiceFilters::status(),
                InvoiceFilters::billingType(),
                InvoiceFilters::classroom(),
                InvoiceFilters::duePeriod(),
                InvoiceFilters::dueSoon(),
                InvoiceFilters::withBalance(),
                InvoiceFilters::withoutAsaasCharge(),
                InvoiceFilters::asaasSyncFailed(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    // Com modal() a ação não vira link para a página de visualizar
                    ->modal()
                    ->slideOver()
                    ->modalHeading(fn (Invoice $record): string => 'Fatura nº '.($record->number ?? $record->getKey()))
                    ->modalDescription(fn (Invoice $record): ?string => $record->customer?->name)
                    ->extraModalFooterActions(fn (Invoice $record): array => [
                        Action::make('openEditPage')
                            ->label('Abrir fatura')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->url(InvoiceResource::getUrl('edit', ['record' => $record])),
                    ]),
                EditAction::make()->label('Editar')->color('gray'),
                // Operações da fatura (as mesmas do cabeçalho da edição), refletidas no Asaas
                ActionGroup::make([
                    ReceivePaymentAction::make(),
                    UndoPaymentAction::make(),
                    CancelInvoiceAction::make(),
                    RestoreInvoiceAction::make(),
                ])->label('Operações')->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteInvoiceActions::bulk(),
                ]),
            ]);
    }
}
