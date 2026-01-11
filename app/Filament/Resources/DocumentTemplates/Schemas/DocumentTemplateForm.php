<?php

namespace App\Filament\Resources\DocumentTemplates\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class DocumentTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Modelo')
                    ->description('Configure o modelo de documento')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome do Modelo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label('Tipo de Documento')
                            ->options([
                                'receipt' => 'Recibo',
                                'invoice' => 'Fatura',
                                'quote' => 'Cotação',
                                'school_contract' => 'Contrato Escolar',
                            ])
                            ->required(),
                    ]),

                Section::make('Merge Tags Disponíveis')
                    ->description('Tags que podem ser usadas no conteúdo: {{customer_name}}, {{customer_email}}, {{amount}}, {{date}}, {{receipt_number}}, {{company_name}}, {{company_address}}, {{phone}}, {{cpf}}, {{cnpj}}, {{document_number}}')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([]),

                Section::make('Conteúdo do Documento')
                    ->description('Use os merge tags para inserir variáveis {{campo}}')
                    ->columnSpanFull()
                    ->schema([
                        RichEditor::make('content')
                            ->label('Conteúdo')
                            ->mergeTags([
                                'customer_name' => 'Nome do Cliente',
                                'customer_email' => 'Email do Cliente',
                                'amount' => 'Valor',
                                'date' => 'Data',
                                'receipt_number' => 'Número do Recibo',
                                'company_name' => 'Nome da Empresa',
                                'company_address' => 'Endereço da Empresa',
                                'phone' => 'Telefone',
                                'cpf' => 'CPF',
                                'cnpj' => 'CNPJ',
                                'document_number' => 'Número do Documento',
                                'student_name' => 'Nome do Aluno',
                                'student_birth_date' => 'Data de Nascimento',
                                'student_cpf' => 'CPF do Aluno',
                                'classroom_name' => 'Nome da Turma',
                                'plan_name' => 'Nome do Plano',
                                'academic_year' => 'Ano Letivo',
                            ])
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('merge_tags')
                            ->label('Merge Tags Usadas (JSON)')
                            ->helperText('Este campo é preenchido automaticamente com os merge tags detectados no conteúdo')
                            ->columnSpanFull()
                            ->disabled()
                            ->hidden(),
                    ]),
            ]);
    }
}
