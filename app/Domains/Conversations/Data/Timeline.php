<?php

namespace App\Domains\Conversations\Data;

/**
 * Uma conversa inteira, em ordem, para as regras de leitura.
 */
final readonly class Timeline
{
    /**
     * @param  list<MessageFact>  $messages
     */
    public function __construct(
        public int $conversationId,
        public array $messages,
    ) {}
}
