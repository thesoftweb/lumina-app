<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Enrollment;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class EnrollmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 3, 'xl' => 6])
                            ->schema([
                                TextEntry::make('status')->label('Situação')->badge(),
                                TextEntry::make('classroom.name')->label('Turma')->weight(FontWeight::SemiBold)->placeholder('Não informada'),
                                TextEntry::make('academicYear.description')->label('Ano letivo')->placeholder('Não informado'),
                                TextEntry::make('enrollment_date')->label('Data da matrícula')->date('d/m/Y'),
                                TextEntry::make('monthly_amount')->label('Mensalidade')
                                    ->state(fn (Enrollment $record) => $record->tuitionBreakdown()['final_amount'] ?? null)
                                    ->money('BRL', locale: 'pt_BR')->weight(FontWeight::SemiBold)->placeholder('Sem plano'),
                                TextEntry::make('day_of_payment')->label('Vencimento')
                                    ->formatStateUsing(fn ($state) => "Todo dia {$state}")->placeholder('-'),
                            ]),
                    ]),

                Tabs::make('Detalhes da matrícula')
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Aluno')
                            ->icon('heroicon-o-user')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('student.name')->label('Nome')->weight(FontWeight::SemiBold)->color('primary')
                                    ->url(fn (Enrollment $record): ?string => $record->student ? StudentResource::getUrl('view', ['record' => $record->student]) : null)
                                    ->columnSpanFull(),
                                TextEntry::make('student.date_of_birth')->label('Data de nascimento')
                                    ->formatStateUsing(fn ($state) => $state->format('d/m/Y')." ({$state->age} anos)")->placeholder('-'),
                                TextEntry::make('student.gender_label')->label('Gênero')->placeholder('-'),
                                TextEntry::make('student.birthplace')->label('Naturalidade')->placeholder('-'),
                                TextEntry::make('student.doc_number')->label('CPF')->placeholder('-'),
                                TextEntry::make('student.reg_number')->label('RG')->placeholder('-'),
                                TextEntry::make('student.affiliation_1')->label('Filiação 1')->placeholder('-'),
                                TextEntry::make('student.phone_primary')->label('Telefone principal')->placeholder('-'),
                                TextEntry::make('student.affiliation_2')->label('Filiação 2')->placeholder('-'),
                                TextEntry::make('student.phone_secondary')->label('Telefone secundário')->placeholder('-'),
                                TextEntry::make('student.observations')->label('Observações')->placeholder('-')->columnSpanFull(),
                            ]),

                        Tab::make('Responsável')
                            ->icon('heroicon-o-user-group')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('student.customer.name')->label('Responsável financeiro')->weight(FontWeight::SemiBold)->color('primary')
                                    ->url(fn (Enrollment $record): ?string => $record->student?->customer ? CustomerResource::getUrl('edit', ['record' => $record->student->customer]) : null)
                                    ->placeholder('Não informado'),
                                TextEntry::make('student.degree_of_kinship')->label('Parentesco')
                                    ->formatStateUsing(fn ($state) => Str::ucfirst($state))->placeholder('-'),
                                TextEntry::make('student.customer.document')->label('CPF')->placeholder('-'),
                                TextEntry::make('student.customer.phone')->label('Telefone')->placeholder('-'),
                                TextEntry::make('student.customer.email')->label('E-mail')->copyable()->placeholder('-'),
                                TextEntry::make('student.customer.asaas_customer_id')->label('ID no Asaas')->copyable()->placeholder('Não sincronizado'),
                                TextEntry::make('student.customer.full_address')->label('Endereço')->placeholder('-')->columnSpanFull(),
                            ]),

                        Tab::make('Financeiro')
                            ->icon('heroicon-o-banknotes')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('plan.name')->label('Plano')->placeholder('Não informado'),
                                TextEntry::make('plan.base_amount')->label('Valor base')->money('BRL', locale: 'pt_BR')->placeholder('-'),
                                TextEntry::make('discount')->label('Desconto')
                                    ->state(fn (Enrollment $record): ?string => $record->tuitionDiscountLabel())->placeholder('Sem desconto'),
                                TextEntry::make('final_amount')->label('Mensalidade com desconto')
                                    ->state(fn (Enrollment $record) => $record->tuitionBreakdown()['final_amount'] ?? null)
                                    ->money('BRL', locale: 'pt_BR')->weight(FontWeight::SemiBold)->placeholder('-'),
                                TextEntry::make('discount_reason')->label('Motivo do desconto')->columnSpanFull()
                                    ->visible(fn (Enrollment $record): bool => (bool) $record->use_custom_discount)->placeholder('-'),
                                IconEntry::make('enrollment_tax_paid')->label('Taxa de matrícula paga')->boolean(),
                                IconEntry::make('tuition_generated')->label('Mensalidades geradas')->boolean(),
                            ]),

                        Tab::make('Faturas')
                            ->icon('heroicon-o-receipt-percent')
                            ->badge(fn (Enrollment $record): ?int => $record->invoices->count() ?: null)
                            ->schema([
                                RepeatableEntry::make('invoices')
                                    ->hiddenLabel()
                                    ->placeholder('Nenhuma fatura gerada para esta matrícula.')
                                    ->table([
                                        TableColumn::make('Número'),
                                        TableColumn::make('Tipo'),
                                        TableColumn::make('Vencimento'),
                                        TableColumn::make('Valor'),
                                        TableColumn::make('Situação'),
                                    ])
                                    ->schema([
                                        TextEntry::make('number'),
                                        TextEntry::make('billing_type')->badge(),
                                        TextEntry::make('due_date')->date('d/m/Y'),
                                        TextEntry::make('amount')->money('BRL', locale: 'pt_BR'),
                                        TextEntry::make('status')->badge(),
                                    ]),
                            ]),
                    ]),

                Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Section::make('Documentação')
                            ->icon('heroicon-o-document-check')
                            ->description(fn (Enrollment $record): string => "{$record->deliveredDocumentsCount()} de ".count(Enrollment::DOCUMENTS).' entregues')
                            ->compact()
                            ->dense()
                            ->schema(collect(Enrollment::DOCUMENTS)
                                ->map(fn (string $label, string $field): TextEntry => TextEntry::make($field)->hiddenLabel()->state($label)
                                    ->icon(fn (Enrollment $record) => $record->{$field} ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                                    ->iconColor(fn (Enrollment $record): string => $record->{$field} ? 'success' : 'danger'))
                                ->values()->all()),
                        Section::make('Registro')
                            ->icon('heroicon-o-clock')
                            ->compact()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('id')->label('Nº da matrícula')
                                    ->formatStateUsing(fn (Enrollment $record): string => $record->number()),
                                IconEntry::make('new_student')->label('Primeira matrícula')->boolean(),
                                TextEntry::make('created_at')->label('Cadastrada em')->dateTime('d/m/Y H:i'),
                                TextEntry::make('updated_at')->label('Atualizada em')->dateTime('d/m/Y H:i'),
                            ]),
                    ]),
            ]);
    }
}
