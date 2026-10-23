<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\Actions\AsaasActionGroup;
use App\Filament\Resources\Invoices\Actions\IssuePaymentReceiptAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Mantida para os links antigos (/faturas/{id}); na listagem o "Visualizar" abre o mesmo
 * resumo num painel lateral e a linha leva direto para a edição.
 */
class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Fatura nº '.($this->getRecord()->number ?? $this->getRecord()->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [
            IssuePaymentReceiptAction::make()->color('gray'),
            AsaasActionGroup::make(),
            EditAction::make()->label('Editar'),
        ];
    }
}
