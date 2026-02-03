<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\Actions\GenerateAsaasChargeAction;
use App\Filament\Resources\Invoices\Actions\SyncAsaasPaymentAction;
use App\Filament\Resources\Invoices\Actions\ViewAsaasLinksAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GenerateAsaasChargeAction::make(),
            SyncAsaasPaymentAction::make(),
            ViewAsaasLinksAction::make(),
            Action::make('back')
                ->label('Voltar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(InvoiceResource::getUrl('index')),
            // EditAction::make(),
        ];
    }
}
