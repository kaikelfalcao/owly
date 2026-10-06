<?php

namespace App\Domains\Accounts\Data;

use App\Domains\Accounts\Rules\BusinessHours;
use Carbon\CarbonImmutable;

/**
 * Quando a empresa atende: fuso, horário por dia e feriados. É a única
 * definição de dia útil e hora útil (docs/arquitetura.md): o corte dos
 * atendimentos, as esperas e o orçamento parado perguntam aqui.
 */
final readonly class WorkingCalendar
{
    private BusinessHours $rules;

    /**
     * @param  array<string, array{0: string, 1: string}|null>  $hours  'mon' => ['08:00', '18:00']
     * @param  list<string>  $holidays  datas 'Y-m-d' da empresa
     */
    public function __construct(
        public int $organizationId,
        public string $timezone,
        public array $hours,
        public array $holidays,
        public bool $nationalHolidays,
        public bool $configured,
    ) {
        $this->rules = new BusinessHours($hours, $timezone, $holidays, $nationalHolidays);
    }

    /** Se a loja está aberta neste instante. */
    public function isOpen(CarbonImmutable $at): bool
    {
        return $this->rules->isOpen($at);
    }

    /** Se a data deste instante, no fuso da empresa, tem expediente. */
    public function isWorkingDay(CarbonImmutable $at): bool
    {
        return $this->rules->isWorkingDay($at);
    }

    /** Segundos de expediente entre dois instantes. */
    public function secondsBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->rules->secondsBetween($from, $to);
    }

    /** Datas com expediente estritamente entre as datas de dois instantes. */
    public function workingDaysBetween(CarbonImmutable $from, CarbonImmutable $to, int $limit = PHP_INT_MAX): int
    {
        return $this->rules->workingDaysBetween($from, $to, $limit);
    }
}
