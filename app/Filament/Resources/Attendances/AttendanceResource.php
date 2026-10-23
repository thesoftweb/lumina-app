<?php

namespace App\Filament\Resources\Attendances;

use App\Enums\NavGroup;
use App\Filament\Concerns\ChecksNavigationAccess;
use App\Filament\Resources\Attendances\Pages\CreateAttendance;
use App\Filament\Resources\Attendances\Pages\EditAttendance;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Attendances\Pages\ViewAttendance;
use App\Filament\Resources\Attendances\Schemas\AttendanceForm;
use App\Filament\Resources\Attendances\Schemas\AttendanceInfolist;
use App\Filament\Resources\Attendances\Tables\AttendancesTable;
use App\Models\Attendance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AttendanceResource extends Resource
{
    use ChecksNavigationAccess;

    protected static ?string $model = Attendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Academic;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Frequências';

    protected static ?string $recordTitleAttribute = 'date';

    protected static ?string $modelLabel = 'Frequência';

    protected static ?string $pluralModelLabel = 'Frequências';

    public static function form(Schema $schema): Schema
    {
        return AttendanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendancesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return ! Auth::user()->hasRole('teacher') && parent::canCreate();
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
            'index' => ListAttendances::route('/'),
            'create' => CreateAttendance::route('/create'),
            'view' => ViewAttendance::route('/{record}'),
            'edit' => EditAttendance::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        return $user->hasRole('teacher')
            ? $query->where('teacher_id', $user->teacher?->id ?? 0)
            : $query;
    }
}
