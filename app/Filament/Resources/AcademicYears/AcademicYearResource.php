<?php

namespace App\Filament\Resources\AcademicYears;

use App\Models\AcademicYear;
use App\Filament\Resources\AcademicYears\Pages\CreateAcademicYear;
use App\Filament\Resources\AcademicYears\Pages\EditAcademicYear;
use App\Filament\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Filament\Resources\AcademicYears\Pages\ViewAcademicYear;
use App\Filament\Resources\AcademicYears\Schemas\AcademicYearForm;
use App\Filament\Resources\AcademicYears\Schemas\AcademicYearInfolist;
use App\Filament\Resources\AcademicYears\Tables\AcademicYearsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AcademicYearResource extends Resource
{
    protected static ?string $model = AcademicYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;
    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $label = 'Ano Escolar';

    protected static ?string $pluralModelLabel = 'Ano Escolar';


    public static function form(Schema $schema): Schema
    {
        return AcademicYearForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AcademicYearInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicYearsTable::configure($table);
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
            'index' => ListAcademicYears::route('/'),
            'create' => CreateAcademicYear::route('/create'),
            'view' => ViewAcademicYear::route('/{record}'),
            'edit' => EditAcademicYear::route('/{record}/edit'),
        ];
    }
}
