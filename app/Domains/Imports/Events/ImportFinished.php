<?php

namespace App\Domains\Imports\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Uma importação terminou e gravou as conversas. Quem lê as conversas
 * (Insights) recalcula o que depende delas. Nunca dispara IA.
 */
final class ImportFinished
{
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $importId,
    ) {}
}
