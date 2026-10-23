<?php

namespace App\Filament\Resources\Levels;

use App\Enums\NavGroup;
use App\Filament\Concerns\ChecksNavigationAccess;
use App\Filament\Resources\Levels\Pages\ManageLevels;
use App\Models\Level;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LevelResource extends Resource
{
    use ChecksNavigationAccess;

    protected static ?string $model = Level::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Settings;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Níveis';

    protected static ?string $modelLabel = 'Nível';

    protected static ?string $pluralModelLabel = 'Níveis';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('Descrição')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nível')->searchable()->sortable(),
                TextColumn::make('description')->label('Descrição')->limit(60)->placeholder('—'),
                TextColumn::make('classrooms_count')->label('Turmas')->counts('classrooms')->badge()->alignCenter(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                static::deleteAction(),
            ]);
    }

    protected static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->before(function (DeleteAction $action, Model $record): void {
            if ($reason = $record->deletionBlockedReason()) {
                Notification::make()->danger()->title('Não é possível excluir')->body($reason)->send();
                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLevels::route('/'),
        ];
    }
}
