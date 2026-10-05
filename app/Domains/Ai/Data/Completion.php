<?php

namespace App\Domains\Ai\Data;

final readonly class Completion
{
    public function __construct(
        public string $text,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}
}
