<?php

namespace App\Domains\Conversations\Data;

/**
 * O mínimo para listar uma conversa em outra tela (o painel).
 */
final readonly class ConversationSummary
{
    public function __construct(
        public int $id,
        public string $contact,
        public ?string $phone,
        public ?string $lastMessageAt,
    ) {}
}
