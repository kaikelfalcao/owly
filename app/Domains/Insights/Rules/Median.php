<?php

namespace App\Domains\Insights\Rules;

final class Median
{
    /**
     * @param  list<int|float>  $values
     */
    public static function of(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? (float) $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
