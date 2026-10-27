<?php

namespace App\Filament\Widgets;

use App\Enums\PreEnrollmentStatus;
use App\Enums\PreEnrollmentType;
use App\Filament\Resources\PreEnrollments\PreEnrollmentResource;
use App\Models\AcademicYear;
use App\Models\PreEnrollment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** Funil da pré-matrícula do ano que está recebendo fichas. Só aparece com as inscrições abertas. */
class PreEnrollmentsStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['secretary', 'admin']) && static::year() !== null;
    }

    public static function year(): ?AcademicYear
    {
        return AcademicYear::query()->acceptingPreEnrollments()->orderByDesc('year')->first();
    }

    protected function getHeading(): ?string
    {
        return 'Pré-matrículas '.static::year()?->year;
    }

    protected function getStats(): array
    {
        $year = static::year();
        $leads = PreEnrollment::query()->forYear($year);
        $total = (clone $leads)->count();
        $converted = (clone $leads)->where('status', PreEnrollmentStatus::Converted->value)->count();
        $url = PreEnrollmentResource::getUrl('index');

        $open = (clone $leads)->open();
        $byType = fn ($query): string => sprintf('%d novatos · %d rematrículas',
            (clone $query)->where('type', PreEnrollmentType::NewStudent->value)->count(),
            (clone $query)->where('type', PreEnrollmentType::Reenrollment->value)->count());

        return [
            Stat::make('Fichas em aberto', (clone $open)->count())
                ->description($byType($open).' · '.(clone $leads)->where('status', PreEnrollmentStatus::New->value)->count().' sem contato')
                ->color('info')->icon('heroicon-o-user-plus')->url($url),
            // Rematrículas geradas em lote não são fichas recebidas: aqui contam só as de novatos
            Stat::make('Novatos nos últimos 7 dias', (clone $leads)->where('type', PreEnrollmentType::NewStudent->value)->where('created_at', '>=', now()->subDays(7))->count())
                ->description("{$total} fichas no total")
                ->color('primary')->icon('heroicon-o-inbox-arrow-down'),
            Stat::make('Matriculadas', $converted)
                ->description($byType((clone $leads)->where('status', PreEnrollmentStatus::Converted->value)))
                ->color('success')->icon('heroicon-o-check-badge'),
            Stat::make('Conversão', $total > 0 ? Number::percentage($converted / $total * 100, maxPrecision: 1, locale: 'pt_BR') : '-')
                ->description('Fichas que viraram matrícula em '.$year->year)
                ->color('warning')->icon('heroicon-o-arrow-trending-up'),
        ];
    }
}
