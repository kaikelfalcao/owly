<?php

namespace App\Domains\Insights\Data;

use Carbon\CarbonImmutable;

/**
 * O período do painel. O "hoje" é a mensagem mais recente da empresa: quem
 * só tem zip olha para os dias do zip, não para a data do computador.
 */
final readonly class Period
{
    public const OPTIONS = ['7' => 7, '30' => 30, 'tudo' => null];

    public function __construct(
        public string $key,
        public ?CarbonImmutable $since,
        public CarbonImmutable $until,
    ) {}

    public static function make(?string $key, ?CarbonImmutable $latest, string $timezone): self
    {
        $key = array_key_exists((string) $key, self::OPTIONS) ? (string) $key : '30';
        $until = $latest ?? CarbonImmutable::now('UTC');
        $days = self::OPTIONS[$key];

        $since = $days === null
            ? null
            : $until->setTimezone($timezone)->startOfDay()->subDays($days - 1)->utc();

        return new self($key, $since, $until);
    }

    public function contains(CarbonImmutable $at): bool
    {
        return ($this->since === null || $at >= $this->since) && $at <= $this->until;
    }
}
