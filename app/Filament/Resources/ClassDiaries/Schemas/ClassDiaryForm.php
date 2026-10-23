<?php

namespace App\Filament\Resources\ClassDiaries\Schemas;

use App\Enums\ClassDiaryStatus;
use App\Models\ClassroomSubject;
use App\Models\Subject;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ClassDiaryForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();
        $isTeacher = $user->hasRole('teacher');

        return $schema
            ->components([
                Select::make('classroom_id')
                    ->relationship(
                        'classroom',
                        'name',
                        fn ($query) => $isTeacher && $user->teacher
                            ? $query->whereHas('teachers', fn ($q) => $q->where('teacher_id', $user->teacher->id))
                            : $query
                    )
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->hidden($isTeacher)
                    ->visible(! $isTeacher)
                    ->default($isTeacher ? $user->teacher?->id : null),

                Select::make('subject_id')
                    ->options(function (callable $get) use ($isTeacher, $user) {
                        if (! $isTeacher) {
                            return Subject::query()->orderBy('name')->pluck('name', 'id');
                        }

                        return ClassroomSubject::query()
                            ->where('classroom_id', $get('classroom_id'))
                            ->whereHas('teachers', fn ($query) => $query->whereKey($user->teacher?->id ?? 0))
                            ->with('subject')
                            ->get()
                            ->pluck('subject.name', 'subject_id');
                    })
                    ->required()
                    ->searchable()
                    ->preload(),

                DatePicker::make('date')
                    ->required()
                    ->label('Data da Aula'),

                RichEditor::make('content')
                    ->required()
                    ->columnSpanFull()
                    ->label('Conteúdo Ministrado'),

                Textarea::make('activities')
                    ->columnSpanFull()
                    ->label('Atividades Realizadas'),

                Textarea::make('homework')
                    ->columnSpanFull()
                    ->label('Tarefas de Casa'),

                Textarea::make('observations')
                    ->columnSpanFull()
                    ->label('Observações'),

                Select::make('status')
                    ->options(ClassDiaryStatus::class)
                    ->default(ClassDiaryStatus::Draft)
                    ->label('Status'),
            ]);
    }
}
