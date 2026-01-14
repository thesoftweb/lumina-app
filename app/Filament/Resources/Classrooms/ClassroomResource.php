<?php

namespace App\Filament\Resources\Classrooms;

use App\Filament\Resources\Classrooms\Pages\CreateClassroom;
use App\Filament\Resources\Classrooms\Pages\EditClassroom;
use App\Filament\Resources\Classrooms\Pages\ListClassrooms;
use App\Filament\Resources\Classrooms\Pages\ViewClassroom;
use App\Filament\Resources\Classrooms\Schemas\ClassroomForm;
use App\Filament\Resources\Classrooms\Schemas\ClassroomInfolist;
use App\Filament\Resources\Classrooms\Tables\ClassroomsTable;
use App\Models\Classroom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Turmas';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    public static function form(Schema $schema): Schema
    {
        return ClassroomForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClassroomInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassroomsTable::configure($table);
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
            'index' => ListClassrooms::route('/'),
            'create' => CreateClassroom::route('/create'),
            'view' => ViewClassroom::route('/{record}'),
            'edit' => EditClassroom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()->hasRole('teacher')) {
            // Filter classrooms to only those of the current teacher
            $teacher = Auth::user()->teacher;
            if ($teacher) {
                $query->whereHas('teachers', function ($q) use ($teacher) {
                    $q->where('teacher_id', $teacher->id);
                });
            }
        }

        return $query;
    }
}
