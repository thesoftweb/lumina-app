<?php

namespace App\Filament\Resources\Classrooms\Schemas;

use App\Models\Subject;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;

class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->prefixIcon('heroicon-o-academic-cap')
                    ->label('Titulo')
                    ->required()
                    ->maxLength(255),
                Select::make('level_id')
                    ->prefixIcon('heroicon-o-book-open')
                    ->options(function () {
                        return \App\Models\Level::all()->pluck('name', 'id');
                    })
                    ->createOptionForm(function (Schema $schema) {
                        return $schema->components([
                            TextInput::make('name')
                                ->prefixIcon('heroicon-o-academic-cap')
                                ->label('Titulo')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->label('Detalhes')
                                ->maxLength(65535),
                        ]);
                    })
                    ->label('Nivel')
                    ->relationship('level', 'name')
                    ->required(),
                Select::make('teacher_ids')
                    ->prefixIcon('heroicon-o-user-group')
                    ->label('Profesores')
                    ->multiple()
                    ->relationship('teachers', 'name'),
                Fieldset::make('Disciplinas')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('subject_ids')
                            ->columnSpanFull()
                            ->label('Disciplinas')
                            ->relationship('subjects', 'name')
                            ->columns(2)
                            ->options(Subject::all()->pluck('name', 'id')),
                    ]),
            ]);
    }
}
