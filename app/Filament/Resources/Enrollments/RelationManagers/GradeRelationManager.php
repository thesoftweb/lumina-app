<?php

namespace App\Filament\Resources\Enrollments\RelationManagers;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GradeRelationManager extends RelationManager
{
    protected static string $relationship = 'grade';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('classroom_subject_id')
                    ->label('Disciplina')
                    ->relationship('classroomSubject', 'id')
                    ->options(Subject::all()->pluck('name', 'id')),
                Select::make('term_id')
                    ->label('Bimestre')
                    ->options(Term::all()->pluck('name', 'id')),
                TextInput::make('grade')
                    ->label('Nota')
                    ->numeric(),
                Select::make('teacher_id')
                    ->label('Docente')
                    ->relationship('teacher', 'id')
                    ->options(Teacher::all()->pluck('name', 'id')),
                Textarea::make('comments')
                    ->label('Comentários')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('classroom_subject_id')
                    ->numeric(),
                TextEntry::make('term_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('grade')
                    ->numeric(),
                TextEntry::make('teacher_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('comments')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('classroomSubject.subject.name')
                    ->label('Disciplina')
                    ->sortable(),
                TextColumn::make('term.name')
                    ->label('Período')
                    ->sortable(),
                TextColumn::make('grade')
                    ->label('Nota')
                    ->sortable(),
                TextColumn::make('teacher.name')
                    ->label('Docente')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
