<?php

namespace App\Filament\Pages;

use App\Models\Student;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class Birthdays extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Aniversariantes';

    protected static ?string $title = 'Aniversariantes';

    protected string $view = 'filament.pages.birthdays';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('teacher')
            && Auth::user()?->can('view_birthdays')
            && (bool) Auth::user()?->teacher;
    }

    public function table(Table $table): Table
    {
        $teacherId = Auth::user()->teacher?->id ?? 0;
        $dayExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%d', date_of_birth) AS INTEGER)"
            : 'DAY(date_of_birth)';

        return $table
            ->query(
                Student::query()
                    ->whereNotNull('date_of_birth')
                    ->whereHas('enrollments', fn (Builder $query) => $query
                        ->where('status', 'active')
                        ->whereHas('classroom.teachers', fn (Builder $query) => $query->whereKey($teacherId)))
                    ->with(['enrollments' => fn ($query) => $query
                        ->where('status', 'active')
                        ->whereHas('classroom.teachers', fn (Builder $query) => $query->whereKey($teacherId))
                        ->with('classroom')])
                    ->orderByRaw($dayExpression)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Aluno')
                    ->icon(Heroicon::OutlinedUser)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('classrooms')
                    ->label('Turma(s)')
                    ->getStateUsing(fn (Student $record): array => $record->enrollments
                        ->pluck('classroom.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all())
                    ->badge()
                    ->separator(','),
                TextColumn::make('date_of_birth')
                    ->label('Aniversário')
                    ->date('d/m')
                    ->icon(Heroicon::OutlinedCake),
                TextColumn::make('age')
                    ->label('Idade em '.now()->year)
                    ->getStateUsing(fn (Student $record): string => (now()->year - $record->date_of_birth->year).' anos')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('Mês')
                    ->options(self::monthOptions())
                    ->default(now()->month)
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $month): Builder => $query->whereMonth('date_of_birth', (int) $month)
                    )),
            ])
            ->filtersFormColumns(1)
            ->emptyStateHeading('Nenhum aniversariante neste mês')
            ->emptyStateDescription('Selecione outro mês no filtro para consultar os demais aniversariantes.')
            ->emptyStateIcon(Heroicon::OutlinedCake)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /** @return array<int, string> */
    private static function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [
                $month => ucfirst(Carbon::create(null, $month)->translatedFormat('F')),
            ])
            ->all();
    }
}
