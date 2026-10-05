<?php

namespace App\Domains\Imports\Data;

/**
 * A última importação concluída, para o painel mostrar a saúde dos dados.
 */
final readonly class ImportSummary
{
    /**
     * @param  list<array{from: string, to: string, days: int}>  $gaps
     */
    public function __construct(
        public string $finishedAt,
        public ?string $firstAt,
        public ?string $lastAt,
        public array $gaps,
        public int $problems,
    ) {}
}
