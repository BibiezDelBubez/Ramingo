<?php
declare(strict_types=1);

namespace Ramingo\Services\Search;

/** Normalizzazione del testo condivisa da indicizzazione e ricerca (DRY). */
final class Text
{
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ];

    /** minuscolo, senza accenti, solo lettere/cifre/spazi. */
    public static function normalize(string $s): string
    {
        $s = strtr(mb_strtolower($s, 'UTF-8'), self::ACCENTS);
        $s = (string) preg_replace('~[^a-z0-9]+~', ' ', $s);
        return trim($s);
    }

    /** @return string[] */
    public static function tokens(string $s): array
    {
        $n = self::normalize($s);
        return $n === '' ? [] : explode(' ', $n);
    }

    /**
     * Radice "povera" per l'italiano: toglie la vocale finale alle parole lunghe.
     * vendite -> vendit (trova vendita, venditore), regione -> region (trova region, regioni).
     */
    public static function stem(string $word): string
    {
        $w = self::normalize($word);
        if (strlen($w) >= 5 && preg_match('~[aeiou]$~', $w)) {
            $w = substr($w, 0, -1);
        }
        return $w;
    }
}
