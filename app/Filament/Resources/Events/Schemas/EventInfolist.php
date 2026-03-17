<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Schemas\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Event Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Informações do Evento')
                            ->schema([
                                Section::make()
                                    ->heading('Dados do Evento')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Nome'),
                                                TextEntry::make('type')
                                                    ->label('Tipo')
                                                    ->badge()
                                                    ->formatStateUsing(fn($state) => match($state) {
                                                        'celebration' => 'Festa/Celebração',
                                                        'trip' => 'Viagem',
                                                        'extracurricular' => 'Atividade Extraclasse',
                                                        'contribution' => 'Contribuição',
                                                        default => $state,
                                                    }),
                                                TextEntry::make('classroom.name')
                                                    ->label('Turma'),
                                                TextEntry::make('amount')
                                                    ->label('Valor por Participante')
                                                    ->money('BRL'),
                                                TextEntry::make('due_date')
                                                    ->label('Data Limite')
                                                    ->dateTime('d/m/Y H:i'),
                                                TextEntry::make('status')
                                                    ->label('Status')
                                                    ->badge()
                                                    ->color(fn($state) => match($state) {
                                                        'active' => 'success',
                                                        'closed' => 'warning',
                                                        'canceled' => 'danger',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn($state) => match($state) {
                                                        'active' => 'Ativo',
                                                        'closed' => 'Fechado',
                                                        'canceled' => 'Cancelado',
                                                        default => $state,
                                                    }),
                                                TextEntry::make('description')
                                                    ->label('Descrição')
                                                    ->columnSpanFull()
                                                    ->markdown(),
                                                TextEntry::make('created_at')
                                                    ->label('Criado em')
                                                    ->dateTime('d/m/Y H:i'),
                                                TextEntry::make('updated_at')
                                                    ->label('Atualizado em')
                                                    ->dateTime('d/m/Y H:i'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Participantes Pagadores')
                            ->schema([
                                Section::make()
                                    ->heading('Lista de Participantes que Pagaram')
                                    ->schema([
                                        RepeatableEntry::make('participants')
                                            ->hidden(fn($record) => $record->participants()->where('status', 'paid')->count() === 0)
                                            ->formatStateUsing(fn($state) => $state)
                                            ->columnSpanFull()
                                            ->columns(4)
                                            ->query(fn($query) => $query->where('status', 'paid'))
                                            ->schema([
                                                TextEntry::make('customer.name')
                                                    ->label('Responsável')
                                                    ->columnSpan(2)
                                                    ->weight('bold'),
                                                TextEntry::make('paid_at')
                                                    ->label('Data de Pagamento')
                                                    ->dateTime('d/m/Y H:i'),
                                                TextEntry::make('customer.document')
                                                    ->label('CPF')
                                                    ->formatStateUsing(fn($state) => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $state ?? '')),
                                                TextEntry::make('customer.phone')
                                                    ->label('Telefone'),
                                                TextEntry::make('customer.email')
                                                    ->label('Email')
                                                    ->columnSpanFull(),
                                            ]),
                                        TextEntry::make('participants_empty')
                                            ->visible(fn($record) => $record->participants()->where('status', 'paid')->count() === 0)
                                            ->formatStateUsing(fn() => 'Nenhum participante pagou ainda')
                                            ->columnSpanFull()
                                            ->state(''),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
