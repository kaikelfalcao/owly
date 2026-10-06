<?php

namespace App\Domains\Conversations\Services;

/**
 * O que o corte gravou para um cliente.
 */
final readonly class CutOutcome
{
    /**
     * @param  list<int>  $changed  atendimentos que existiam e foram refeitos (perderam mensagem ou sumiram)
     * @param  array<int, int>  $replaced  atendimento apagado => atendimento que ficou com a mensagem que o abria
     * @param  list<int>  $conversationIds  os atendimentos calculados, em ordem
     */
    public function __construct(
        public int $created,
        public array $changed,
        public array $replaced,
        public array $conversationIds,
        public int $movedMessages,
    ) {}
}
