<?php

namespace App\Filament\Resources\PreEnrollments;

use App\Enums\NavGroup;
use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Filament\Concerns\ChecksNavigationAccess;
use App\Filament\Resources\PreEnrollments\Pages\CreatePreEnrollment;
use App\Filament\Resources\PreEnrollments\Pages\EditPreEnrollment;
use App\Filament\Resources\PreEnrollments\Pages\ListPreEnrollments;
use App\Filament\Resources\PreEnrollments\Pages\ViewPreEnrollment;
use App\Filament\Resources\PreEnrollments\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\PreEnrollments\Schemas\PreEnrollmentForm;
use App\Filament\Resources\PreEnrollments\Schemas\PreEnrollmentInfolist;
use App\Filament\Resources\PreEnrollments\Tables\PreEnrollmentsTable;
use App\Models\AcademicYear;
use App\Models\PreEnrollment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Fichas de pré-matrícula (leads) recebidas pelo link público ou registradas pela secretaria. */
class PreEnrollmentResource extends Resource
{
    use ChecksNavigationAccess;

    protected static ?string $model = PreEnrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Academic;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pré-matrículas';

    protected static ?string $modelLabel = 'Pré-matrícula';

    protected static ?string $pluralModelLabel = 'Pré-matrículas';

    protected static ?string $recordTitleAttribute = 'student_name';

    /** Ano que a lista mostra por padrão: o que está recebendo fichas, ou o mais recente. */
    public static function defaultAcademicYearId(): ?int
    {
        return AcademicYear::query()->acceptingPreEnrollments()->orderByDesc('year')->value('id')
            ?? AcademicYear::query()->orderByDesc('year')->value('id');
    }

    /**
     * Novato ou rematrícula pelo histórico do aluno encontrado no cadastro (mesmo critério da ficha pública):
     * o tipo não é escolhido pela secretaria.
     *
     * @return array{type: PreEnrollmentType, previous_enrollment_id: ?int}
     */
    public static function classify(PreEnrollment $record): array
    {
        $year = $record->academicYear ?? AcademicYear::query()->find($record->academic_year_id);
        $previous = $year ? PreEnrollmentInfolist::matchedStudent($record)?->previousEnrollmentBefore($year) : null;

        return [
            'type' => $previous ? PreEnrollmentType::Reenrollment : PreEnrollmentType::NewStudent,
            'previous_enrollment_id' => $previous?->getKey(),
        ];
    }

    /** Fichas novas (ainda sem contato) dos anos que estão recebendo pré-matrículas. */
    public static function getNavigationBadge(): ?string
    {
        $count = PreEnrollment::query()
            ->where('status', PreEnrollmentStatus::New->value)
            ->whereIn('academic_year_id', AcademicYear::query()->acceptingPreEnrollments()->select('id'))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Fichas novas aguardando o primeiro contato';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['student_name', 'guardian_name', 'code'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Ano letivo' => $record->academicYear?->description,
            'Responsável' => $record->guardian_name,
            'Situação' => $record->status?->getLabel(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PreEnrollmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PreEnrollmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreEnrollmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreEnrollments::route('/'),
            'create' => CreatePreEnrollment::route('/create'),
            'view' => ViewPreEnrollment::route('/{record}'),
            'edit' => EditPreEnrollment::route('/{record}/edit'),
        ];
    }
}
