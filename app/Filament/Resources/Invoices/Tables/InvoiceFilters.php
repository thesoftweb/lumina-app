<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Classroom;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/** Filtros de fatura usados na listagem de faturas e na aba Faturas do responsável. */
class InvoiceFilters
{
    public static function status(): SelectFilter
    {
        return SelectFilter::make('status')->label('Situação')->options(InvoiceStatus::class);
    }

    public static function billingType(): SelectFilter
    {
        return SelectFilter::make('billing_type')->label('Tipo de cobrança')->options(InvoiceType::class);
    }

    /** Mesmo critério do scope overdue: saldo a pagar com vencimento anterior a hoje. */
    public static function pastDue(): Filter
    {
        return Filter::make('past_due')->label('Vencidas com saldo')
            ->query(fn (Builder $query): Builder => $query->overdue());
    }

    public static function dueSoon(): Filter
    {
        return Filter::make('due_soon')->label('Vencem nos próximos 7 dias')
            ->query(fn (Builder $query): Builder => $query->unpaid()
                ->whereDate('due_date', '>=', today())
                ->whereDate('due_date', '<=', today()->addDays(7)));
    }

    public static function withBalance(): Filter
    {
        return Filter::make('with_balance')->label('Com saldo pendente')
            ->query(fn (Builder $query): Builder => $query->unpaid());
    }

    public static function duePeriod(): Filter
    {
        return Filter::make('due_period')->label('Período de vencimento')
            ->schema([
                DatePicker::make('from')->label('Vence de'),
                DatePicker::make('until')->label('Vence até')->afterOrEqual('from'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date)))
            ->indicateUsing(fn (array $data): array => array_values(array_filter([
                filled($data['from'] ?? null) ? 'Vence a partir de '.Carbon::parse($data['from'])->format('d/m/Y') : null,
                filled($data['until'] ?? null) ? 'Vence até '.Carbon::parse($data['until'])->format('d/m/Y') : null,
            ])));
    }

    /** Faturas que ainda não viraram cobrança no Asaas (as canceladas não precisam). */
    public static function withoutAsaasCharge(): Filter
    {
        return Filter::make('without_asaas_charge')->label('Sem cobrança no Asaas')
            ->query(fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query->whereNull('asaas_invoice_id')->orWhere('asaas_invoice_id', ''))
                ->where('status', '!=', InvoiceStatus::Canceled->value));
    }

    public static function asaasSyncFailed(): Filter
    {
        return Filter::make('asaas_sync_failed')->label('Falha de sincronização com o Asaas')
            ->query(fn (Builder $query): Builder => $query->where('asaas_sync_status', 'failed'));
    }

    public static function classroom(): SelectFilter
    {
        return SelectFilter::make('classroom')->label('Turma')
            ->options(fn (): array => Classroom::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $query, $classroomId): Builder => $query->whereHas(
                    'enrollment',
                    fn (Builder $query): Builder => $query->where('classroom_id', $classroomId),
                ),
            ));
    }
}
