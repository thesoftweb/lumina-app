<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('classroom.name')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'celebration' => 'Festa/Celebração',
                        'trip' => 'Viagem',
                        'extracurricular' => 'Atividade Extraclasse',
                        'contribution' => 'Contribuição',
                        default => $state,
                    }),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'closed',
                        'danger' => 'canceled',
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        'active' => 'Ativo',
                        'closed' => 'Fechado',
                        'canceled' => 'Cancelado',
                        default => $state,
                    }),
                TextColumn::make('participants_count')
                    ->label('Participantes Pagadores')
                    ->counts(['participants' => fn($query) => $query->where('status', 'paid')])
                    ->badge('primary'),
                TextColumn::make('total_collected')
                    ->label('Total Coletado')
                    ->formatStateUsing(fn($record) => 'R$ ' . number_format($record->getTotalCollected(), 2, ',', '.'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativo',
                        'closed' => 'Fechado',
                        'canceled' => 'Cancelado',
                    ]),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'celebration' => 'Festa/Celebração',
                        'trip' => 'Viagem',
                        'extracurricular' => 'Atividade Extraclasse',
                        'contribution' => 'Contribuição',
                    ]),
                SelectFilter::make('classroom_id')
                    ->label('Turma')
                    ->relationship('classroom', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
