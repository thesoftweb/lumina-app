<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\AsaasChargeState;
use App\Enums\InvoiceType;
use App\Models\AccountPlan;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Asaas\ChargeOperations;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

/**
 * Formulário da fatura (criar, editar e "Nova fatura" na aba do responsável).
 * Na edição ganha uma coluna lateral só de leitura com a situação e o Asaas.
 */
class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Group::make()
                    ->columnSpan(fn (string $operation): array|string => $operation === 'edit' ? ['default' => 1, 'lg' => 2] : 'full')
                    ->schema([
                        Section::make('Cobrança')
                            ->description('Quem paga e o que está sendo cobrado.')
                            ->icon(Heroicon::OutlinedUser)
                            ->columns(2)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Responsável')
                                    ->required()
                                    ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    // O Asaas não troca o cliente de uma cobrança
                                    ->disabled(fn (?Invoice $record): bool => filled($record?->asaas_invoice_id))
                                    ->helperText(fn (?Invoice $record): ?string => filled($record?->asaas_invoice_id)
                                        ? 'A cobrança no Asaas é deste responsável; para trocar, cancele e crie outra fatura.'
                                        : null),
                                Select::make('billing_type')
                                    ->label('Tipo de cobrança')
                                    ->required()
                                    ->options(InvoiceType::class)
                                    ->default(InvoiceType::TUITION->value),
                                TextInput::make('reference')
                                    ->label('Referência interna')
                                    ->helperText('Deixe em branco para gerar automaticamente.')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Valor e datas')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->description(fn (?Invoice $record): ?string => ChargeOperations::canEditCharge($record)
                                ? null
                                : 'Fatura paga, parcial ou cancelada: valor e vencimento não podem mais ser alterados.')
                            ->columns(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Valor')
                                    ->required()
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->minValue(0.01)
                                    ->prefix('R$')
                                    ->disabled(fn (?Invoice $record): bool => ! ChargeOperations::canEditCharge($record)),
                                DatePicker::make('issue_date')
                                    ->label('Emissão')
                                    ->required()
                                    ->default(now()),
                                DatePicker::make('due_date')
                                    ->label('Vencimento')
                                    ->required()
                                    ->disabled(fn (?Invoice $record): bool => ! ChargeOperations::canEditCharge($record)),
                            ]),

                        Section::make('Classificação financeira')
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->columns(2)
                            ->collapsible()
                            ->schema([
                                Select::make('company_id')
                                    ->label('Empresa')
                                    ->options(fn (): array => Company::query()->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload(),
                                Select::make('account_id')
                                    ->label('Plano de contas')
                                    ->options(fn (): array => AccountPlan::query()->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Section::make('Observações')
                            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                            ->schema([
                                Textarea::make('notes')
                                    ->hiddenLabel()
                                    ->helperText('Vai para o Asaas como descrição da cobrança enquanto a fatura está em aberto.')
                                    ->rows(3),
                            ]),
                    ]),

                // Coluna lateral da edição: só leitura; as operações ficam no cabeçalho da página
                Group::make()
                    ->visibleOn('edit')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Section::make('Situação')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->schema([
                                TextEntry::make('status')->label('Situação')->badge(),
                                TextEntry::make('balance')->label('Saldo a pagar')
                                    ->money('BRL', locale: 'pt_BR')->weight(FontWeight::SemiBold)
                                    ->color(fn (?Invoice $record): string => (float) $record?->balance > 0 ? 'warning' : 'success'),
                                TextEntry::make('competence')->label('Competência')
                                    ->state(fn (?Invoice $record): ?string => $record?->competenceLabel()),
                                TextEntry::make('enrollment.student.name')->label('Aluno')
                                    ->placeholder('Sem matrícula vinculada')
                                    ->helperText(fn (?Invoice $record): ?string => $record?->enrollment?->classroom?->name),
                                TextEntry::make('payments_total')->label('Pagamentos recebidos')
                                    ->state(fn (?Invoice $record): float => (float) $record?->payments()->sum('amount'))
                                    ->money('BRL', locale: 'pt_BR')
                                    ->helperText(fn (?Invoice $record): string => ($count = $record?->payments()->count() ?? 0) === 1
                                        ? '1 pagamento'
                                        : "{$count} pagamentos"),
                            ]),

                        Section::make('Asaas')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                TextEntry::make('asaas_state')->hiddenLabel()
                                    ->state(fn (?Invoice $record): ?AsaasChargeState => $record ? AsaasChargeState::of($record) : null)
                                    ->badge(),
                                TextEntry::make('asaas_invoice_id')->label('ID da cobrança')
                                    ->copyable()->placeholder('Sem cobrança'),
                                TextEntry::make('invoice_link')->label('Link da fatura')
                                    ->state(fn (?Invoice $record): ?string => $record?->invoice_link ? 'Abrir fatura no Asaas' : null)
                                    ->url(fn (?Invoice $record): ?string => $record?->invoice_link)
                                    ->openUrlInNewTab()
                                    ->color('primary')
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
