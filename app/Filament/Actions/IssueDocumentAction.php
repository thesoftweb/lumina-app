<?php

namespace App\Filament\Actions;

use App\Models\IssuedDocument;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Ação de emissão de documento (declarações e recibos): finalidade e validade opcionais, aviso quando a
 * emissão é recusada e notificação com o link de impressão quando dá certo.
 */
class IssueDocumentAction
{
    public const DESCRIPTION = 'O documento recebe um número e um código de verificação. Depois de emitido, não pode ser alterado; se houver erro, o admin o cancela e um novo é emitido.';

    /**
     * @param  Closure(array $data): IssuedDocument  $issue  emite com os dados do formulário ($data; $record também é injetado); ValidationException vira aviso
     * @param  array<int, Component>  $fields  campos antes da finalidade e da validade
     * @param  bool  $withPurposeAndValidity  recibos não têm finalidade nem validade
     */
    public static function make(string $name, string $label, Closure $issue, array $fields = [], ?int $defaultValidity = 90, bool $withPurposeAndValidity = true): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-document-check')
            ->authorize(fn (): bool => Gate::allows('create', IssuedDocument::class))
            ->modalHeading($label)
            ->modalDescription(self::DESCRIPTION)
            ->modalSubmitActionLabel('Emitir')
            ->schema([
                ...$fields,
                ...($withPurposeAndValidity ? [
                    TextInput::make('purpose')
                        ->label('Finalidade')
                        ->placeholder('Ex.: transporte escolar')
                        ->helperText('Opcional. Sai no texto como "para fins de ...".')
                        ->maxLength(150),
                    Select::make('valid_for_days')
                        ->label('Validade')
                        ->options([30 => '30 dias', 60 => '60 dias', 90 => '90 dias'])
                        ->placeholder('Sem prazo de validade')
                        ->default($defaultValidity),
                ] : []),
            ])
            ->action(function (array $data, Action $action) use ($issue): void {
                $data['valid_for_days'] = filled($data['valid_for_days'] ?? null) ? (int) $data['valid_for_days'] : null;

                try {
                    // O Filament injeta pelo nome: $data, $record, ...
                    $document = $action->evaluate($issue, ['data' => $data]);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Documento não emitido')->body(collect($e->errors())->flatten()->first())->persistent()->send();
                    $action->halt();

                    return;
                }

                // A emissão pode devolver um documento que já existia (2ª via de recibo)
                Notification::make()
                    ->status($document->wasRecentlyCreated ? 'success' : 'info')
                    ->title($document->wasRecentlyCreated ? 'Documento emitido' : 'Documento já emitido')
                    ->body($document->wasRecentlyCreated
                        ? "{$document->type->getLabel()} nº {$document->number}"
                        : "{$document->type->getLabel()} nº {$document->number}, emitido em {$document->issued_at->format('d/m/Y')}. Abra para imprimir a 2ª via.")
                    ->actions([
                        Action::make('open')
                            ->label('Abrir para imprimir')
                            ->button()
                            ->url(route('issued-documents.print', ['issuedDocument' => $document, 'print' => 1]), shouldOpenInNewTab: true),
                        Action::make('download')
                            ->label('Baixar PDF')
                            ->link()
                            ->url(route('issued-documents.pdf', ['issuedDocument' => $document, 'download' => 1]), shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();
            });
    }
}
