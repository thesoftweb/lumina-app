<?php

namespace App\Filament\Resources\PreEnrollments\Actions;

use App\Enums\CollectionChannel;
use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use App\Filament\Resources\PreEnrollments\Schemas\PreEnrollmentInfolist;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomPlan;
use App\Models\Company;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\PreEnrollment;
use App\Models\PreEnrollmentContact;
use App\Services\Admissions\PreEnrollmentConverter;
use App\Services\Admissions\ReenrollmentCampaign;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Ações da secretaria sobre a ficha, usadas na lista e na página da pré-matrícula. */
class PreEnrollmentActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [self::convert(), self::whatsApp(), self::sendReenrollmentLink(), self::copyReenrollmentLink(), self::registerContact(), self::markLost()];
    }

    public static function whatsApp(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->disabled(fn (PreEnrollment $record): bool => ! $record->whatsappUrl())
            ->modalHeading(fn (PreEnrollment $record): string => "Falar com {$record->guardian_name}")
            ->modalDescription('Revise a mensagem. Ao confirmar, o contato fica registrado e o WhatsApp abre com o texto pronto.')
            ->modalSubmitActionLabel('Abrir WhatsApp')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (PreEnrollment $record): array => ['message' => self::defaultMessage($record)])
            ->schema([
                Textarea::make('message')->label('Mensagem')->rows(8)->required(),
            ])
            ->action(function (PreEnrollment $record, array $data, Component $livewire): void {
                PreEnrollmentContact::record(
                    $record,
                    CollectionChannel::WhatsApp,
                    $data['message'],
                    $record->status === PreEnrollmentStatus::New ? PreEnrollmentStatus::InContact : null,
                );

                $url = $record->whatsappUrl($data['message']);
                $livewire->js('window.open('.json_encode($url).', "_blank")');

                Notification::make()->success()->title('Contato registrado')
                    ->body('Se o WhatsApp não abriu, use o botão abaixo.')
                    ->actions([Action::make('open')->label('Abrir WhatsApp')->url($url, shouldOpenInNewTab: true)])
                    ->send();
            });
    }

    /** Link próprio da rematrícula: a família confere os dados, escolhe a série e confirma. */
    public static function sendReenrollmentLink(): Action
    {
        return Action::make('sendReenrollmentLink')
            ->label('Enviar link de rematrícula')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->visible(fn (PreEnrollment $record): bool => self::hasReenrollmentLink($record))
            ->disabled(fn (PreEnrollment $record): bool => ! $record->whatsappUrl())
            ->modalHeading(fn (PreEnrollment $record): string => "Rematrícula de {$record->student_name}")
            ->modalDescription('Revise a mensagem. Ao confirmar, o contato fica registrado e o WhatsApp abre com o link da rematrícula.')
            ->modalSubmitActionLabel('Abrir WhatsApp')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (PreEnrollment $record): array => ['message' => self::reenrollmentMessage($record)])
            ->schema([
                Textarea::make('message')->label('Mensagem')->rows(8)->required(),
            ])
            ->action(function (PreEnrollment $record, array $data, Component $livewire): void {
                PreEnrollmentContact::record(
                    $record,
                    CollectionChannel::WhatsApp,
                    $data['message'],
                    $record->status === PreEnrollmentStatus::New ? PreEnrollmentStatus::InContact : null,
                );

                $url = $record->whatsappUrl($data['message']);
                $livewire->js('window.open('.json_encode($url).', "_blank")');

                Notification::make()->success()->title('Link de rematrícula registrado')
                    ->body('Se o WhatsApp não abriu, use o botão abaixo.')
                    ->actions([Action::make('open')->label('Abrir WhatsApp')->url($url, shouldOpenInNewTab: true)])
                    ->send();
            });
    }

    public static function copyReenrollmentLink(): Action
    {
        return Action::make('copyReenrollmentLink')
            ->label('Copiar link de rematrícula')
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->visible(fn (PreEnrollment $record): bool => self::hasReenrollmentLink($record))
            ->action(function (PreEnrollment $record, Component $livewire): void {
                $url = $record->reenrollmentUrl();
                $livewire->js('navigator.clipboard?.writeText('.json_encode($url).')');

                Notification::make()->success()->title('Link copiado')->body($url)->send();
            });
    }

    /**
     * Gera as fichas de rematrícula de um ano para o seguinte. Rodar de novo só cria as que faltam,
     * então a secretaria pode completar cadastros e gerar outra vez.
     */
    public static function generateReenrollments(): Action
    {
        return Action::make('generateReenrollments')
            ->label('Gerar rematrículas')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('success')
            ->modalHeading('Gerar fichas de rematrícula')
            ->modalDescription('Cria uma ficha de rematrícula para cada aluno do ano de origem que ainda não tem matrícula nem ficha no ano novo. Os dados vêm do cadastro, e cada ficha ganha um link para a família confirmar.')
            ->modalSubmitActionLabel('Gerar fichas')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (): array => [
                'source_year_id' => AcademicYear::query()->where('is_default', true)->value('id'),
                'target_year_id' => PreEnrollmentResource::defaultAcademicYearId(),
            ])
            ->schema([
                Select::make('source_year_id')->label('Alunos de')
                    ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                    ->required()->live(),
                Select::make('target_year_id')->label('Rematrícula para')
                    ->options(fn (): array => AcademicYear::query()->orderByDesc('year')->pluck('description', 'id')->all())
                    ->required()->live()
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        [$source, $target] = self::campaignYears($get);

                        if ($source && $target && $target->year <= $source->year) {
                            $fail('O ano da rematrícula precisa ser posterior ao ano de origem.');
                        }
                    }),
                Callout::make('Resumo')
                    ->info()
                    ->description(fn (Get $get): ?string => self::campaignYearsAreValid($get) ? self::campaignSummary($get) : null)
                    ->visible(fn (Get $get): bool => self::campaignYearsAreValid($get)),
                Callout::make('Cadastros incompletos')
                    ->warning()
                    ->description(fn (Get $get): ?string => self::incompleteSummary($get))
                    ->visible(fn (Get $get): bool => filled(self::incompleteSummary($get))),
            ])
            ->action(function (array $data): void {
                $source = AcademicYear::query()->findOrFail($data['source_year_id']);
                $target = AcademicYear::query()->findOrFail($data['target_year_id']);
                $created = app(ReenrollmentCampaign::class)->generate($source, $target)->count();

                Notification::make()->success()
                    ->title($created ? "{$created} ficha(s) de rematrícula criada(s)" : 'Nenhuma ficha nova')
                    ->body($created
                        ? "As fichas estão em Pré-matrículas › {$target->description}, com o tipo Rematrícula. Envie o link a cada família pelo WhatsApp."
                        : "Todos os alunos de {$source->description} já têm matrícula ou ficha em {$target->description}.")
                    ->send();
            });
    }

    public static function registerContact(): Action
    {
        return Action::make('registerContact')
            ->label('Registrar contato')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalHeading(fn (PreEnrollment $record): string => "Contato com {$record->guardian_name}")
            ->modalSubmitActionLabel('Registrar')
            ->schema([
                Select::make('channel')->label('Canal')
                    ->options(CollectionChannel::class)
                    ->default(CollectionChannel::Phone->value)
                    ->required()->native(false),
                Select::make('status_after')->label('Nova situação da ficha')
                    ->options(collect(PreEnrollmentStatus::open())->mapWithKeys(fn (PreEnrollmentStatus $status): array => [$status->value => $status->getLabel()])->all())
                    ->placeholder('Manter a situação atual')
                    ->hidden(fn (PreEnrollment $record): bool => $record->isConverted()),
                Textarea::make('notes')->label('O que foi conversado')->rows(3),
            ])
            ->action(function (PreEnrollment $record, array $data): void {
                PreEnrollmentContact::record(
                    $record,
                    $data['channel'] instanceof CollectionChannel ? $data['channel'] : CollectionChannel::from($data['channel']),
                    $data['notes'] ?? null,
                    filled($data['status_after'] ?? null) ? PreEnrollmentStatus::from($data['status_after']) : null,
                );

                Notification::make()->success()->title('Contato registrado')->send();
            });
    }

    public static function markLost(): Action
    {
        return Action::make('markLost')
            ->label('Marcar desistência')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (PreEnrollment $record): bool => $record->status->isOpen())
            ->modalHeading('Marcar desistência')
            ->modalDescription('A ficha sai da lista de abertas e libera a vaga. Ela pode ser reaberta depois, pela edição.')
            ->schema([
                TextInput::make('lost_reason')->label('Motivo')
                    ->datalist(['Escolheu outra escola', 'Valor da mensalidade', 'Mudou de cidade', 'Sem vaga na série', 'Não respondeu aos contatos'])
                    ->required()->maxLength(255),
            ])
            ->action(function (PreEnrollment $record, array $data): void {
                $record->update(['status' => PreEnrollmentStatus::Lost, 'lost_reason' => $data['lost_reason']]);

                Notification::make()->success()->title('Desistência registrada')->send();
            });
    }

    public static function convert(): Action
    {
        return Action::make('convert')
            ->label('Converter em matrícula')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('primary')
            ->visible(fn (PreEnrollment $record): bool => ! $record->isConverted())
            ->modalHeading(fn (PreEnrollment $record): string => "Matricular {$record->student_name} em {$record->academicYear->description}")
            ->modalDescription('Cria a matrícula reservada no ano da ficha, reaproveitando o responsável e o aluno se já existirem. Taxa e mensalidades são geradas depois, pela matrícula.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Criar matrícula')
            ->modalSubmitAction(fn (Action $action, PreEnrollment $record) => self::classroomsFor($record)->exists() ? $action : false)
            ->fillForm(fn (PreEnrollment $record): array => [
                // A turma que a família escolheu na ficha vem marcada, com o plano da vaga se estiver vinculado a ela
                ...(($classroomId = $record->offer?->classroom_id) && self::classroomsFor($record)->whereKey($classroomId)->exists() ? [
                    'classroom_id' => $classroomId,
                    'plan_id' => self::planOptions($record, $classroomId)->has($record->offer->plan_id) ? $record->offer->plan_id : null,
                ] : []),
                'day_of_payment' => 10,
                'enrollment_date' => today()->toDateString(),
                'status' => EnrollmentStatus::Reserved->value,
            ])
            ->schema(fn (PreEnrollment $record): array => self::classroomsFor($record)->exists() ? [
                Select::make('classroom_id')->label('Turma')
                    ->options(fn (): array => self::classroomsFor($record)->pluck('name', 'id')->all())
                    ->helperText('Só aparecem turmas de '.$record->academicYear->description.'.')
                    ->required()->live()->searchable()
                    // O plano divulgado na ficha vem marcado quando está vinculado à turma escolhida
                    ->afterStateUpdated(fn (Set $set, $state) => $set('plan_id', ($planId = $record->offer?->plan_id) && self::planOptions($record, $state)->has($planId) ? $planId : null)),
                Select::make('plan_id')->label('Plano de pagamento')
                    ->options(fn (Get $get): array => self::planOptions($record, $get('classroom_id'))->all())
                    ->helperText($record->offer?->plan ? "Plano divulgado na ficha: {$record->offer->plan->name}." : null)
                    ->required()->live(),
                TextEntry::make('monthly_amount')->label('Mensalidade do plano')
                    ->state(fn (Get $get): ?float => self::planAmount($get('plan_id')))
                    ->money('BRL', locale: 'pt_BR')
                    ->helperText('Valor fixo configurado no plano.')
                    ->placeholder('Escolha o plano'),
                TextInput::make('day_of_payment')->label('Dia do vencimento')->numeric()->minValue(1)->maxValue(31)->required(),
                DatePicker::make('enrollment_date')->label('Data da matrícula')->required(),
                Select::make('status')->label('Situação da matrícula')
                    ->options([
                        EnrollmentStatus::Reserved->value => EnrollmentStatus::Reserved->getLabel().' (vaga garantida para o ano)',
                        EnrollmentStatus::Active->value => EnrollmentStatus::Active->getLabel(),
                    ])
                    ->required(),
                // Desconto só se negocia com quem já é aluno; para novatos a seção nem aparece
                Section::make('Negociação')
                    ->description('Rematrícula: sem desconto, o desconto configurado no plano ou um desconto combinado com a família. Fica registrado na matrícula.')
                    ->visible(fn (): bool => self::isReturning($record))
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('discount_mode')->label('Desconto')->inline()->live()->columnSpanFull()
                            ->options(fn (Get $get): array => [
                                'none' => 'Sem desconto',
                                'plan' => ($label = self::plan($get('plan_id'))?->discountLabel()) ? "Desconto do plano ({$label})" : 'Desconto do plano',
                                'custom' => 'Desconto negociado',
                            ])
                            ->disableOptionWhen(fn (string $value, Get $get): bool => $value === 'plan' && ! self::plan($get('plan_id'))?->has_discount)
                            ->default('none')->required(),
                        Select::make('discount_type')->label('Tipo de desconto')
                            ->options(['percentage' => 'Percentual (%)', 'fixed' => 'Valor fixo (R$)'])
                            ->visible(fn (Get $get): bool => $get('discount_mode') === 'custom')
                            ->required(fn (Get $get): bool => $get('discount_mode') === 'custom')
                            ->live(),
                        TextInput::make('discount_value')->label('Valor do desconto')->numeric()->minValue(0.01)
                            ->maxValue(fn (Get $get): ?float => $get('discount_type') === 'percentage' ? 100 : self::planAmount($get('plan_id')))
                            ->visible(fn (Get $get): bool => $get('discount_mode') === 'custom')
                            ->required(fn (Get $get): bool => $get('discount_mode') === 'custom')
                            ->live(onBlur: true),
                        TextEntry::make('negotiated_amount')->label('Mensalidade com o desconto')
                            ->state(fn (Get $get): ?float => self::negotiatedAmount($get))
                            ->money('BRL', locale: 'pt_BR')
                            ->placeholder('Informe o plano e o desconto')
                            ->visible(fn (Get $get): bool => in_array($get('discount_mode'), ['plan', 'custom'], true))
                            ->columnSpanFull(),
                        Textarea::make('discount_reason')->label('Motivo do desconto')->rows(2)->columnSpanFull()
                            ->visible(fn (Get $get): bool => in_array($get('discount_mode'), ['plan', 'custom'], true))
                            ->required(fn (Get $get): bool => $get('discount_mode') === 'custom'),
                    ]),
            ] : [
                Callout::make('Cadastre as turmas de '.$record->academicYear->description.' antes de converter')
                    ->warning()
                    ->description('A matrícula precisa de uma turma do mesmo ano letivo da ficha. As turmas do ano atual não servem: cadastre as turmas novas informando o ano letivo.')
                    ->actions([
                        Action::make('createClassroom')->label('Cadastrar turma')
                            ->url(ClassroomResource::getUrl('create', ['academic_year_id' => $record->academic_year_id]), shouldOpenInNewTab: true),
                    ]),
            ])
            ->action(function (PreEnrollment $record, array $data, Action $action, Component $livewire): void {
                $mode = $data['discount_mode'] ?? 'none';
                $data['apply_plan_discount'] = $mode === 'plan';
                $data['use_custom_discount'] = $mode === 'custom';

                try {
                    $enrollment = app(PreEnrollmentConverter::class)->convert($record, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Não foi possível converter')
                        ->body(collect($exception->errors())->flatten()->implode(' '))
                        ->persistent()
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Matrícula criada')
                    ->body("{$record->student_name} está com a matrícula reservada em {$record->academicYear->description}.")
                    ->send();

                $livewire->redirect(EnrollmentResource::getUrl('view', ['record' => $enrollment]));
            });
    }

    /** Turmas do ano da ficha; turmas sem ano (legado) só quando a ficha é do ano letivo padrão. */
    public static function classroomsFor(PreEnrollment $record): Builder
    {
        return Classroom::query()->where(fn (Builder $query) => $query
            ->where('classrooms.academic_year_id', $record->academic_year_id)
            ->when($record->academicYear->is_default, fn (Builder $query) => $query->orWhereNull('classrooms.academic_year_id')));
    }

    /** Planos vinculados à turma e do mesmo ano da ficha (planos sem ano só no ano letivo padrão). */
    public static function planOptions(PreEnrollment $record, mixed $classroomId)
    {
        if (blank($classroomId)) {
            return collect();
        }

        return ClassroomPlan::query()
            ->where('classroom_id', $classroomId)
            ->whereHas('plan', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('plans.academic_year_id', $record->academic_year_id)
                ->when($record->academicYear->is_default, fn (Builder $query) => $query->orWhereNull('plans.academic_year_id'))))
            ->with('plan')->get()
            ->pluck('plan.name', 'plan.id');
    }

    /** Mesma regra do conversor: o aluno do cadastro tem matrícula em ano anterior ao da ficha. */
    public static function isReturning(PreEnrollment $record): bool
    {
        return PreEnrollmentInfolist::previousEnrollment($record) !== null;
    }

    private static function plan(mixed $planId): ?Plan
    {
        return filled($planId) ? Plan::query()->find($planId) : null;
    }

    private static function planAmount(mixed $planId): ?float
    {
        return filled($planId) ? (float) Plan::query()->whereKey($planId)->value('base_amount') : null;
    }

    /** Prévia da mensalidade: valor com desconto do plano ou com o desconto negociado digitado. */
    private static function negotiatedAmount(Get $get): ?float
    {
        if ($get('discount_mode') === 'plan') {
            $plan = self::plan($get('plan_id'));

            return $plan?->has_discount ? (float) $plan->final_value : null;
        }

        $base = self::planAmount($get('plan_id'));
        $value = (float) $get('discount_value');

        if ($base === null || $value <= 0 || ! in_array($get('discount_type'), ['percentage', 'fixed'], true)) {
            return null;
        }

        return max(0, $get('discount_type') === 'percentage' ? $base * (1 - $value / 100) : $base - $value);
    }

    private static function hasReenrollmentLink(PreEnrollment $record): bool
    {
        return $record->type === PreEnrollmentType::Reenrollment && $record->status->isOpen() && Route::has('pre-enrollment.reenrollment');
    }

    /** @return array{0: ?AcademicYear, 1: ?AcademicYear} */
    private static function campaignYears(Get $get): array
    {
        return [
            filled($get('source_year_id')) ? AcademicYear::query()->find($get('source_year_id')) : null,
            filled($get('target_year_id')) ? AcademicYear::query()->find($get('target_year_id')) : null,
        ];
    }

    private static function campaignYearsAreValid(Get $get): bool
    {
        [$source, $target] = self::campaignYears($get);

        return $source && $target && $target->year > $source->year;
    }

    private static function campaignSummary(Get $get): string
    {
        [$source, $target] = self::campaignYears($get);
        $count = app(ReenrollmentCampaign::class)->pending($source, $target)->distinct()->count('student_id');

        return $count
            ? "{$count} aluno(s) de {$source->description} receberão ficha de rematrícula para {$target->description}."
            : "Nenhum aluno de {$source->description} está sem matrícula ou ficha em {$target->description}.";
    }

    /** Alunos que ficam de fora por falta de dados no cadastro (até 10 nomes). */
    private static function incompleteSummary(Get $get): ?string
    {
        if (! self::campaignYearsAreValid($get)) {
            return null;
        }

        [$source, $target] = self::campaignYears($get);
        $enrollments = app(ReenrollmentCampaign::class)->incomplete($source, $target)->get()->unique('student_id');

        if ($enrollments->isEmpty()) {
            return null;
        }

        $names = $enrollments->take(10)->map(fn (Enrollment $enrollment): string => $enrollment->student->name.' (falta '.collect([
            $enrollment->student->date_of_birth ? null : 'data de nascimento',
            filled($enrollment->student->customer?->document) ? null : 'CPF do responsável',
        ])->filter()->implode(' e ').')')->implode('; ');

        $more = $enrollments->count() > 10 ? ' e mais '.($enrollments->count() - 10) : '';

        return "Ficam de fora até o cadastro ser completado: {$names}{$more}. Complete os cadastros e gere de novo; as fichas já criadas não se repetem.";
    }

    public static function reenrollmentMessage(PreEnrollment $record): string
    {
        $school = Company::current(auth()->user())?->name ?? 'escola';
        $firstName = Str::of($record->guardian_name)->before(' ')->title();

        return "Olá, {$firstName}! Aqui é da secretaria da {$school}. "
            ."A rematrícula de {$record->student_name} para {$record->academicYear->description} está aberta. "
            ."Confira os dados e confirme por este link: {$record->reenrollmentUrl()}";
    }

    public static function defaultMessage(PreEnrollment $record): string
    {
        $school = Company::current(auth()->user())?->name ?? 'escola';
        $firstName = Str::of($record->guardian_name)->before(' ')->title();

        return "Olá, {$firstName}! Aqui é da secretaria da {$school}. "
            ."Recebemos a pré-matrícula de {$record->student_name} para {$record->academicYear->description} (protocolo {$record->code}). "
            .'Podemos conversar sobre a vaga e os próximos passos?';
    }
}
