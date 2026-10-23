<?php

namespace App\Filament\Resources\IssuedDocuments;

use App\Enums\NavGroup;
use App\Filament\Concerns\ChecksNavigationAccess;
use App\Filament\Resources\IssuedDocuments\Pages\ListIssuedDocuments;
use App\Filament\Resources\IssuedDocuments\Tables\IssuedDocumentsTable;
use App\Models\IssuedDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Documentos emitidos (declarações e recibos). A emissão acontece na matrícula ou na fatura; aqui se consulta, reimprime e cancela. */
class IssuedDocumentResource extends Resource
{
    use ChecksNavigationAccess;

    protected static ?string $model = IssuedDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Documents;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Documentos emitidos';

    protected static ?string $modelLabel = 'Documento emitido';

    protected static ?string $pluralModelLabel = 'Documentos emitidos';

    protected static ?string $slug = 'issued-documents';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('issuer');
    }

    public static function table(Table $table): Table
    {
        return IssuedDocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIssuedDocuments::route('/'),
        ];
    }
}
