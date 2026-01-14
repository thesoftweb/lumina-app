<?php

namespace App\Filament\Resources\DocumentTemplates;

use App\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\EditDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Filament\Resources\DocumentTemplates\Pages\ViewDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Schemas\DocumentTemplateForm;
use App\Filament\Resources\DocumentTemplates\Tables\DocumentTemplatesTable;
use App\Models\DocumentTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Modelos de Documentos';

    protected static string|UnitEnum|null $navigationGroup = 'Documentos';

    public static function shouldRegisterNavigation(): bool
    {
        return !Auth::user()->hasRole('teacher');
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTemplates::route('/'),
            'create' => CreateDocumentTemplate::route('/create'),
            'view' => ViewDocumentTemplate::route('/{record}'),
            'edit' => EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
