<?php

namespace App\Filament\Resources\LessonPlans;

use App\Enums\NavGroup;
use App\Filament\Concerns\ChecksNavigationAccess;
use App\Filament\Resources\LessonPlans\Pages\CreateLessonPlan;
use App\Filament\Resources\LessonPlans\Pages\EditLessonPlan;
use App\Filament\Resources\LessonPlans\Pages\ListLessonPlans;
use App\Filament\Resources\LessonPlans\Pages\ViewLessonPlan;
use App\Filament\Resources\LessonPlans\Schemas\LessonPlanForm;
use App\Filament\Resources\LessonPlans\Schemas\LessonPlanInfolist;
use App\Filament\Resources\LessonPlans\Tables\LessonPlansTable;
use App\Models\LessonPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class LessonPlanResource extends Resource
{
    use ChecksNavigationAccess;

    protected static ?string $model = LessonPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Classroom;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Planos de aula';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'Plano de Aula';

    protected static ?string $pluralModelLabel = 'Planos de Aula';

    public static function form(Schema $schema): Schema
    {
        return LessonPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LessonPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonPlansTable::configure($table);
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
            'index' => ListLessonPlans::route('/'),
            'create' => CreateLessonPlan::route('/create'),
            'view' => ViewLessonPlan::route('/{record}'),
            'edit' => EditLessonPlan::route('/{record}/edit'),
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
