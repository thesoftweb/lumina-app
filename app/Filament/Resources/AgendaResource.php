<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgendaResource\Pages;
use App\Models\Schedule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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

class AgendaResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Agendas';

    protected static ?string $modelLabel = 'Agenda';

    protected static ?string $pluralModelLabel = 'Agendas';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('O que vai acontecer?')
                ->description('Escreva a informação como ela deve aparecer para alunos e responsáveis.')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Título')
                        ->prefixIcon('heroicon-o-megaphone')
                        ->placeholder('Ex.: Reunião de pais e responsáveis')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    RichEditor::make('description')
                        ->label('Descrição')
                        ->helperText('Inclua orientações, local ou materiais necessários.')
                        ->columnSpanFull(),
                    DateTimePicker::make('date')
                        ->label('Data e hora')
                        ->prefixIcon('heroicon-o-calendar-days')
                        ->seconds(false)
                        ->required()
                        ->columnSpanFull(),
                ]),
            Section::make('Quem deve receber?')
                ->description('Defina o alcance da publicação. Essa escolha controla a visibilidade no Portal do Aluno.')
                ->icon('heroicon-o-user-group')
                ->schema([
                    Toggle::make('global')
                        ->label('Publicar para toda a escola')
                        ->helperText('Ative para exibir a agenda a todos. Desative para escolher turmas específicas.')
                        ->default(false)
                        ->live(),
                    Select::make('classrooms')
                        ->label('Turmas que receberão a agenda')
                        ->relationship('classrooms', 'name')
                        ->multiple()
                        ->placeholder('Selecione uma ou mais turmas')
                        ->helperText('Alunos de qualquer turma selecionada verão esta publicação.')
                        ->searchable()
                        ->preload()
                        ->required(fn (callable $get): bool => ! $get('global'))
                        ->visible(fn (callable $get): bool => ! $get('global')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data e hora')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('classrooms.name')
                    ->label('Turmas')->badge()->separator(',')
                    ->placeholder('Todas as turmas')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\IconColumn::make('global')
                    ->label('Global')->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criada em')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('global')
                    ->label('Apenas globais')
                    ->query(fn (Builder $query): Builder => $query->where('global', true)),
                Tables\Filters\Filter::make('classroom')
                    ->label('Por turma')
                    ->form([
                        Select::make('classroom_id')
                            ->label('Turma')
                            ->relationship('classrooms', 'name')
                            ->searchable()->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['classroom_id'] ?? null,
                            fn (Builder $query, $classroomId): Builder => $query->whereHas(
                                'classrooms',
                                fn (Builder $query): Builder => $query->whereKey($classroomId),
                            ),
                        );
                    }),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('date');
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
