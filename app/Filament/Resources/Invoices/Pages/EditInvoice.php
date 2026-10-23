<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\Actions\AsaasActionGroup;
use App\Filament\Resources\Invoices\Actions\IssuePaymentReceiptAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/** Página principal da fatura (a linha da listagem abre aqui). */
class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Fatura nº '.($this->getRecord()->number ?? $this->getRecord()->getKey());
    }

    /** "Maria Responsável · Ana Clara · outubro/2026" */
    public function getSubheading(): ?string
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return collect([
            $invoice->customer?->name,
            $invoice->enrollment?->student?->name,
            $invoice->competenceLabel(),
        ])->filter()->implode(' · ') ?: null;
    }

    /**
     * Cabeçalho: documento (recibo), Asaas (grupo) e "mais ações".
     * As operações financeiras das próximas etapas (receber, cancelar...) entram no grupo "Mais ações"
     * ou num grupo próprio ao lado do Asaas.
     */
    protected function getHeaderActions(): array
    {
        return [
            IssuePaymentReceiptAction::make()->color('gray'),
            AsaasActionGroup::make(),
            ActionGroup::make([
                ViewAction::make()
                    ->label('Ver resumo')
                    ->icon(Heroicon::OutlinedEye)
                    ->modal()
                    ->slideOver()
                    ->modalHeading(fn (Invoice $record): string => 'Fatura nº '.($record->number ?? $record->getKey())),
                DeleteAction::make()->label('Excluir fatura'),
            ])
                ->label('Mais ações')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
