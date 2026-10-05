<?php

namespace App\Domains\Conversations\Contracts;

use Closure;

/**
 * Espaço na tela da conversa para outros domínios (a IA, depois os insights)
 * sem que Conversas saiba deles: cada um registra uma chave e o que mostrar.
 */
interface ConversationPanels
{
    /**
     * @param  Closure(int $organizationId, int $conversationId): array<string, mixed>  $props
     */
    public function register(string $key, Closure $props): void;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function for(int $organizationId, int $conversationId): array;
}
