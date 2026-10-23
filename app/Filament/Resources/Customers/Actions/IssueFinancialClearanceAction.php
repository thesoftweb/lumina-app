<?php

namespace App\Filament\Resources\Customers\Actions;

use App\Documents\FinancialClearanceDeclaration;
use App\Filament\Actions\IssueDocumentAction;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

class IssueFinancialClearanceAction
{
    public static function make(): Action
    {
        return IssueDocumentAction::make(
            'issueFinancialClearance',
            'Emitir declaração de quitação',
            fn (array $data, Customer $record) => FinancialClearanceDeclaration::issue(
                $record,
                auth()->user(),
                filled($data['year'] ?? null) ? (int) $data['year'] : null,
                $data['purpose'] ?? null,
                $data['valid_for_days'],
            ),
            fields: [
                Select::make('year')
                    ->label('Período')
                    ->placeholder('Até hoje: sem débitos vencidos')
                    ->options(collect(FinancialClearanceDeclaration::closedYears())->mapWithKeys(
                        fn (string $year, int $key): array => [$key => "Quitação anual de {$year}"],
                    )->all())
                    ->helperText('A quitação anual exige todas as faturas do ano pagas (Lei nº 12.007/2009).'),
            ],
            defaultValidity: 30,
        )
            ->icon('heroicon-o-check-badge')
            // Antecipa se há faturas vencidas: a quitação só considera o que está registrado nas faturas
            ->modalDescription(function (Customer $record): string {
                $pending = FinancialClearanceDeclaration::pendingInvoices($record);

                return ($pending->isEmpty()
                    ? 'Sem faturas vencidas até hoje.'
                    : FinancialClearanceDeclaration::describePending($pending)).' '.IssueDocumentAction::DESCRIPTION;
            });
    }
}
