<?php

namespace App\Filament\Resources\PreEnrollments\Schemas;

use App\Enums\PreEnrollmentType;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\PreEnrollment;
use App\Models\Student;
use App\Rules\Cpf;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Str;

class PreEnrollmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 2])
            ->components([
                Callout::make('Aluno já matriculado neste ano')->warning()->columnSpanFull()
                    ->description(fn (PreEnrollment $record): ?string => ($enrollment = self::blockingEnrollment($record)) ? sprintf(
                        '%s já tem a matrícula "%s" em %s (turma %s). A conversão desta ficha será recusada; confira se é uma ficha repetida ou cancele a matrícula existente.',
                        $enrollment->student?->name, $enrollment->status->getLabel(),
                        $enrollment->academicYear?->description, $enrollment->classroom?->name ?? 'não informada',
                    ) : null)
                    ->visible(fn (PreEnrollment $record): bool => (bool) self::blockingEnrollment($record)),

                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 4, 'xl' => 8])->schema([
                            TextEntry::make('status')->label('Situação')->badge(),
                            TextEntry::make('type')->label('Tipo')->badge(),
                            TextEntry::make('code')->label('Protocolo')->copyable()->weight(FontWeight::SemiBold),
                            TextEntry::make('academicYear.description')->label('Ano letivo'),
                            TextEntry::make('offer.name')->label('Série / vaga')->placeholder('Não escolhida'),
                            TextEntry::make('created_at')->label('Recebida em')->dateTime('d/m/Y H:i'),
                            TextEntry::make('last_contact_at')->label('Último contato')->since()->placeholder('Sem contato'),
                            TextEntry::make('responded_at')->label('Confirmou pelo link')->dateTime('d/m/Y H:i')
                                ->placeholder('Ainda não')
                                ->visible(fn (PreEnrollment $record): bool => $record->type === PreEnrollmentType::Reenrollment),
                        ]),
                    ]),

                Section::make('Aluno')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('student_name')->label('Nome')->weight(FontWeight::SemiBold)->columnSpanFull(),
                        TextEntry::make('student_birth_date')->label('Data de nascimento')
                            ->formatStateUsing(fn ($state, PreEnrollment $record): string => $state->format('d/m/Y')
                                .(($age = $record->studentAgeAtYearStart()) !== null ? " ({$age} anos no início do ano letivo)" : '')),
                        TextEntry::make('student_gender')->label('Gênero')
                            ->formatStateUsing(fn (?string $state): ?string => ['M' => 'Masculino', 'F' => 'Feminino', 'O' => 'Outro'][$state] ?? $state)
                            ->placeholder('-'),
                        TextEntry::make('student_previous_school')->label('Escola atual / anterior')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('student_special_needs_notes')->label('Necessidade específica')->columnSpanFull()
                            ->state(fn (PreEnrollment $record): ?string => $record->student_has_special_needs ? ($record->student_special_needs_notes ?: 'Sim, sem detalhes') : null)
                            ->placeholder('Não informada'),
                    ]),

                Section::make('Responsável')
                    ->icon('heroicon-o-user-group')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('guardian_name')->label('Nome')->weight(FontWeight::SemiBold)->columnSpanFull(),
                        TextEntry::make('guardian_document')->label('CPF')->formatStateUsing(fn (?string $state): ?string => $state ? Cpf::format($state) : null),
                        TextEntry::make('guardian_kinship')->label('Parentesco')->formatStateUsing(fn (?string $state): ?string => $state ? Str::ucfirst($state) : null)->placeholder('-'),
                        TextEntry::make('guardian_phone')->label('Telefone / WhatsApp')
                            ->formatStateUsing(fn (?string $state): ?string => self::formatPhone($state))
                            ->url(fn (PreEnrollment $record): ?string => $record->whatsappUrl(), shouldOpenInNewTab: true)
                            ->color('success'),
                        TextEntry::make('guardian_email')->label('E-mail')->placeholder('-'),
                        TextEntry::make('full_address')->label('Endereço')->columnSpanFull()
                            ->state(fn (PreEnrollment $record): ?string => collect([
                                collect([$record->address, $record->address_number])->filter()->implode(', '),
                                $record->neighborhood,
                                $record->city_name,
                                $record->postal_code ? "CEP {$record->postal_code}" : null,
                            ])->filter()->implode(' - ') ?: null)
                            ->placeholder('Não informado'),
                    ]),

                Section::make('Interesse e valores')
                    ->icon('heroicon-o-currency-dollar')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('offer.name')->label('Série / vaga')->placeholder('Não escolhida'),
                        TextEntry::make('offer.shift')->label('Turno')->placeholder('-'),
                        TextEntry::make('monthly')->label('Mensalidade divulgada')
                            ->state(fn (PreEnrollment $record): ?float => $record->offer?->monthlyAmount())
                            ->money('BRL', locale: 'pt_BR')
                            ->helperText('Valor fixo do plano.')
                            ->placeholder('-'),
                        TextEntry::make('offer.enrollment_fee')->label('Taxa de matrícula')->money('BRL', locale: 'pt_BR')->placeholder('-'),
                        TextEntry::make('message')->label('Mensagem da família')->placeholder('Sem mensagem')->columnSpanFull(),
                    ]),

                Section::make('Origem')
                    ->icon('heroicon-o-megaphone')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('source')->label('Como conheceu a escola')->placeholder('Não informado'),
                        TextEntry::make('utm_campaign')->label('Campanha')->placeholder('-'),
                        TextEntry::make('utm_source')->label('Origem (utm_source)')->placeholder('-'),
                        TextEntry::make('utm_medium')->label('Mídia (utm_medium)')->placeholder('-'),
                        TextEntry::make('consent_at')->label('Aceite dos termos (LGPD)')->dateTime('d/m/Y H:i')->placeholder('Registrada pela secretaria'),
                        TextEntry::make('ip_address')->label('IP de envio')->placeholder('-'),
                    ]),

                Section::make('Vínculos')
                    ->icon('heroicon-o-link')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('matched_customer')->label('Responsável no cadastro')
                            ->state(fn (PreEnrollment $record): ?string => self::matchedCustomer($record)?->name)
                            ->url(fn (PreEnrollment $record): ?string => ($customer = self::matchedCustomer($record)) ? CustomerResource::getUrl('edit', ['record' => $customer]) : null)
                            ->color('primary')
                            ->placeholder('Novo: será cadastrado na conversão'),
                        TextEntry::make('matched_student')->label('Aluno no cadastro')
                            ->state(fn (PreEnrollment $record): ?string => self::matchedStudent($record)?->name)
                            ->url(fn (PreEnrollment $record): ?string => ($student = self::matchedStudent($record)) ? StudentResource::getUrl('view', ['record' => $student]) : null)
                            ->color('primary')
                            ->placeholder('Novo: será cadastrado na conversão'),
                        TextEntry::make('previous_enrollment')->label('Matrícula anterior')
                            ->state(fn (PreEnrollment $record): ?string => ($previous = self::previousEnrollment($record))
                                ? collect(['Matrícula nº '.$previous->number(), $previous->academicYear?->description ?? 'sem ano letivo', $previous->classroom?->name])->filter()->implode(' · ')
                                : null)
                            ->url(fn (PreEnrollment $record): ?string => ($previous = self::previousEnrollment($record)) ? EnrollmentResource::getUrl('view', ['record' => $previous]) : null)
                            ->color('primary')
                            ->placeholder('Nenhuma: aluno novato')
                            ->columnSpanFull(),
                        TextEntry::make('enrollment_id')->label('Matrícula criada')
                            ->state(fn (PreEnrollment $record): ?string => $record->enrollment ? 'Matrícula nº '.$record->enrollment->number() : null)
                            ->url(fn (PreEnrollment $record): ?string => $record->enrollment ? EnrollmentResource::getUrl('view', ['record' => $record->enrollment]) : null)
                            ->color('primary')
                            ->placeholder('Ainda não convertida'),
                        TextEntry::make('converted_at')->label('Convertida em')
                            ->formatStateUsing(fn ($state, PreEnrollment $record): string => $state->format('d/m/Y H:i').($record->converter ? " por {$record->converter->name}" : ''))
                            ->placeholder('-'),
                    ]),

                Section::make('Acompanhamento')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextEntry::make('assignee.name')->label('Responsável na secretaria')->placeholder('Ninguém'),
                        TextEntry::make('lost_reason')->label('Motivo da desistência')->placeholder('-'),
                        IconEntry::make('student_has_special_needs')->label('Necessidade específica')->boolean(),
                        TextEntry::make('internal_notes')->label('Anotações internas')->placeholder('-')->columnSpanFull(),
                    ]),
            ]);
    }

    /** Cadastro do responsável: o vinculado na ficha ou o encontrado pelo CPF. */
    public static function matchedCustomer(PreEnrollment $record): ?Customer
    {
        return $record->customer ?? Customer::query()
            ->whereIn('document', [$record->guardian_document, Cpf::format((string) $record->guardian_document)])
            ->first();
    }

    /** Cadastro do aluno: o vinculado na ficha ou o do mesmo responsável com mesmo nome e nascimento. */
    public static function matchedStudent(PreEnrollment $record): ?Student
    {
        if ($record->student) {
            return $record->student;
        }

        $customer = self::matchedCustomer($record);

        return $customer ? Student::query()
            ->where('customer_id', $customer->getKey())
            ->whereDate('date_of_birth', $record->student_birth_date)
            ->whereRaw('LOWER(name) = ?', [Str::lower($record->student_name)])
            ->first() : null;
    }

    /** Matrícula de um ano anterior: a gravada na ficha ou a encontrada pelo histórico do aluno. */
    public static function previousEnrollment(PreEnrollment $record): ?Enrollment
    {
        return $record->previousEnrollment ?? self::matchedStudent($record)?->previousEnrollmentBefore($record->academicYear);
    }

    /** Matrícula não cancelada do mesmo aluno no ano da ficha, que impede a conversão. */
    public static function blockingEnrollment(PreEnrollment $record): ?Enrollment
    {
        if ($record->isConverted() || ! ($student = self::matchedStudent($record))) {
            return null;
        }

        return Enrollment::query()->notCanceledInYear($student->getKey(), $record->academic_year_id)
            ->with(['student', 'classroom', 'academicYear'])->first();
    }

    public static function formatPhone(?string $phone): ?string
    {
        return match (strlen((string) $phone)) {
            11 => preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone),
            10 => preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone),
            default => $phone,
        };
    }
}
