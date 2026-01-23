<?php

namespace App\Filament\Resources\ClassDiaries;

use App\Filament\Resources\ClassDiaries\Pages\CreateClassDiary;
use App\Filament\Resources\ClassDiaries\Pages\EditClassDiary;
use App\Filament\Resources\ClassDiaries\Pages\ListClassDiaries;
use App\Filament\Resources\ClassDiaries\Pages\ViewClassDiary;
use App\Filament\Resources\ClassDiaries\Schemas\ClassDiaryForm;
use App\Filament\Resources\ClassDiaries\Schemas\ClassDiaryInfolist;
use App\Filament\Resources\ClassDiaries\Tables\ClassDiariesTable;
use App\Models\ClassDiary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ClassDiaryResource extends Resource
{
    protected static ?string $model = ClassDiary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'content';

    protected static ?string $modelLabel = 'Diário de Classe';

    protected static ?string $pluralModelLabel = 'Diários de Classe';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public static function form(Schema $schema): Schema
    {
        return ClassDiaryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClassDiaryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassDiariesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassDiaries::route('/'),
            'create' => CreateClassDiary::route('/create'),
            'view' => ViewClassDiary::route('/{record}'),
            'edit' => EditClassDiary::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
