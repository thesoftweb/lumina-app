<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\AsaasService;
use Filament\Actions\Action;
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
            ->action(function (Invoice $record) {
                $asaasService = new AsaasService();
                $links = $asaasService->getPaymentLinks($record);

                if (!$links) {
                    Notification::make()
                        ->title('Cobrança não disponível')
                        ->body('Não foi possível obter os links de pagamento. Tente sincronizar primeiro.')
                        ->warning()
                        ->send();
                    return;
                }

                $message = "📋 Links de Pagamento - {$record->reference}\n\n";
                
                if ($links['boleto_url']) {
                    $message .= "🔗 Boleto: {$links['boleto_url']}\n";
                }
                if ($links['boleto_barcode']) {
                    $message .= "📊 Código de Barras: {$links['boleto_barcode']}\n";
                }
                if ($links['pix_url']) {
                    $message .= "🔗 PIX: {$links['pix_url']}\n";
                }

                Notification::make()
                    ->title('✅ Links de Pagamento')
                    ->body($message)
                    ->info()
                    ->send();
            })
            ->visible(fn (Invoice $record) => $record->asaas_invoice_id !== null);
    }
}
