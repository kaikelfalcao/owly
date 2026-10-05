<?php

namespace App\Platform\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as Monolog;

/**
 * Deixa um canal de log em JSON, uma linha por registro, com a limpeza de
 * dados pessoais. Ligado pelo `tap` do canal `json` em config/logging.php.
 */
class StructuredLogs
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            return;
        }

        $monolog->pushProcessor(new ScrubPersonalData);

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter(includeStacktraces: true));
            }
        }
    }
}
