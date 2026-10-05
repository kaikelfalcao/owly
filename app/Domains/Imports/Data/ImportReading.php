<?php

namespace App\Domains\Imports\Data;

use App\Domains\Conversations\Data\IncomingConversation;

final readonly class ImportReading
{
    /**
     * @param  list<IncomingConversation>  $conversations
     * @param  list<array{file: string, code: string}>  $problems  arquivos que não deu para ler
     */
    public function __construct(
        public array $conversations,
        public array $problems,
        public int $files,
    ) {}
}
