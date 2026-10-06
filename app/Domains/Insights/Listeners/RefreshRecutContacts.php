<?php

namespace App\Domains\Insights\Listeners;

use App\Domains\Conversations\Events\ConversationsRecut;
use App\Domains\Insights\Services\RefreshOpportunities;

/**
 * Atendimentos refeitos (pelo owly:recut ou por um arquivo com mensagens
 * antigas): as oportunidades desses clientes acompanham na hora. É um
 * cliente por vez e não chama nada de fora.
 */
class RefreshRecutContacts
{
    public function __construct(private readonly RefreshOpportunities $opportunities) {}

    public function handle(ConversationsRecut $event): void
    {
        $this->opportunities->refresh($event->organizationId, $event->contactIds);
    }
}
