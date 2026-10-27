<?php

namespace App\Filament\Resources\PreEnrollments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Histórico de contatos com a família. Os registros entram pelas ações WhatsApp e Registrar contato. */
class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Histórico de contatos';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-ellipsis';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['secretary', 'admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i'),
                TextColumn::make('channel')->label('Canal')->badge()->color('gray'),
                TextColumn::make('user.name')->label('Por')->placeholder('-'),
                TextColumn::make('status_after')->label('Situação depois')->badge()->placeholder('Sem mudança'),
                TextColumn::make('notes')->label('Conversa')->wrap()->limit(200)->placeholder('-'),
            ])
            ->paginated([10, 25])
            ->emptyStateHeading('Nenhum contato registrado')
            ->emptyStateDescription('Use as ações WhatsApp ou Registrar contato no topo da página.');
    }
}
