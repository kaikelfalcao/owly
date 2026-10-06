<?php

namespace App\Domains\Conversations\Rules;

use Carbon\CarbonImmutable;

/**
 * Um atendimento calculado pelo corte, antes de virar linha no banco.
 */
final class CutSegment
{
    /** @var list<int|string> chaves das mensagens, em ordem */
    public array $keys = [];

    /** @var array<int, int> vendedora => mensagens */
    private array $sellerMessages = [];

    /** @var array<int, int> vendedora => posição da primeira mensagem */
    private array $sellerFirst = [];

    public CarbonImmutable $lastAt;

    public CarbonImmutable $lastPersonAt;

    /**
     * @param  string  $openedBy  contact | company
     */
    public function __construct(
        public readonly string $openedBy,
        public readonly CarbonImmutable $firstAt,
    ) {
        $this->lastAt = $firstAt;
        $this->lastPersonAt = $firstAt;
    }

    public function add(CutMessage $message): void
    {
        $this->keys[] = $message->key;
        $this->lastAt = $message->at;

        if ($message->fromPerson()) {
            $this->lastPersonAt = $message->at;
        }

        if ($message->author === 'seller' && $message->sellerId !== null) {
            $this->sellerFirst[$message->sellerId] ??= count($this->keys);
            $this->sellerMessages[$message->sellerId] = ($this->sellerMessages[$message->sellerId] ?? 0) + 1;
        }
    }

    /** A vendedora com mais mensagens; no empate, quem respondeu primeiro. */
    public function sellerId(): ?int
    {
        $best = null;

        foreach ($this->sellerMessages as $id => $count) {
            if ($best === null
                || $count > $this->sellerMessages[$best]
                || ($count === $this->sellerMessages[$best] && $this->sellerFirst[$id] < $this->sellerFirst[$best])) {
                $best = $id;
            }
        }

        return $best;
    }

    public function opening(): int|string
    {
        return $this->keys[0];
    }
}
