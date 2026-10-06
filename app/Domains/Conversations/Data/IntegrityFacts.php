<?php

namespace App\Domains\Conversations\Data;

/**
 * A foto dos dados de uma empresa que uma migração não pode mudar. Só
 * números, ids e um hash: nada de texto, nome ou telefone.
 */
final readonly class IntegrityFacts
{
    /**
     * @param  array<int, int>  $firstConversations  cliente => id do atendimento mais antigo
     */
    public function __construct(
        public int $messages,
        public int $contacts,
        public int $conversations,
        public int $emptyConversations,
        /** sha256 da lista ordenada de "cliente:id externo" das mensagens. */
        public string $messagesHash,
        public array $firstConversations = [],
        /** Atendimentos que começaram pela empresa; null antes de a coluna existir. */
        public ?int $openedByCompany = null,
    ) {}

    /**
     * @return array{messages: int, contacts: int, conversations: int, empty_conversations: int, opened_by_company: int|null, messages_hash: string, first_conversations: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'messages' => $this->messages,
            'contacts' => $this->contacts,
            'conversations' => $this->conversations,
            'empty_conversations' => $this->emptyConversations,
            'opened_by_company' => $this->openedByCompany,
            'messages_hash' => $this->messagesHash,
            'first_conversations' => $this->firstConversations,
        ];
    }
}
