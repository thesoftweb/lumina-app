<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\Actions\AsaasActionGroup;
use App\Filament\Resources\Invoices\Actions\CancelInvoiceAction;
use App\Filament\Resources\Invoices\Actions\DeleteInvoiceActions;
use App\Filament\Resources\Invoices\Actions\IssuePaymentReceiptAction;
use App\Filament\Resources\Invoices\Actions\ReceivePaymentAction;
use App\Filament\Resources\Invoices\Actions\RestoreInvoiceAction;
use App\Filament\Resources\Invoices\Actions\UndoPaymentAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperationException;
use App\Services\Asaas\ChargeOperations;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

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
     * Cabeçalho: documento (recibo), "Receber pagamento", Asaas (grupo) e "Mais ações" com as
     * demais operações. Todas passam por ChargeOperations (Asaas primeiro).
     */
    protected function getHeaderActions(): array
    {
        return [
            IssuePaymentReceiptAction::make()->color('gray'),
            $this->refreshingForm(ReceivePaymentAction::make()),
            AsaasActionGroup::make(),
            ActionGroup::make([
                ViewAction::make()
                    ->label('Ver resumo')
                    ->icon(Heroicon::OutlinedEye)
                    ->modal()
                    ->slideOver()
                    ->modalHeading(fn (Invoice $record): string => 'Fatura nº '.($record->number ?? $record->getKey())),
                $this->refreshingForm(UndoPaymentAction::make()),
                $this->refreshingForm(CancelInvoiceAction::make()),
                $this->refreshingForm(RestoreInvoiceAction::make()),
                DeleteInvoiceActions::single(),
            ])
                ->label('Mais ações')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }

    /** Valor, vencimento e observações vão para o Asaas pela operação de atualizar (Asaas primeiro). */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ChargeOperations::class)->update($record, $data);
        } catch (ChargeOperationException $e) {
            Notification::make()
                ->danger()
                ->title('Não foi possível salvar')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    /** Depois de uma operação, o formulário mostra o valor, o saldo e a situação atualizados. */
    private function refreshingForm(Action $action): Action
    {
        return $action->after(fn () => $this->refreshFormData(['amount', 'balance', 'due_date', 'notes', 'status']));
    }
}
