<?php

namespace App\Domains\Conversations\Contracts;

use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\StoreResult;
use Carbon\CarbonImmutable;

/**
 * A porta de entrada de Conversas: quem traz mensagens (a importação hoje, a
 * API do WhatsApp depois) grava por aqui, nunca direto nas tabelas.
 */
interface ConversationStore
{
    /**
     * Grava o cliente e as mensagens novas e refaz os atendimentos dele, numa
     * transação só. Mensagem que já existe (mesmo id externo do mesmo
     * cliente) é ignorada e fica com a importação que a trouxe primeiro.
     *
     * @param  string  $source  zip | api
     * @param  int|null  $importId  a importação que trouxe as mensagens; null quando não veio de uma
     */
    public function store(int $organizationId, string $source, IncomingConversation $incoming, ?int $importId = null): StoreResult;

    /**
     * Fecha os atendimentos abertos que passaram um dia útil inteiro sem
     * mensagem de pessoa até a referência. Devolve quantos fecharam.
     */
    public function closeIdle(int $organizationId, CarbonImmutable $reference): int;
}
