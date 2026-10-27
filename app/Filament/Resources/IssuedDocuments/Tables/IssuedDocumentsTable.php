<?php

namespace App\Filament\Resources\IssuedDocuments\Tables;

use App\Enums\IssuedDocumentStatus;
use App\Enums\IssuedDocumentType;
use App\Models\IssuedDocument;
use App\Services\DocumentIssuer;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class IssuedDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Número')
                    ->weight('medium')
                    ->copyable()
                    ->sortable(['sequence'])
                    // Busca pelo número, pelo nome de quem o documento se refere ou pelo código de verificação
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->where('number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('verification_code', IssuedDocument::normalizeCode($search)))),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray'),
                TextColumn::make('subject')->label('Referente a')->wrap(),
                TextColumn::make('issued_at')->label('Emitido em')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('issuer.name')->label('Emitido por')->placeholder('—')->toggleable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (IssuedDocument $record): IssuedDocumentStatus => $record->status())
                    ->tooltip(fn (IssuedDocument $record): ?string => $record->canceled_at
                        ? "Cancelado em {$record->canceled_at->format('d/m/Y')}: {$record->cancel_reason}"
                        : ($record->valid_until ? "Válido até {$record->valid_until->format('d/m/Y')}" : null)),
            ])
            ->defaultSort('issued_at', 'desc')
            ->searchPlaceholder('Número, nome ou código')
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(IssuedDocumentType::class),
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(IssuedDocumentStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->withStatus(IssuedDocumentStatus::from($data['value']))
                        : $query),
                Filter::make('issued_at')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('from')->label('Emitidos de'),
                        DatePicker::make('until')->label('até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('issued_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('issued_at', '<=', $date))),
            ])
            ->recordUrl(fn (IssuedDocument $record): string => route('issued-documents.print', $record))
            ->openRecordUrlInNewTab()
            ->recordActions([
                Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (IssuedDocument $record): string => route('issued-documents.print', ['issuedDocument' => $record, 'print' => 1]))
                    ->openUrlInNewTab(),
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (IssuedDocument $record): bool => $record->type !== IssuedDocumentType::PickupCard)
                    ->url(fn (IssuedDocument $record): string => route('issued-documents.pdf', ['issuedDocument' => $record, 'download' => 1]))
                    ->openUrlInNewTab(),
                Action::make('verification')
                    ->label('Verificação')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->url(fn (IssuedDocument $record): string => $record->verificationUrl())
                    ->openUrlInNewTab(),
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (IssuedDocument $record): bool => Gate::allows('cancel', $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (IssuedDocument $record): string => "Cancelar {$record->number}")
                    ->modalDescription('O documento continua registrado, mas a verificação passa a informar que ele foi cancelado. Não é possível desfazer.')
                    ->modalSubmitActionLabel('Cancelar documento')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Ex.: emitido com o nome do aluno errado')
                            ->required()
                            ->minLength(10)
                            ->maxLength(500),
                    ])
                    ->action(function (IssuedDocument $record, array $data): void {
                        Gate::authorize('cancel', $record);
                        app(DocumentIssuer::class)->cancel($record, auth()->user(), $data['reason']);
                    })
                    ->successNotificationTitle('Documento cancelado'),
            ]);
    }
}
