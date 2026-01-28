<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\AsaasService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;

class ViewAsaasLinksAction
{
    public static function make(): Action
    {
        return Action::make('view_asaas_links')
            ->label('Ver Links de Pagamento')
            ->icon('heroicon-o-link')
            ->color('success')
            ->tooltip('Visualizar boleto, PIX e links de pagamento')
            ->modalHeading('Links de Pagamento Asaas')
            ->slideOver()
            ->closeModalByClickingAway(true)
            ->schema([
                    TextEntry::make('notes')

                ])
            ->action(function (Invoice $record) {
                $asaasService = new AsaasService();
                $viewUrl = $asaasService->getInvoiceViewUrl($record);
                $links = $asaasService->getPaymentLinks($record);

                if (!$viewUrl && !$links) {
                    Notification::make()
                        ->title('Cobrança não disponível')
                        ->body('Não foi possível obter os links de pagamento. Tente sincronizar primeiro.')
                        ->warning()
                        ->send();
                }
            })
            ->visible(fn (Invoice $record) => $record->asaas_invoice_id !== null);
    }
}

