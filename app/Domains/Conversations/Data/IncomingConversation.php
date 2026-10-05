<?php

namespace App\Domains\Conversations\Data;

/**
 * As mensagens de um cliente lidas de uma origem.
 */
final readonly class IncomingConversation
{
    /**
     * @param  string  $contactKey  "wa:<telefone>" ou "file:<nome>"
     * @param  list<IncomingMessage>  $messages
     */
    public function __construct(
        public string $contactKey,
        public ?string $contactName,
        public ?string $contactPhone,
        public array $messages,
    ) {}
}
