<?php

namespace App\Documents;

use App\Models\IssuedDocument;
use Illuminate\Support\HtmlString;

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

    private static function strong(string $text): string
    {
        return '<strong>'.e($text).'</strong>';
    }
}
