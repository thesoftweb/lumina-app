<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Widgets\InvoiceTotalsOverview;
use App\Models\Invoice;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova fatura'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InvoiceTotalsOverview::class,
        ];
    }

    /** "Em aberto" é tudo que ainda tem saldo a pagar (inclui as vencidas, que também têm aba própria). */
    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas')
                ->badge(fn (): int => Invoice::query()->count())
                ->badgeColor('gray'),
            'em_aberto' => Tab::make('Em aberto')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->unpaid())
                ->badge(fn (): int => Invoice::query()->unpaid()->count())
                ->badgeColor('info'),
            'vencidas' => Tab::make('Vencidas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->overdue())
                ->badge(fn (): int => Invoice::query()->overdue()->count())
                ->badgeColor('danger'),
            'pagas' => Tab::make('Pagas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Paid->value))
                ->badge(fn (): int => Invoice::query()->where('status', InvoiceStatus::Paid->value)->count())
                ->badgeColor('success'),
            'canceladas' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Canceled->value))
                ->badge(fn (): int => Invoice::query()->where('status', InvoiceStatus::Canceled->value)->count())
                ->badgeColor('gray'),
        ];
    }
}
