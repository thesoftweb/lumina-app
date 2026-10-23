<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Customer $record): string => CustomerResource::getUrl('edit', ['record' => $record]))
            ->defaultSort('name')
            ->searchPlaceholder('Buscar nome, CPF, e-mail ou telefone')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Nenhum cliente encontrado')
            ->emptyStateDescription('Cadastre um responsável financeiro ou ajuste os filtros da busca.')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'invoices as overdue_invoices_count' => fn (Builder $query) => $query->overdue(),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Cliente')
                    ->weight('medium')
                    ->description(fn (Customer $record): string => $record->email ?: 'E-mail não informado')
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('document')
                    ->label('CPF')
                    ->formatStateUsing(function (?string $state): string {
                        $digits = preg_replace('/\D/', '', $state ?? '');

                        return strlen($digits) === 11
                            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits)
                            : ($state ?: 'Não informado');
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->icon('heroicon-o-phone')
                    ->placeholder('Não informado')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('students_count')
                    ->label('Alunos')->counts('students')->badge()->color('gray')->alignCenter()->sortable(),
                TextColumn::make('overdue_invoices_count')
                    ->label('Faturas vencidas')->badge()->alignCenter()->sortable()
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('asaas_sync_status')
                    ->label('Asaas')->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'synced' => 'Sincronizado', 'failed' => 'Falha', default => 'Pendente',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'synced' => 'success', 'failed' => 'danger', default => 'warning',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->date('d/m/Y')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('has_students')->label('Alunos vinculados')
                    ->placeholder('Todos os clientes')->trueLabel('Com alunos')->falseLabel('Sem alunos')
                    ->queries(
                        true: fn (Builder $query) => $query->has('students'),
                        false: fn (Builder $query) => $query->doesntHave('students'),
                        blank: fn (Builder $query) => $query,
                    ),
                Filter::make('overdue')->label('Com faturas vencidas')
                    ->query(fn (Builder $query) => $query->whereHas('invoices', fn (Builder $query) => $query->overdue())),
                Filter::make('missing_contact')->label('Telefone ou e-mail não informado')
                    ->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                        ->whereNull('phone')->orWhere('phone', '')->orWhereNull('email')->orWhere('email', ''))),
                SelectFilter::make('asaas_sync_status')->label('Sincronização Asaas')
                    ->options(['pending' => 'Pendente', 'synced' => 'Sincronizado', 'failed' => 'Falha']),
                Filter::make('registered')->label('Período de cadastro')
                    ->schema([
                        DatePicker::make('from')->label('Cadastrado a partir de'),
                        DatePicker::make('until')->label('Cadastrado até')->afterOrEqual('from'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        filled($data['from'] ?? null) ? 'Cadastro desde '.Carbon::parse($data['from'])->format('d/m/Y') : null,
                        filled($data['until'] ?? null) ? 'Cadastro até '.Carbon::parse($data['until'])->format('d/m/Y') : null,
                    ])),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
