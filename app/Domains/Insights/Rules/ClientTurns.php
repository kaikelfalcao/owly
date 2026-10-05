<?php

namespace App\Domains\Insights\Rules;

use App\Domains\Conversations\Data\Timeline;
use App\Domains\Insights\Data\Turn;

/**
 * Corta a conversa nas vezes em que o cliente esperou resposta. Mensagens do
 * cliente em sequência são uma vez só; aviso do sistema e resposta
 * automática não encerram a espera.
 */
final class ClientTurns
{
    /**
     * @return list<Turn>
     */
    public static function of(Timeline $timeline): array
    {
        $turns = [];
        $open = null;

        foreach ($timeline->messages as $message) {
            if ($message->author === 'contact') {
                $open = $open === null
                    ? ['start' => $message->at, 'last' => $message->at, 'body' => $message->body]
                    : ['start' => $open['start'], 'last' => $message->at, 'body' => $message->body];

                continue;
            }

            if ($message->author === 'seller' && $open !== null) {
                $turns[] = new Turn($timeline->conversationId, $open['start'], $open['last'], $open['body'], $message->at, $message->seller);
                $open = null;
            }
        }

        if ($open !== null) {
            $turns[] = new Turn($timeline->conversationId, $open['start'], $open['last'], $open['body']);
        }

        return $turns;
    }
}
