<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use App\Models\AcademicYear;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Route;

class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ano Escolar')
                    ->description('Configure o ano escolar, definindo datas de início e término, além de períodos importantes como matrículas e férias.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('description')->label('Descrição'),
                        TextInput::make('year')->label('Ano'),
                        DatePicker::make('start_at')->label('Início do Período'),
                        DatePicker::make('end_at')->label('Final do Período'),
                        // Antes não havia o campo e todo ano novo nascia padrão (padrão do banco = true)
                        Toggle::make('is_default')
                            ->label('Ano letivo atual (padrão)')
                            ->helperText('Só um ano é o padrão: marcar este desmarca os outros. Mantenha 2026 como padrão até o início das aulas de 2027.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Pré-matrícula')
                    ->description('Ficha pública para famílias interessadas neste ano letivo. As vagas e os valores divulgados ficam em "Vagas e valores", abaixo.')
                    ->icon('heroicon-o-user-plus')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Toggle::make('admissions_open')
                            ->label('Receber pré-matrículas')
                            ->helperText('Com a chave desligada, o link público informa que as inscrições estão fechadas.')
                            ->live()
                            ->columnSpanFull(),
                        DatePicker::make('admissions_starts_at')->label('Abre em')
                            ->helperText('Em branco: aberta a partir de agora.'),
                        DatePicker::make('admissions_ends_at')->label('Fecha em')
                            ->afterOrEqual('admissions_starts_at')
                            ->helperText('Em branco: sem data para encerrar.'),
                        Textarea::make('admissions_notes')->label('Texto exibido na ficha')
                            ->placeholder('Ex.: documentos necessários, data da visita, como funciona a reserva da vaga.')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('public_link')->label('Link público da ficha')
                            ->dehydrated(false)
                            ->readOnly()
                            ->copyable(copyMessage: 'Link copiado')
                            ->formatStateUsing(fn (?AcademicYear $record): ?string => self::publicLink($record))
                            ->helperText('Divulgue este link no site, no Instagram e no WhatsApp da escola.')
                            ->visible(fn (?AcademicYear $record): bool => filled(self::publicLink($record)))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Endereço da ficha pública do ano (a rota é registrada pelo módulo público de pré-matrícula). */
    public static function publicLink(?AcademicYear $record): ?string
    {
        if (! $record?->year || ! Route::has('pre-enrollment.create')) {
            return null;
        }

        return route('pre-enrollment.create', $record->year);
    }
}
