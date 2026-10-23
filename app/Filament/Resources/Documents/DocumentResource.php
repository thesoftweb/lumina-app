<?php

namespace App\Filament\Resources\Documents;

use App\Enums\NavGroup;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    /** Módulo antigo, substituído por Documentos emitidos: fora do menu, só o admin consulta pela URL. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Documents;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Documentos';

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
