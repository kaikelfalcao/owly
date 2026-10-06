<?php

namespace App\Domains\Conversations\Data;

/**
 * O mínimo para listar um atendimento em outra tela (o painel).
 */
final readonly class ConversationSummary
{
    /**
     * @param  string  $status  open | closed
     */
    public function __construct(
        public int $id,
        public string $contact,
        public ?string $phone,
        public ?string $lastMessageAt,
        public int $contactId = 0,
        public string $status = 'closed',
    ) {}
}
