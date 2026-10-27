<?php

namespace App\Filament\Resources\PreEnrollments\Tables;

use App\Enums\LeadSource;
use App\Enums\PreEnrollmentType;
use App\Filament\Resources\PreEnrollments\Actions\PreEnrollmentActions;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use App\Filament\Resources\PreEnrollments\Schemas\PreEnrollmentInfolist;
use App\Models\AcademicYear;
use App\Models\AdmissionOffer;
use App\Models\PreEnrollment;
use App\Models\User;
use App\Rules\Cpf;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PreEnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['academicYear', 'offer', 'assignee']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Protocolo')->searchable()->copyable()->toggleable(),
                TextColumn::make('created_at')->label('Recebida')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('student_name')->label('Aluno')->weight('semibold')
                    ->searchable()->sortable()
                    ->description(fn (PreEnrollment $record): ?string => ($age = $record->studentAgeAtYearStart()) !== null
                        ? "{$age} anos no início de {$record->academicYear->year}" : null),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->description(fn (PreEnrollment $record): ?string => $record->responded_at
                        ? 'Confirmou pelo link em '.$record->responded_at->format('d/m/Y') : null),
                TextColumn::make('offer.name')->label('Série de interesse')->placeholder('Não escolhida')->toggleable(),
                TextColumn::make('guardian_name')->label('Responsável')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('guardian_name', 'like', "%{$search}%")
                        ->when(strlen(Cpf::digits($search)) >= 3, fn (Builder $query) => $query->orWhere('guardian_document', 'like', '%'.Cpf::digits($search).'%')))
                    ->description(fn (PreEnrollment $record): ?string => PreEnrollmentInfolist::formatPhone($record->guardian_phone)),
                TextColumn::make('source')->label('Origem')->badge()->color('gray')->placeholder('-')->toggleable(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('last_contact_at')->label('Último contato')->since()->sortable()->placeholder('Sem contato'),
                TextColumn::make('assignee.name')->label('Atendente')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('academicYear.description')->label('Ano letivo')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Um ano por vez: fichas de anos diferentes nunca aparecem misturadas por padrão
                SelectFilter::make('academic_year_id')
                    ->label('Ano letivo')
                    ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                    ->default(fn (): ?int => PreEnrollmentResource::defaultAcademicYearId())
                    ->selectablePlaceholder(false),
                SelectFilter::make('type')->label('Tipo')->options(PreEnrollmentType::class),
                SelectFilter::make('admission_offer_id')
                    ->label('Série de interesse')
                    ->options(fn (): array => AdmissionOffer::query()->with('academicYear')->get()
                        ->mapWithKeys(fn (AdmissionOffer $offer): array => [$offer->id => "{$offer->name} ({$offer->academicYear->year})"])->all())
                    ->searchable(),
                SelectFilter::make('source')->label('Origem')->options(LeadSource::class),
                SelectFilter::make('assigned_to')->label('Atendente')
                    ->options(fn (): array => User::query()->role(['secretary', 'admin'])->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ...PreEnrollmentActions::all(),
                    EditAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhuma pré-matrícula neste ano letivo')
            ->emptyStateDescription('As fichas enviadas pelo link público aparecem aqui. Abra as inscrições e cadastre as vagas em Configurações › Ano letivo.');
    }
}
