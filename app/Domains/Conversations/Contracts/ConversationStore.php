<?php

namespace App\Domains\Conversations\Contracts;

use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\StoreResult;

/**
 * A porta de entrada de Conversas: quem traz mensagens (a importação hoje, a
 * API do WhatsApp depois) grava por aqui, nunca direto nas tabelas.
 */
interface ConversationStore
{
    /**
     * Grava o cliente, a conversa e as mensagens novas. Mensagem que já
     * existe (mesmo id externo na conversa) é ignorada.
     *
     * @param  string  $source  zip | api
     */
    public function store(int $organizationId, string $source, IncomingConversation $incoming): StoreResult;
}
