<?php

namespace App\Domains\Ai\Data;

/**
 * O que vai para o provedor, já sem dados pessoais.
 */
final readonly class Prompt
{
    public function __construct(
        public string $instructions,
        public string $content,
    ) {}
}
