<?php

namespace App\Documents;

use App\Models\IssuedDocument;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

/** Trechos de texto comuns às declarações, montados a partir do conteúdo congelado. */
class DocumentText
{
    /** Escolhe a palavra pelo gênero gravado do aluno: M, F ou não informado. */
    public static function byGender(?string $gender, string $male, string $female, string $neutral): string
    {
        return match ($gender) {
            'M' => $male,
            'F' => $female,
            default => $neutral,
        };
    }

    /**
     * "<strong>Ana Clara</strong>, filha de <strong>Maria</strong> e de <strong>João</strong>, nascida em 10/03/2015, CPF ..."
     * Montado em PHP para não deixar espaço antes da vírgula que vem em seguida no texto.
     */
    public static function studentIntro(array $student, bool $public): HtmlString
    {
        $gender = $student['gender'] ?? null;
        $cpf = $public ? IssuedDocument::maskDocument($student['cpf'] ?? null) : ($student['cpf'] ?? null);
        $parents = collect($student['parents'] ?? [])->filter()->map(fn (string $name): string => self::strong($name));

        $parts = [self::strong($student['name'] ?? '')];

        if ($parents->isNotEmpty()) {
            $parts[] = self::byGender($gender, 'filho', 'filha', 'filho(a)').' de '.$parents->implode(' e de ');
        }

        if (filled($student['birth_date'] ?? null)) {
            $parts[] = self::byGender($gender, 'nascido', 'nascida', 'nascido(a)').' em '.e($student['birth_date']);
        }

        if (filled($cpf)) {
            $parts[] = 'CPF '.e($cpf);
        }

        return new HtmlString(implode(', ', $parts));
    }

    /** "<strong>A</strong>, <strong>B</strong> e de <strong>C</strong>" */
    public static function names(array $names, string $lastJoin = ' e de '): HtmlString
    {
        $names = collect($names)->filter()->map(fn (string $name): string => self::strong($name))->values();

        return new HtmlString($names->count() > 1
            ? $names->slice(0, -1)->implode(', ').$lastJoin.$names->last()
            : (string) $names->first());
    }

    /**
     * Valor em reais por extenso: "mil duzentos e trinta e quatro reais e cinquenta centavos",
     * "um real", "um milhão de reais", "cinquenta centavos".
     */
    public static function moneyInWords(float|string $amount): string
    {
        $cents = (int) round(((float) $amount) * 100);
        $reais = intdiv($cents, 100);
        $cents %= 100;
        $parts = [];

        if ($reais > 0) {
            $unit = $reais === 1 ? 'real' : 'reais';
            // "um milhão de reais", mas "um milhão e duzentos mil reais"
            $parts[] = Number::spell($reais, locale: 'pt_BR').($reais % 1_000_000 === 0 ? ' de ' : ' ').$unit;
        }

        if ($cents > 0) {
            $parts[] = Number::spell($cents, locale: 'pt_BR').($cents === 1 ? ' centavo' : ' centavos');
        }

        return $parts === [] ? 'zero real' : implode(' e ', $parts);
    }

    private static function strong(string $text): string
    {
        return '<strong>'.e($text).'</strong>';
    }
}
