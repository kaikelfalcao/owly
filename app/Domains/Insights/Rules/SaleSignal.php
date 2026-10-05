<?php

namespace App\Domains\Insights\Rules;

use App\Domains\Conversations\Data\MessageFact;

/**
 * O cliente fechou: autorizou ("pode fazer", "fechado"), pagou ("fiz o pix",
 * "segue o comprovante"). É uma leitura provável, não a nota fiscal.
 */
final class SaleSignal
{
    private const PATTERN = '/(pode (fazer|fechar|mandar fazer|produzir|imprimir)|\bfechad[oa]\b|\bfechou\b|vou querer|quero sim|pode seguir|segue (o )?comprovante|comprovante|\bpaguei\b|pix (feito|enviado|realizado)|fiz o pix|\btransferi\b)/iu';

    public static function is(MessageFact $message): bool
    {
        return $message->author === 'contact'
            && $message->body !== null
            && preg_match(self::PATTERN, $message->body) === 1;
    }
}
