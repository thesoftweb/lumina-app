<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EnrollmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Enrollment Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Matrícula')
                            ->schema([
                                Section::make()
                                    ->heading('Informações da Matrícula')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('id')
                                                    ->label('ID Matrícula'),
                                                TextEntry::make('enrollment_date')
                                                    ->label('Data da Matrícula')
                                                    ->date(),
                                                TextEntry::make('status')
                                                    ->label('Status'),
                                                TextEntry::make('day_of_payment')
                                                    ->label('Dia de Pagamento'),
                                                TextEntry::make('created_at')
                                                    ->label('Criado em')
                                                    ->dateTime(),
                                                TextEntry::make('updated_at')
                                                    ->label('Atualizado em')
                                                    ->dateTime(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Aluno')
                            ->schema([
                                Section::make()
                                    ->heading('Informações do Aluno')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('student.name')
                                                    ->label('Nome do Aluno'),
                                                TextEntry::make('student.doc_number')
                                                    ->label('Documento do Aluno'),
                                                TextEntry::make('student.date_of_birth')
                                                    ->label('Data de Nascimento')
                                                    ->date(),
                                                TextEntry::make('student.gender')
                                                    ->label('Gênero'),
                                                TextEntry::make('student.phone_primary')
                                                    ->label('Telefone Principal'),
                                                TextEntry::make('student.phone_secondary')
                                                    ->label('Telefone Secundário'),
                                                TextEntry::make('student.state_of_birth')
                                                    ->label('Estado de Nascimento'),
                                                TextEntry::make('student.city_of_birth')
                                                    ->label('Cidade de Nascimento'),
                                                TextEntry::make('student.affiliation_1')
                                                    ->label('Filiação 1')
                                                    ->columnSpanFull(),
                                                TextEntry::make('student.affiliation_2')
                                                    ->label('Filiação 2')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Responsável')
                            ->schema([
                                Section::make()
                                    ->heading('Informações Pessoais')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('student.customer.name')
                                                    ->label('Nome do Responsável'),
                                                TextEntry::make('student.customer.document')
                                                    ->label('CPF')
                                                    ->formatStateUsing(fn($state) => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $state ?? '')),
                                                TextEntry::make('student.customer.email')
                                                    ->label('E-mail'),
                                                TextEntry::make('student.customer.phone')
                                                    ->label('Telefone'),
                                            ]),
                                    ]),
                                Section::make()
                                    ->heading('Endereço')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('student.customer.address')
                                                    ->label('Endereço')
                                                    ->columnSpanFull(),
                                                TextEntry::make('student.customer.address_number')
                                                    ->label('Número'),
                                                TextEntry::make('student.customer.address_complement')
                                                    ->label('Complemento'),
                                                TextEntry::make('student.customer.neighborhood')
                                                    ->label('Bairro'),
                                                TextEntry::make('student.customer.city.name')
                                                    ->label('Cidade'),
                                                TextEntry::make('student.customer.state')
                                                    ->label('Estado'),
                                                TextEntry::make('student.customer.postal_code')
                                                    ->label('CEP')
                                                    ->formatStateUsing(fn($state) => preg_replace('/(\d{5})(\d{3})/', '$1-$2', $state ?? '')),
                                            ]),
                                    ]),
                                Section::make()
                                    ->heading('Dados do Sistema')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('student.customer.asaas_customer_id')
                                                    ->label('ID Asaas')
                                                    ->copyable(),
                                                TextEntry::make('student.customer.created_at')
                                                    ->label('Cadastrado em')
                                                    ->dateTime(),
                                                TextEntry::make('student.customer.updated_at')
                                                    ->label('Atualizado em')
                                                    ->dateTime(),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Acadêmico')
                            ->schema([
                                Section::make()
                                    ->heading('Informações Acadêmicas')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('classroom.name')
                                                    ->label('Turma'),
                                                TextEntry::make('plan.name')
                                                    ->label('Plano'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Faturas')
                            ->schema([
                                Section::make()
                                    ->heading('Faturas da Matrícula')
                                    ->schema([
                                        RepeatableEntry::make('invoices')
                                            ->schema([
                                                TextEntry::make('number')
                                                    ->label('Número'),
                                                TextEntry::make('status')
                                                    ->label('Status')
                                                    ->badge(),
                                                TextEntry::make('amount')
                                                    ->label('Valor')
                                                    ->money('BRL'),
                                                TextEntry::make('issue_date')
                                                    ->label('Data de Emissão')
                                                    ->date(),
                                                TextEntry::make('due_date')
                                                    ->label('Data de Vencimento')
                                                    ->date(),
                                                TextEntry::make('billing_type')
                                                    ->label('Tipo de Cobrança'),
                                            ])
                                            ->columns(3),
                                    ]),
                            ]),

                        Tabs\Tab::make('Documentação')
                            ->schema([
                                Section::make()
                                    ->heading('Documentação Entregue')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                IconEntry::make('doc_historical_delivered')
                                                    ->label('Histórico Escolar')
                                                    ->boolean(),
                                                IconEntry::make('doc_photo_3x4_delivered')
                                                    ->label('Foto 3x4')
                                                    ->boolean(),
                                                IconEntry::make('doc_declaration_delivered')
                                                    ->label('Declaração')
                                                    ->boolean(),
                                                IconEntry::make('doc_residence_proof_delivered')
                                                    ->label('Comprovante de Residência')
                                                    ->boolean(),
                                                IconEntry::make('doc_student_document_delivered')
                                                    ->label('Documento Aluno')
                                                    ->boolean(),
                                                IconEntry::make('doc_responsible_document_delivered')
                                                    ->label('Documento Responsável')
                                                    ->boolean(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
