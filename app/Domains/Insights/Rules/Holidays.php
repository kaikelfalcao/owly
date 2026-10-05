<?php

namespace App\Domains\Insights\Rules;

use Carbon\CarbonImmutable;

/**
 * Feriados nacionais do Brasil, calculados ano a ano (a Páscoa muda). Carnaval
 * e Corpus Christi são ponto facultativo: ficam de fora, e a empresa que fecha
 * nesses dias cadastra como feriado próprio.
 */
final class Holidays
{
    private const FIXED = [
        '01-01' => 'Confraternização Universal',
        '04-21' => 'Tiradentes',
        '05-01' => 'Dia do Trabalho',
        '09-07' => 'Independência',
        '10-12' => 'Nossa Senhora Aparecida',
        '11-02' => 'Finados',
        '11-15' => 'Proclamação da República',
        '11-20' => 'Consciência Negra',
        '12-25' => 'Natal',
    ];

    /**
     * @return array<string, string> 'Y-m-d' => nome
     */
    public static function national(int $year): array
    {
        $days = [];

        foreach (self::FIXED as $day => $name) {
            $days["{$year}-{$day}"] = $name;
        }

        $days[self::easter($year)->subDays(2)->toDateString()] = 'Sexta-feira Santa';
        ksort($days);

        return $days;
    }

    /** Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher). */
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
