<?php

namespace App\Filament\Resources\Classrooms\RelationManagers;

use App\Models\ClassroomSubject;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Carga horária anual de cada disciplina da turma (vai para o boletim e o histórico escolar). As
 * disciplinas entram e saem da turma pelo formulário; aqui só se informa a carga horária.
 */
class SubjectWorkloadsRelationManager extends RelationManager
{
    protected static string $relationship = 'classroomSubjects';

    protected static ?string $title = 'Carga horária';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $total = (int) $ownerRecord->classroomSubjects()->sum('workload_hours');

        return $total > 0 ? "{$total} h" : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['subject', 'teachers']))
            ->description('Carga horária anual de cada disciplina, em horas. A soma é a carga horária da série no histórico escolar.')
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->weight('medium'),
                TextColumn::make('teachers.name')
                    ->label('Docentes')
                    ->listWithLineBreaks()
                    ->placeholder('-'),
                TextInputColumn::make('workload_hours')
                    ->label('Carga horária (h)')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:0', 'max:2000'])
                    ->placeholder('0')
                    ->extraInputAttributes(['class' => 'text-center', 'min' => 0, 'max' => 2000])
                    ->updateStateUsing(function (ClassroomSubject $record, mixed $state): mixed {
                        $record->update(['workload_hours' => filled($state) ? (int) $state : null]);

                        return $state;
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nenhuma disciplina na turma')
            ->emptyStateDescription('Marque as disciplinas da turma no formulário acima e salve.');
    }
}
