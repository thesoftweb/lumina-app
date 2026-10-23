<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperationException;
use App\Services\Asaas\ChargeOperations;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** A fatura nasce junto com a cobrança no Asaas; se o Asaas recusar, ela não é criada. */
class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ChargeOperations::class)->createInvoice(fn (): Invoice => Invoice::create([
                ...$data,
                'balance' => $data['amount'],
                'status' => 'open',
            ]));
        } catch (ChargeOperationException $e) {
            Notification::make()
                ->danger()
                ->title('A fatura não foi criada')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
