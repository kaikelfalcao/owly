<?php

namespace App\Domains\Insights\Rules;

/**
 * "Obrigado", "ok", "👍": o cliente encerrou, não está esperando resposta.
 * No zip da Gráfica, um terço das conversas que terminam no cliente
 * terminam assim.
 */
final class ClosingMessage
{
    private const PATTERN = '/^\s*(ok(ay)?|obrigad[oa]s?|muito obrigad[oa]|obg|vlw|valeu|blz|beleza|show|perfeito|certo|combinado|t[aá] bom|tudo bem|de nada|at[eé] mais|bom dia|boa tarde|boa noite|👍|🙏|😊|😉|❤️|👏)([\s!.,]|👍|🙏|😊|😉|❤️|👏)*$/iu';

    public static function is(?string $body): bool
    {
        return $body !== null && preg_match(self::PATTERN, $body) === 1;
    }
}
