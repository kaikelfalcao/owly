<?php

namespace App\Domains\Conversations\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Atendimentos que já existiam foram refeitos: perderam mensagens para
 * outro ou foram apagados ao se juntar a outro. Sai só depois do commit.
 * Quem guarda id de atendimento (as perguntas à IA) reaponta por aqui.
 */
final class ConversationsRecut implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $contactIds
     * @param  list<int>  $conversationIds  atendimentos refeitos, inclusive os apagados
     * @param  array<int, int>  $replaced  atendimento apagado => atendimento que ficou com a mensagem que o abria
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly array $contactIds,
        public readonly array $conversationIds,
        public readonly array $replaced,
    ) {}
}
