<?php

namespace App\Domains\Insights\Data;

use Carbon\CarbonImmutable;

/**
 * Uma vez em que o cliente falou e ficou esperando: começa na primeira
 * mensagem dele e termina na primeira resposta de uma pessoa da empresa
 * (resposta automática não conta).
 */
final readonly class Turn
{
    public function __construct(
        public int $conversationId,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $lastContactAt,
        public ?string $lastContactBody,
        public ?CarbonImmutable $answeredAt = null,
        public ?string $answeredBy = null,
    ) {}

    public function answered(): bool
    {
        return $this->answeredAt !== null;
    }
}
