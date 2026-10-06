<?php

namespace App\Domains\Conversations\Data;

/**
 * O histórico inteiro de um cliente, atravessando os atendimentos: cada
 * mensagem diz em qual está. Para regras que olham além de um atendimento
 * (oportunidade, orçamento parado).
 */
final readonly class History
{
    /**
     * @param  list<MessageFact>  $messages
     */
    public function __construct(
        public int $contactId,
        public array $messages,
    ) {}
}
