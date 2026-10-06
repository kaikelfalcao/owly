<?php

namespace App\Domains\Conversations\Rules;

use Carbon\CarbonImmutable;

/**
 * Uma mensagem como o corte enxerga. A chave é o id da mensagem gravada ou
 * uma marca da mensagem nova que ainda vai ser gravada.
 */
final readonly class CutMessage
{
    public function __construct(
        public int|string $key,
        public CarbonImmutable $at,
        public string $author,
        public string $direction,
        public ?string $event = null,
        public ?int $sellerId = null,
    ) {}

    /** Do cliente: o que ele escreveu, ou uma ligação dele que ninguém atendeu. */
    public function fromClient(): bool
    {
        return $this->author === 'contact'
            || ($this->author === 'system' && $this->event === 'missed_call' && $this->direction === 'in');
    }

    /** De pessoa: cliente ou vendedora. Robô e avisos entram, mas não mexem no relógio. */
    public function fromPerson(): bool
    {
        return $this->fromClient() || $this->author === 'seller';
    }
}
