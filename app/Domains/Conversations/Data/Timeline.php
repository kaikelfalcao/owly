<?php

namespace App\Domains\Conversations\Data;

/**
 * Um atendimento inteiro, em ordem, para as regras de leitura.
 */
final readonly class Timeline
{
    /**
     * @param  list<MessageFact>  $messages
     * @param  string  $status  open | closed
     * @param  string  $openedBy  contact | company
     */
    public function __construct(
        public int $conversationId,
        public array $messages,
        public int $contactId = 0,
        public string $status = 'closed',
        public string $openedBy = 'contact',
    ) {}
}
