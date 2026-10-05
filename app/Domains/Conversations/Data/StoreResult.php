<?php

namespace App\Domains\Conversations\Data;

final readonly class StoreResult
{
    public function __construct(
        public int $conversationId,
        public bool $created,
        public int $newMessages,
        public int $knownMessages,
    ) {}
}
