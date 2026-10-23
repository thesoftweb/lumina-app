<?php

namespace App\Filament\Pages;

use App\Enums\CollectionChannel;
use App\Enums\CollectionOutcome;
use App\Enums\NavGroup;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\CollectionContact;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Collections\CollectionMessage;
use App\Services\Collections\LateFeeCalculator;
use App\Services\Collections\OverdueDebt;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use UnitEnum;

/**
 * Inadimplência: um responsável por linha, com o que está vencido, o valor atualizado (multa e juros
 * estimados com as regras do Asaas) e o histórico de cobrança. Daqui a secretaria manda a mensagem
 * por WhatsApp e registra cada contato e promessa de pagamento.
 */
class Delinquency extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Finance;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Inadimplência';

    protected static ?string $title = 'Inadimplência';

    protected static ?string $slug = 'inadimplencia';

    protected string $view = 'filament.pages.delinquency';

    private ?LateFeeCalculator $calculator = null;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasAnyRole(['admin', 'secretary']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Customer::query()->whereHas('overdueInvoices')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getSubheading(): ?string
    {
        $balance = (float) Invoice::query()->overdue()->sum('balance');
        $rules = $this->calculator()->rulesLabel();

        return 'Em atraso: '.OverdueDebt::money($balance).' sem encargos.'
            .($rules ? " Valores atualizados estimados com {$rules}, as regras do Asaas." : ' Configure multa e juros na empresa para estimar o valor atualizado.');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query()
                ->whereHas('overdueInvoices')
                ->withCount('overdueInvoices')
                ->withSum('overdueInvoices', 'balance')
                ->withMin('overdueInvoices', 'due_date')
                ->with(['overdueInvoices', 'students', 'latestCollectionContact']))
            ->defaultSort('overdue_invoices_min_due_date')
            ->recordAction('details')
            ->searchPlaceholder('Buscar responsável ou aluno')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('Nenhum responsável com fatura vencida')
            ->emptyStateDescription('Quando uma fatura passar do vencimento sem pagamento, o responsável aparece aqui.')
            ->columns([
                TextColumn::make('name')
                    ->label('Responsável')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Customer $record): ?string => $record->students->pluck('name')->sort()->implode(', ') ?: null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('students', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))))
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->placeholder('Sem telefone')
                    ->copyable(),
                TextColumn::make('overdue_invoices_min_due_date')
                    ->label('Atraso')
                    ->state(fn (Customer $record): int => (int) $record->overdueInvoices->max(fn (Invoice $invoice): int => (int) $invoice->due_date->diffInDays(today())))
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? '1 dia' : "{$state} dias")
                    ->description(fn (Customer $record): string => $record->overdue_invoices_count === 1 ? '1 fatura' : "{$record->overdue_invoices_count} faturas")
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state > 60 => 'danger',
                        $state > 30 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('overdue_invoices_min_due_date', $direction === 'asc' ? 'desc' : 'asc')),
                TextColumn::make('overdue_invoices_sum_balance')
                    ->label('Em aberto')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('updated_total')
                    ->label('Atualizado')
                    ->state(fn (Customer $record): float => $this->debtFromLoaded($record)->updatedTotal())
                    ->money('BRL', locale: 'pt_BR')
                    ->weight(FontWeight::SemiBold)
                    ->color('danger')
                    ->tooltip('Estimativa com multa e juros até hoje; o valor exato está no link do Asaas')
                    ->alignEnd(),
                TextColumn::make('latestCollectionContact.created_at')
                    ->label('Último contato')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->description(fn (Customer $record): ?string => ($contact = $record->latestCollectionContact)
                        ? $contact->channel->getLabel().' · '.$contact->outcome->getLabel()
                        : null)
                    ->placeholder('Nenhum'),
                TextColumn::make('promise')
                    ->label('Promessa')
                    ->state(fn (Customer $record): ?string => $record->latestCollectionContact?->promised_date?->format('d/m/Y'))
                    ->badge()
                    ->formatStateUsing(fn (string $state, Customer $record): string => ($record->latestCollectionContact->promiseIsBroken() ? 'Não cumprida · ' : 'Para ').$state)
                    ->color(fn (Customer $record): string => $record->latestCollectionContact?->promiseIsBroken() ? 'danger' : 'info')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('late')
                    ->label('Atraso da fatura mais antiga')
                    ->options([
                        'up_to_30' => 'Até 30 dias',
                        '31_60' => '31 a 60 dias',
                        '61_90' => '61 a 90 dias',
                        'over_90' => 'Mais de 90 dias',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        [$min, $max] = match ($data['value'] ?? null) {
                            'up_to_30' => [1, 30],
                            '31_60' => [31, 60],
                            '61_90' => [61, 90],
                            'over_90' => [91, null],
                            default => [null, null],
                        };

                        if ($min === null) {
                            return $query;
                        }

                        // A mais antiga vencida entre $min e $max dias atrás
                        return $query
                            ->whereHas('overdueInvoices', fn (Builder $query): Builder => $query->whereDate('due_date', '<=', today()->subDays($min)))
                            ->when($max, fn (Builder $query): Builder => $query->whereDoesntHave(
                                'overdueInvoices',
                                fn (Builder $query): Builder => $query->whereDate('due_date', '<', today()->subDays($max)),
                            ));
                    }),
                SelectFilter::make('contact')
                    ->label('Cobrança')
                    ->options([
                        'no_recent_contact' => 'Sem contato nos últimos 7 dias',
                        'broken_promise' => 'Promessa não cumprida',
                        'open_promise' => 'Promessa dentro do prazo',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'no_recent_contact' => $query->whereDoesntHave('collectionContacts', fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7))),
                        'broken_promise' => $query->whereHas('latestCollectionContact', fn (Builder $query): Builder => $query->whereDate('promised_date', '<', today())),
                        'open_promise' => $query->whereHas('latestCollectionContact', fn (Builder $query): Builder => $query->whereDate('promised_date', '>=', today())),
                        default => $query,
                    }),
            ])
            ->recordActions([
                $this->whatsAppAction(),
                $this->registerContactAction(),
                $this->detailsAction(),
            ]);
    }

    private function whatsAppAction(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->disabled(fn (Customer $record): bool => CollectionMessage::phones($record) === [])
            ->tooltip(fn (Customer $record): ?string => CollectionMessage::phones($record) === []
                ? 'Nenhum telefone cadastrado para o responsável ou os alunos'
                : null)
            ->modalHeading(fn (Customer $record): string => "Cobrar {$record->name} pelo WhatsApp")
            ->modalDescription('Revise a mensagem. Ao confirmar, o contato fica registrado e o WhatsApp abre com o texto pronto para enviar.')
            ->modalSubmitActionLabel('Abrir WhatsApp')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (Customer $record): array => [
                'phone' => array_key_first(CollectionMessage::phones($record)),
                'message' => app(CollectionMessage::class)->build($this->debt($record), $this->company()),
            ])
            ->schema(fn (Customer $record): array => [
                Select::make('phone')
                    ->label('Enviar para')
                    ->options(CollectionMessage::phones($record))
                    ->required()
                    ->native(false),
                Textarea::make('message')
                    ->label('Mensagem')
                    ->rows(16)
                    ->required(),
            ])
            ->action(function (Customer $record, array $data, Component $livewire): void {
                CollectionContact::record($this->debt($record), CollectionChannel::WhatsApp, CollectionOutcome::MessageSent, ['message' => $data['message']]);

                $url = CollectionMessage::whatsappUrl($data['phone'], $data['message']);
                $livewire->js('window.open('.json_encode($url).', "_blank")');

                Notification::make()
                    ->success()
                    ->title('Contato registrado')
                    ->body('Se o WhatsApp não abriu, use o botão abaixo.')
                    ->actions([Action::make('open')->label('Abrir WhatsApp')->url($url, shouldOpenInNewTab: true)])
                    ->send();
            });
    }

    private function registerContactAction(): Action
    {
        return Action::make('registerContact')
            ->label('Registrar contato')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalHeading(fn (Customer $record): string => "Contato com {$record->name}")
            ->modalSubmitActionLabel('Registrar')
            ->schema([
                Select::make('channel')
                    ->label('Canal')
                    ->options(CollectionChannel::class)
                    ->default(CollectionChannel::Phone->value)
                    ->required()
                    ->native(false),
                Select::make('outcome')
                    ->label('Resultado')
                    ->options(CollectionOutcome::class)
                    ->required()
                    ->live()
                    ->native(false),
                DatePicker::make('promised_date')
                    ->label('Pagamento prometido para')
                    ->minDate(today())
                    ->required()
                    ->visible(fn (Get $get): bool => $this->isPromise($get('outcome'))),
                TextInput::make('promised_amount')
                    ->label('Valor prometido')
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('R$')
                    ->helperText('Deixe em branco se for o total.')
                    ->visible(fn (Get $get): bool => $this->isPromise($get('outcome'))),
                Textarea::make('notes')
                    ->label('Observações')
                    ->rows(3),
            ])
            ->action(function (Customer $record, array $data): void {
                $contact = CollectionContact::record(
                    $this->debt($record),
                    CollectionChannel::from($data['channel'] instanceof CollectionChannel ? $data['channel']->value : $data['channel']),
                    CollectionOutcome::from($data['outcome'] instanceof CollectionOutcome ? $data['outcome']->value : $data['outcome']),
                    $data,
                );

                Notification::make()
                    ->success()
                    ->title('Contato registrado')
                    ->body($contact->promised_date ? 'Promessa de pagamento para '.$contact->promised_date->format('d/m/Y').'.' : null)
                    ->send();
            });
    }

    private function detailsAction(): Action
    {
        return Action::make('details')
            ->label('Detalhes')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (Customer $record): string => $record->name)
            ->modalDescription(fn (Customer $record): ?string => $this->calculator()->rulesLabel()
                ? 'Valores atualizados estimados com '.$this->calculator()->rulesLabel().'.'
                : null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->extraModalFooterActions(fn (Customer $record): array => [
                Action::make('openCustomer')
                    ->label('Abrir cadastro')
                    ->icon(Heroicon::OutlinedUser)
                    ->color('gray')
                    ->url(CustomerResource::getUrl('edit', ['record' => $record])),
            ])
            ->schema(fn (Customer $record): array => $this->detailsSchema($record));
    }

    /** @return array<int, mixed> */
    private function detailsSchema(Customer $customer): array
    {
        $debt = $this->debt($customer);
        $phones = CollectionMessage::phones($customer);

        return [
            Section::make('Contato')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('phones')->label('Telefones')
                        ->state(array_values($phones) ?: null)
                        ->listWithLineBreaks()
                        ->placeholder('Nenhum telefone cadastrado'),
                    TextEntry::make('email')->label('E-mail')
                        ->state($customer->email)
                        ->copyable()
                        ->placeholder('Sem e-mail'),
                    TextEntry::make('students')->label('Alunos')
                        ->state($customer->students->pluck('name')->sort()->values()->all() ?: null)
                        ->listWithLineBreaks()
                        ->placeholder('Nenhum aluno vinculado'),
                    TextEntry::make('address')->label('Endereço')
                        ->state($customer->full_address)
                        ->placeholder('Sem endereço'),
                ]),
            Section::make('Faturas vencidas')
                ->description(OverdueDebt::money($debt->balance()).' em aberto + '.OverdueDebt::money($debt->charges()).' de multa e juros = '.OverdueDebt::money($debt->updatedTotal()))
                ->schema([
                    RepeatableEntry::make('invoices')
                        ->hiddenLabel()
                        ->state($debt->items->map(fn (array $item): array => [
                            'label' => OverdueDebt::invoiceLabel($item['invoice']),
                            'student' => $item['invoice']->enrollment?->student?->name,
                            'due' => $item['invoice']->due_date->format('d/m/Y'),
                            'days' => $item['amounts']->daysLate,
                            'balance' => (float) $item['invoice']->balance,
                            'charges' => $item['amounts']->charges(),
                            'total' => $item['amounts']->total,
                            'url' => InvoiceResource::getUrl('edit', ['record' => $item['invoice']]),
                        ])->all())
                        ->table([
                            TableColumn::make('Fatura'),
                            TableColumn::make('Vencimento'),
                            TableColumn::make('Saldo'),
                            TableColumn::make('Multa e juros'),
                            TableColumn::make('Atualizado'),
                        ])
                        ->schema([
                            TextEntry::make('label')
                                ->url(fn (TextEntry $component): ?string => self::item($component, 'url'))
                                ->color('primary')
                                ->helperText(fn (TextEntry $component): ?string => self::item($component, 'student')),
                            TextEntry::make('due')
                                ->helperText(fn (TextEntry $component): string => ($days = self::item($component, 'days')) === 1 ? 'há 1 dia' : "há {$days} dias"),
                            TextEntry::make('balance')->money('BRL', locale: 'pt_BR'),
                            TextEntry::make('charges')->money('BRL', locale: 'pt_BR'),
                            TextEntry::make('total')->money('BRL', locale: 'pt_BR')->weight(FontWeight::SemiBold),
                        ]),
                ]),
            Section::make('Histórico de cobrança')
                ->schema([
                    RepeatableEntry::make('history')
                        ->hiddenLabel()
                        ->state($customer->collectionContacts()->with('user')->limit(30)->get()->map(fn (CollectionContact $contact): array => [
                            'when' => $contact->created_at->format('d/m/Y H:i'),
                            'who' => $contact->user?->name,
                            'how' => $contact->channel->getLabel(),
                            'outcome' => $contact->outcome->getLabel(),
                            'promise' => $contact->promised_date
                                ? 'Prometeu pagar '.($contact->promised_amount ? OverdueDebt::money($contact->promised_amount).' ' : '').'até '.$contact->promised_date->format('d/m/Y')
                                : null,
                            'notes' => $contact->notes,
                        ])->all() ?: null)
                        ->placeholder('Nenhum contato registrado.')
                        ->table([
                            TableColumn::make('Quando'),
                            TableColumn::make('Contato'),
                            TableColumn::make('Resultado'),
                        ])
                        ->schema([
                            TextEntry::make('when')
                                ->helperText(fn (TextEntry $component): ?string => self::item($component, 'who')),
                            TextEntry::make('how'),
                            TextEntry::make('outcome')
                                ->helperText(fn (TextEntry $component): ?string => collect([self::item($component, 'promise'), self::item($component, 'notes')])
                                    ->filter()->implode(' · ') ?: null),
                        ]),
                ]),
        ];
    }

    /** Campo da linha de uma RepeatableEntry montada com arrays. */
    private static function item(TextEntry $component, string $key): mixed
    {
        $item = $component->getContainer()->getConstantState();

        return is_array($item) ? ($item[$key] ?? null) : null;
    }

    private function isPromise(mixed $outcome): bool
    {
        return ($outcome instanceof CollectionOutcome ? $outcome : CollectionOutcome::tryFrom((string) $outcome)) === CollectionOutcome::Promised;
    }

    private function debt(Customer $customer): OverdueDebt
    {
        return OverdueDebt::for($customer, $this->calculator());
    }

    /** Valor atualizado da linha com as faturas já carregadas (sem consulta por linha). */
    private function debtFromLoaded(Customer $customer): OverdueDebt
    {
        $calculator = $this->calculator();

        return new OverdueDebt(
            $customer,
            $customer->overdueInvoices->map(fn (Invoice $invoice): array => ['invoice' => $invoice, 'amounts' => $calculator->invoice($invoice)]),
            $calculator,
        );
    }

    private function company(): ?Company
    {
        return Company::current(Auth::user());
    }

    private function calculator(): LateFeeCalculator
    {
        return $this->calculator ??= LateFeeCalculator::forCompany($this->company());
    }
}
