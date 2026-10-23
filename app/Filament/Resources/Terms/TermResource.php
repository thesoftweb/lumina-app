<?php

namespace App\Filament\Resources\Terms;

use App\Enums\NavGroup;
use App\Filament\Concerns\ChecksNavigationAccess;
use App\Filament\Resources\Terms\Pages\ManageTerms;
use App\Models\Term;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use App\Models\AcademicYear;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Validation\Rules\Unique;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TermResource extends Resource
{
    use ChecksNavigationAccess;

    protected static ?string $model = Term::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::Settings;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Bimestres';

    protected static ?string $modelLabel = 'Bimestre';

    protected static ?string $pluralModelLabel = 'Bimestres';

    protected static ?string $recordTitleAttribute = 'name';

    /** Anos dos anos letivos cadastrados mais os anos que já possuem bimestres. */
    protected static function yearOptions(): array
    {
        return AcademicYear::query()->pluck('year')
            ->merge(Term::query()->whereNotNull('year')->pluck('year'))
            ->unique()->sortDesc()
            ->mapWithKeys(fn ($year) => [$year => (string) $year])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('year')
                ->label('Ano letivo')
                ->options(fn (): array => static::yearOptions())
                ->required()
                ->live()
                ->helperText('Cadastre antes o ano em Configurações > Ano letivo.'),
            TextInput::make('order')
                ->label('Ordem')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->required()
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, callable $get) => $rule->where('year', $get('year')),
                )
                ->validationMessages(['unique' => 'Já existe um bimestre com esta ordem neste ano.']),
            TextInput::make('name')
                ->label('Nome')
                ->placeholder('1º Bimestre')
                ->required()
                ->maxLength(255),
            DatePicker::make('start_date')
                ->label('Início')
                ->required(),
            DatePicker::make('end_date')
                ->label('Fim')
                ->required()
                ->afterOrEqual('start_date'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')->label('Ano')->sortable(),
                TextColumn::make('order')->label('Ordem')->sortable()->alignCenter(),
                TextColumn::make('name')->label('Bimestre')->searchable(),
                TextColumn::make('start_date')->label('Início')->date('d/m/Y'),
                TextColumn::make('end_date')->label('Fim')->date('d/m/Y'),
                TextColumn::make('grades_count')->label('Notas')->counts('grades')->badge()->alignCenter(),
                TextColumn::make('closed_at')
                    ->label('Situação')
                    ->state(fn (Term $record): string => $record->isClosed() ? 'Fechado' : 'Aberto')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Fechado' ? 'gray' : 'success')
                    ->icon(fn (string $state): Heroicon => $state === 'Fechado' ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
                    ->tooltip(fn (Term $record): ?string => $record->isClosed()
                        ? 'Fechado em '.$record->closed_at->format('d/m/Y H:i').($record->closedBy ? ' por '.$record->closedBy->name : '')
                        : 'Professores podem lançar e alterar notas'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->orderByDesc('year')->orderBy('order'))
            ->paginated(false)
            ->filters([
                SelectFilter::make('year')->label('Ano letivo')->options(fn (): array => static::yearOptions()),
            ])
            ->recordActions([
                Action::make('close')
                    ->label('Fechar')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('warning')
                    ->visible(fn (Term $record): bool => ! $record->isClosed())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Term $record): string => "Fechar {$record->name} ({$record->year})?")
                    ->modalDescription('Os professores não poderão mais lançar nem alterar notas deste bimestre. A secretaria continua podendo corrigir notas pela matrícula, com registro do motivo.')
                    ->modalSubmitActionLabel('Fechar bimestre')
                    ->action(function (Term $record): void {
                        $record->update(['closed_at' => now(), 'closed_by' => auth()->id()]);
                        Notification::make()->success()->title('Bimestre fechado')->send();
                    }),
                Action::make('reopen')
                    ->label('Reabrir')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('gray')
                    ->visible(fn (Term $record): bool => $record->isClosed())
                    ->requiresConfirmation()
                    ->modalDescription('Os professores voltam a poder alterar as notas deste bimestre.')
                    ->action(function (Term $record): void {
                        $record->update(['closed_at' => null, 'closed_by' => null]);
                        Notification::make()->success()->title('Bimestre reaberto')->send();
                    }),
                EditAction::make(),
                static::deleteAction(),
            ]);
    }

    protected static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->before(function (DeleteAction $action, Model $record): void {
            if ($reason = $record->deletionBlockedReason()) {
                Notification::make()->danger()->title('Não é possível excluir')->body($reason)->send();
                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTerms::route('/'),
        ];
    }
}
