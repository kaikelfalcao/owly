<?php

namespace App\Domains\Conversations\Contracts;

use App\Domains\Conversations\Data\Transcript;

/**
 * A conversa em texto corrido, para quem precisa ler (a IA, um relatório).
 * Responde 404 (ModelNotFoundException) para conversa de outra empresa.
 */
interface ConversationTranscript
{
    /**
     * @param  string  $timezone  horas das linhas neste fuso (o da empresa)
     */
    public function for(int $organizationId, int $conversationId, string $timezone): Transcript;
}
