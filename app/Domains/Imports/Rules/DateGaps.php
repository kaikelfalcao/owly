<?php

namespace App\Domains\Imports\Rules;

use Carbon\CarbonImmutable;

/**
 * Saúde da importação: dias seguidos sem nenhuma mensagem entre o primeiro e
 * o último dia do zip. Um buraco de dois dias ou mais quase sempre é
 * exportação incompleta, não loja fechada.
 */
final class DateGaps
{
    public const MIN_DAYS = 2;

    /**
     * @param  iterable<string>  $days  datas (Y-m-d) que têm mensagem, no fuso da empresa
     * @return list<array{from: string, to: string, days: int}>
     */
    public static function find(iterable $days): array
    {
        $set = [];

        foreach ($days as $day) {
            $set[$day] = true;
        }

        if (count($set) < 2) {
            return [];
        }

        ksort($set);
        $sorted = array_keys($set);
        $gaps = [];

        for ($i = 1; $i < count($sorted); $i++) {
            $previous = CarbonImmutable::parse($sorted[$i - 1]);
            $current = CarbonImmutable::parse($sorted[$i]);
            $missing = (int) $previous->diffInDays($current) - 1;

            if ($missing >= self::MIN_DAYS) {
                $gaps[] = [
                    'from' => $previous->addDay()->toDateString(),
                    'to' => $current->subDay()->toDateString(),
                    'days' => $missing,
                ];
            }
        }

        return $gaps;
    }
}
