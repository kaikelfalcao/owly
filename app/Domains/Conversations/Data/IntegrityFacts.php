<?php

namespace App\Domains\Conversations\Data;

/**
 * A foto dos dados de uma empresa que uma migração não pode mudar. Só
 * números e um hash: nada de texto, nome ou telefone.
 */
final readonly class IntegrityFacts
{
    public function __construct(
        public int $messages,
        public int $contacts,
        public int $conversations,
        public int $emptyConversations,
        /** sha256 da lista ordenada de "cliente:id externo" das mensagens. */
        public string $messagesHash,
    ) {}

    /**
     * @return array{messages: int, contacts: int, conversations: int, empty_conversations: int, messages_hash: string}
     */
    public function toArray(): array
    {
        return [
            'messages' => $this->messages,
            'contacts' => $this->contacts,
            'conversations' => $this->conversations,
            'empty_conversations' => $this->emptyConversations,
            'messages_hash' => $this->messagesHash,
        ];
    }
}
