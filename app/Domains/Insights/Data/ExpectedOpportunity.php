<?php

namespace App\Domains\Insights\Data;

use Carbon\CarbonImmutable;

/**
 * O que a regra espera encontrar gravado para uma âncora.
 */
final readonly class ExpectedOpportunity
{
    /**
     * @param  string  $status  open | won
     */
    public function __construct(
        public int $anchorMessageId,
        public int $conversationId,
        public ?int $sellerId,
        public string $status,
        public CarbonImmutable $openedAt,
        public ?int $closingMessageId = null,
        public ?CarbonImmutable $closedAt = null,
    ) {}
}
