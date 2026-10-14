<?php

namespace App\Filament\Resources\Subjects;

use App\Enums\NavGroup;
use App\Filament\Concerns\ChecksNavigationAccess;
use App\Filament\Resources\Subjects\Pages\ManageSubjects;
use App\Models\Subject;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SubjectResource extends Resource
{
    use ChecksNavigationAccess;

    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Settings;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Disciplinas';

    protected static ?string $modelLabel = 'Disciplina';

    protected static ?string $pluralModelLabel = 'Disciplinas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->label('Código')
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Opcional. Ex.: MAT, POR, HIS.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Disciplina')->searchable()->sortable(),
                TextColumn::make('code')->label('Código')->searchable()->placeholder('—'),
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
            'index' => ManageSubjects::route('/'),
        ];
    }
}
