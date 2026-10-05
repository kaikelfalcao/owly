<?php

namespace App\Domains\Conversations\Data;

use Carbon\CarbonImmutable;

/**
 * Uma mensagem como as regras de leitura enxergam: sem anexo, sem citação.
 */
final readonly class MessageFact
{
    /**
     * @param  string  $author  contact | seller | bot | system
     */
    public function __construct(
        public int $id,
        public CarbonImmutable $at,
        public string $direction,
        public string $author,
        public ?string $seller = null,
        public ?string $body = null,
        public ?string $mediaType = null,
        public ?string $event = null,
    ) {}
}
