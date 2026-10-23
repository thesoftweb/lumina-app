<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Documents\PickupCard;
use App\Filament\Actions\IssueDocumentAction;
use App\Models\IssuedDocument;
use App\Models\StudentPickupAuthorization;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Pessoas autorizadas a buscar o aluno e suas carteirinhas. */
class PickupAuthorizationsRelationManager extends RelationManager
{
    protected static string $relationship = 'pickupAuthorizations';

    protected static ?string $title = 'Autorizados a buscar';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-identification';

    protected static ?string $modelLabel = 'pessoa autorizada';

    protected static ?string $pluralModelLabel = 'pessoas autorizadas';

    public const RELATIONSHIPS = ['Mãe', 'Pai', 'Avó', 'Avô', 'Tia', 'Tio', 'Irmã', 'Irmão', 'Madrinha', 'Padrinho', 'Motorista do transporte escolar', 'Outro'];

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nome completo')->required()->maxLength(255)->columnSpanFull(),
            Select::make('relationship')
                ->label('Parentesco ou vínculo')
                ->options(array_combine(self::RELATIONSHIPS, self::RELATIONSHIPS))
                ->required()
                ->native(false),
            TextInput::make('document')
                ->label('Documento (RG ou CPF)')
                ->helperText('A portaria confere com o documento com foto.')
                ->required()
                ->maxLength(30),
            TextInput::make('phone')->label('Telefone')->mask('(99) 99999-9999')->maxLength(20),
            Textarea::make('notes')->label('Observações')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Cada pessoa autorizada recebe uma carteirinha com a foto do aluno, válida até o fim do ano letivo. Revogar a autorização ou alterar nome, documento ou parentesco cancela a carteirinha.')
            ->emptyStateHeading('Nenhuma pessoa autorizada')
            ->emptyStateDescription('Cadastre quem pode buscar o aluno na escola para emitir as carteirinhas.')
            ->columns([
                TextColumn::make('name')->label('Nome')->weight('medium')->searchable()
                    ->description(fn (StudentPickupAuthorization $record): ?string => $record->notes),
                TextColumn::make('relationship')->label('Vínculo'),
                TextColumn::make('document')->label('Documento')->placeholder('—'),
                TextColumn::make('phone')->label('Telefone')->placeholder('—')->toggleable(),
                TextColumn::make('card')
                    ->label('Carteirinha')
                    ->state(fn (StudentPickupAuthorization $record): ?string => PickupCard::existing($record)?->number)
                    ->placeholder('Não emitida')
                    ->url(fn (StudentPickupAuthorization $record): ?string => ($card = PickupCard::existing($record))
                        ? route('issued-documents.print', $card)
                        : null)
                    ->openUrlInNewTab()
                    ->color('primary'),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (StudentPickupAuthorization $record): string => $record->isRevoked() ? 'Revogada' : 'Ativa')
                    ->color(fn (string $state): string => $state === 'Ativa' ? 'success' : 'gray')
                    ->tooltip(fn (StudentPickupAuthorization $record): ?string => $record->isRevoked()
                        ? 'Revogada em '.$record->revoked_at->format('d/m/Y').($record->revoked_reason ? ": {$record->revoked_reason}" : '')
                        : null),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make()->label('Adicionar pessoa'),
                $this->printCardsAction('printAllCards', 'Imprimir carteirinhas')
                    ->visible(fn (): bool => $this->getOwnerRecord()->pickupAuthorizations()->active()->exists())
                    ->action(fn () => $this->issueAndPrint($this->getOwnerRecord()->pickupAuthorizations()->active()->get())),
            ])
            ->recordActions([
                IssueDocumentAction::make(
                    'issuePickupCard',
                    'Emitir carteirinha',
                    fn (StudentPickupAuthorization $record) => PickupCard::issue($record, auth()->user()),
                    withPurposeAndValidity: false,
                )
                    ->icon('heroicon-o-identification')
                    ->modalDescription('A carteirinha vale até o fim do ano letivo da matrícula ativa e traz a foto do aluno cadastrada agora. Se a pessoa já tem carteirinha válida, ela é reaberta para a 2ª via.')
                    ->visible(fn (StudentPickupAuthorization $record): bool => ! $record->isRevoked()),
                EditAction::make()
                    ->visible(fn (StudentPickupAuthorization $record): bool => ! $record->isRevoked())
                    ->modalDescription('Alterar nome, documento ou parentesco cancela a carteirinha emitida; será preciso emitir uma nova.'),
                Action::make('revoke')
                    ->label('Revogar')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (StudentPickupAuthorization $record): bool => ! $record->isRevoked() && Gate::allows('update', $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (StudentPickupAuthorization $record): string => "Revogar a autorização de {$record->name}")
                    ->modalDescription('A pessoa deixa de poder buscar o aluno e a carteirinha é cancelada: o QR code passa a mostrar que ela não vale mais. Recolha a carteirinha, se possível.')
                    ->modalSubmitActionLabel('Revogar')
                    ->schema([Textarea::make('reason')->label('Motivo')->placeholder('Ex.: pedido da mãe em 23/10')->maxLength(500)])
                    ->action(fn (StudentPickupAuthorization $record, array $data) => PickupCard::revoke($record, $data['reason'] ?? null))
                    ->successNotificationTitle('Autorização revogada'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('printSelectedCards')
                    ->label('Imprimir carteirinhas')
                    ->icon('heroicon-o-printer')
                    ->authorize(fn (): bool => Gate::allows('create', IssuedDocument::class))
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $this->issueAndPrint($records->reject->isRevoked())),
            ]);
    }

    private function printCardsAction(string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->authorize(fn (): bool => Gate::allows('create', IssuedDocument::class))
            ->requiresConfirmation()
            ->modalHeading('Imprimir as carteirinhas')
            ->modalDescription('Emite a carteirinha de quem ainda não tem e abre todas numa folha A4 para recortar, até 4 por folha.')
            ->modalSubmitActionLabel('Emitir e imprimir');
    }

    /** Emite o que falta (ou reaproveita a carteirinha válida) e oferece a folha de impressão. */
    private function issueAndPrint(iterable $authorizations): void
    {
        $cards = collect();
        $errors = collect();

        foreach ($authorizations as $authorization) {
            try {
                $cards->push(PickupCard::issue($authorization, auth()->user()));
            } catch (ValidationException $e) {
                $errors->push("{$authorization->name}: ".collect($e->errors())->flatten()->first());
            }
        }

        if ($cards->isEmpty()) {
            Notification::make()->danger()->title('Nenhuma carteirinha emitida')->body($errors->implode(' ') ?: 'Selecione pessoas com autorização ativa.')->persistent()->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($cards->count() === 1 ? '1 carteirinha pronta' : "{$cards->count()} carteirinhas prontas")
            ->body($errors->isEmpty() ? 'Abra a folha para imprimir.' : 'Não emitidas: '.$errors->implode(' '))
            ->actions([
                Action::make('open')
                    ->label('Abrir para imprimir')
                    ->button()
                    ->url(route('pickup-cards.print', ['ids' => $cards->pluck('id')->all(), 'print' => 1]), shouldOpenInNewTab: true),
            ])
            ->persistent()
            ->send();
    }
}
