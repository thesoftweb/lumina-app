<?php

namespace App\Enums;

enum DegreeOfKinship: string
{
    case Mother        = 'mãe';
    case Father        = 'pai';
    case Stepmother    = 'madrasta';
    case Stepfather    = 'padrasto';

    case Brother       = 'irmão';
    case Sister        = 'irmã';

    case Grandmother   = 'avó';
    case Grandfather   = 'avô';

    case Grandson      = 'neto';
    case Granddaughter = 'neta';

    case Aunt          = 'tia';
    case Uncle         = 'tio';

    case Niece         = 'sobrinha';
    case Nephew        = 'sobrinho';

    case CousinMale    = 'primo';
    case CousinFemale  = 'prima';

    case Guardian      = 'responsável';
    case Other         = 'outro';

    /**
     * Útil para usar como options() no Filament
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = ucfirst($case->value);
        }

        return $options;
    }
}
