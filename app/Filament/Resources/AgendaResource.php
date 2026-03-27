<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgendaResource\Pages;
use App\Models\Agenda;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AgendaResource extends Resource
{
    protected static ?string $model = Agenda::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Agendas';

    protected static ?string $modelLabel = 'Agenda';

    protected static ?string $pluralModelLabel = 'Agendas';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Agenda')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),

                        RichEditor::make('description')
                            ->label('Descrição')
                            ->columnSpanFull(),

                        DateTimePicker::make('date')
                            ->label('Data e Hora')
                            ->required(),

                        Toggle::make('global')
                            ->label('Agenda Global')
                            ->helperText('Se ativada, a agenda aparecerá para todos os alunos. Caso contrário, aparecerá apenas para a turma selecionada.')
                            ->default(false)
                            ->reactive(),

                        Select::make('classroom_id')
                            ->label('Turma')
                            ->relationship('classroom', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (callable $get) => !$get('global'))
                            ->hidden(fn (callable $get) => $get('global')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label('Data e Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Turma')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\IconColumn::make('global')
                    ->label('Global')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('global')
                    ->label('Apenas Globais')
                    ->query(fn (Builder $query) => $query->where('global', true)),

                Tables\Filters\Filter::make('classroom')
                    ->label('Por Turma')
                    ->form([
                        Select::make('classroom_id')
                            ->label('Turma')
                            ->relationship('classroom', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['classroom_id'] ?? null,
                            fn (Builder $query, $classroomId) => $query->where('classroom_id', $classroomId),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgendas::route('/'),
            'create' => Pages\CreateAgenda::route('/create'),
            'edit' => Pages\EditAgenda::route('/{record}/edit'),
        ];
    }
}
