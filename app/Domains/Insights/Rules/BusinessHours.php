<?php

namespace App\Domains\Insights\Rules;

use Carbon\CarbonImmutable;

/**
 * O horário de atendimento da empresa, no fuso dela, com feriados. Tempo de
 * resposta conta só o tempo com a loja aberta: cliente que escreve às 19h da
 * sexta e é atendido às 8h05 da segunda esperou 5 minutos de expediente. Sem
 * nenhum dia aberto, conta tudo.
 */
final class BusinessHours
{
    private const DAYS = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    /**
     * @param  array<string, array{0: string, 1: string}|null>  $hours  'mon' => ['08:00', '18:00']
     * @param  list<string>  $holidays  datas 'Y-m-d' da empresa (além das nacionais)
     */
    public function __construct(
        private readonly array $hours,
        private readonly string $timezone,
        private readonly array $holidays = [],
        private readonly bool $nationalHolidays = true,
    ) {}

    /** @var array<int, array<string, string>> */
    private array $nationalCache = [];

    public function isHoliday(CarbonImmutable $localDay): bool
    {
        $date = $localDay->toDateString();

        if (in_array($date, $this->holidays, true)) {
            return true;
        }

        if (! $this->nationalHolidays) {
            return false;
        }

        $year = $localDay->year;
        $this->nationalCache[$year] ??= Holidays::national($year);

        return isset($this->nationalCache[$year][$date]);
    }

    public function isOpen(CarbonImmutable $at): bool
    {
        if (! $this->hasAnyDay()) {
            return true;
        }

        $window = $this->window($at->setTimezone($this->timezone)->startOfDay());

        return $window !== null && $at >= $window[0] && $at < $window[1];
    }

    /**
     * Segundos de expediente entre dois instantes.
     */
    public function secondsBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($to <= $from) {
            return 0;
        }

        if (! $this->hasAnyDay()) {
            return $to->getTimestamp() - $from->getTimestamp();
        }

        $seconds = 0;
        $day = $from->setTimezone($this->timezone)->startOfDay();
        $last = $to->setTimezone($this->timezone)->startOfDay();

        while ($day <= $last) {
            $window = $this->window($day);

            if ($window !== null) {
                $start = max($window[0], $from);
                $end = min($window[1], $to);

                if ($end > $start) {
                    $seconds += $end->getTimestamp() - $start->getTimestamp();
                }
            }

            $day = $day->addDay();
        }

        return $seconds;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function window(CarbonImmutable $localDay): ?array
    {
        $range = $this->hours[self::DAYS[$localDay->dayOfWeekIso]] ?? null;

        if ($range === null || $this->isHoliday($localDay)) {
            return null;
        }

        [$open, $close] = $range;

        return [
            $localDay->setTimeFromTimeString($open),
            $localDay->setTimeFromTimeString($close),
        ];
    }

    private function hasAnyDay(): bool
    {
        return array_filter($this->hours) !== [];
    }
}
