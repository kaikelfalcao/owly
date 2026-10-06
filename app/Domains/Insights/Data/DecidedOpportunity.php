<?php

namespace App\Domains\Insights\Data;

use Carbon\CarbonImmutable;

/**
 * Uma oportunidade em que o dono já decidiu: a regra não mexe nela, mas
 * precisa saber dela para não abrir outra no mesmo lugar.
 */
final readonly class DecidedOpportunity
{
    /**
     * @param  string  $status  open | won | lost | discarded
     * @param  CarbonImmutable|null  $closedAt  até quando ela segura os orçamentos seguintes
     */
    public function __construct(
        public int $anchorMessageId,
        public string $status,
        public ?CarbonImmutable $closedAt = null,
    ) {}
}
