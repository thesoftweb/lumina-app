<?php

namespace App\Filament\Resources\AcademicYears\RelationManagers;

use App\Enums\Shift;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\Classroom;
use App\Models\Level;
use App\Models\Plan;
use App\Services\Admissions\OffersFromClassrooms;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Vagas e valores divulgados na ficha pública de pré-matrícula do ano. O valor da mensalidade vem
 * do plano, que precisa ser deste mesmo ano letivo: um plano de 2026 nunca aparece na ficha de 2027.
 */
class AdmissionOffersRelationManager extends RelationManager
{
    protected static string $relationship = 'admissionOffers';

    protected static ?string $title = 'Vagas e valores';

    protected static ?string $modelLabel = 'vaga';

    protected static ?string $pluralModelLabel = 'vagas';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-currency-dollar';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['secretary', 'admin']);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        /** @var AcademicYear $year */
        $year = $this->getOwnerRecord();

        return $schema
            ->columns(2)
            ->components([
                Select::make('classroom_id')->label('Turma')
                    ->options(fn (): array => Classroom::query()->where('academic_year_id', $year->getKey())->pluck('name', 'id')->all())
                    ->searchable()->live()->columnSpanFull()
                    ->helperText('A família escolhe esta turma na ficha, e a conversão em matrícula já a traz marcada. Só aparecem turmas de '.$year->description.'.')
                    // Preenche nome, nível, série e plano a partir da turma; tudo pode ser ajustado depois
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if ($classroom = Classroom::query()->find($state)) {
                            foreach (collect(AdmissionOffer::attributesFromClassroom($classroom))->only(['name', 'level_id', 'grade_label', 'plan_id']) as $field => $value) {
                                $set($field, $value);
                            }
                        }
                    }),
                TextInput::make('name')->label('Nome da vaga')
                    ->placeholder('3º ano - Fundamental I')
                    ->required()->maxLength(255)->columnSpanFull(),
                Select::make('level_id')->label('Nível')
                    ->options(fn (): array => Level::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('grade_label')->label('Série / ano escolar')->placeholder('3º ano')->maxLength(60),
                Select::make('shift')->label('Turno')->options(Shift::class),
                Select::make('plan_id')->label('Plano de mensalidade')
                    ->options(fn (): array => Plan::query()->where('academic_year_id', $year->getKey())->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->helperText(new HtmlString('Só aparecem planos de '.e($year->description).'. Se a lista estiver vazia, cadastre antes o plano deste ano em <a class="underline" href="'.e(PlanResource::getUrl('create')).'" target="_blank">Planos e mensalidades</a>, informando o ano letivo.')),
                TextInput::make('enrollment_fee')->label('Taxa de matrícula')->numeric()->minValue(0)->prefix('R$'),
                TextInput::make('vacancies')->label('Quantidade de vagas (opcional)')->numeric()->minValue(0)
                    ->helperText('Em branco: a turma aparece na ficha sem limite e sem divulgar o número de vagas.'),
                Textarea::make('description')->label('Descrição exibida na ficha')->rows(3)->columnSpanFull(),
                TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
                Toggle::make('is_active')->label('Disponível na ficha')->default(true)->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with(['plan', 'level', 'classroom'])->withCount([
                'preEnrollments as taken_count' => fn ($query) => $query->where('status', '!=', 'lost'),
            ]))
            ->columns([
                TextColumn::make('name')->label('Vaga')->weight('semibold')
                    ->description(fn (AdmissionOffer $record): ?string => collect([$record->level?->name, $record->shift?->getLabel()])->filter()->implode(' · ') ?: null),
                TextColumn::make('classroom.name')->label('Turma')->placeholder('Sem turma'),
                TextColumn::make('plan.name')->label('Plano')->placeholder('Sem plano'),
                TextColumn::make('monthly')->label('Mensalidade')
                    ->state(fn (AdmissionOffer $record): ?float => $record->monthlyAmount())
                    ->money('BRL', locale: 'pt_BR')
                    ->placeholder('-'),
                TextColumn::make('enrollment_fee')->label('Taxa de matrícula')->money('BRL', locale: 'pt_BR')->placeholder('-'),
                TextColumn::make('taken_count')->label('Fichas / vagas')
                    ->formatStateUsing(fn ($state, AdmissionOffer $record): string => $record->vacancies !== null ? "{$state} / {$record->vacancies}" : (string) $state)
                    ->badge()
                    ->color(fn ($state, AdmissionOffer $record): string => $record->vacancies !== null && $state >= $record->vacancies ? 'danger' : 'gray'),
                IconColumn::make('is_active')->label('Na ficha')->boolean(),
            ])
            ->headerActions([
                Action::make('offersFromClassrooms')
                    ->label('Criar vagas a partir das turmas')
                    ->icon('heroicon-o-squares-plus')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => 'Cria uma vaga para cada turma de '.$this->getOwnerRecord()->description.' que ainda não tem vaga, com o plano da turma e sem quantidade de vagas (opcional). Rodar de novo só cria as que faltam.')
                    ->action(function (): void {
                        $created = app(OffersFromClassrooms::class)->create($this->getOwnerRecord());

                        $notification = Notification::make();
                        $created->isEmpty()
                            ? $notification->warning()->title('Nenhuma vaga nova')->body('Todas as turmas do ano já têm vaga, ou o ano ainda não tem turmas cadastradas.')
                            : $notification->success()->title($created->count().' vaga(s) criada(s)')->body($created->pluck('name')->implode(', '));
                        $notification->send();
                    }),
                CreateAction::make()->label('Nova vaga'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (AdmissionOffer $record): bool => $record->preEnrollments()->exists()),
            ])
            ->emptyStateHeading('Nenhuma vaga ofertada')
            ->emptyStateDescription('Use "Criar vagas a partir das turmas" para a família escolher a turma na ficha, ou cadastre as vagas uma a uma.');
    }
}
