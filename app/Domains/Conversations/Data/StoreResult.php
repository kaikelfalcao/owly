<?php

namespace App\Domains\Conversations\Data;

final readonly class StoreResult
{
    /**
     * @param  int  $conversationsCreated  atendimentos novos
     * @param  int  $conversationsChanged  atendimentos que já existiam e foram refeitos
     */
    public function __construct(
        public int $contactId,
        public bool $contactCreated,
        public int $newMessages,
        public int $knownMessages,
        public int $conversationsCreated,
        public int $conversationsChanged,
    ) {}
}
