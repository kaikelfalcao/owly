<?php

namespace App\Domains\Insights\Rules;

use App\Domains\Conversations\Data\MessageFact;

/**
 * Orçamento enviado: mensagem da empresa com preço ("R$ 90") ou com um
 * documento anexo (o orçamento em PDF, que não vem no zip).
 */
final class QuoteMessage
{
    public static function is(MessageFact $message): bool
    {
        if ($message->direction !== 'out' || $message->author === 'bot') {
            return false;
        }

        return $message->mediaType === 'document'
            || ($message->body !== null && preg_match('/R\$\s?\d/u', $message->body) === 1);
    }
}
