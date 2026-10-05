<?php

namespace App\Domains\Accounts\Data;

/**
 * Quando a empresa atende: fuso, horário por dia e feriados. É o que os
 * outros domínios precisam saber da empresa para medir o atendimento.
 */
final readonly class WorkingCalendar
{
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
    ) {}
}
