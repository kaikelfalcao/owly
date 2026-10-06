<?php

namespace App\Domains\Conversations\Contracts;

use App\Domains\Conversations\Data\IntegrityFacts;

/**
 * Confere os dados de Conversas antes e depois de uma migração
 * (owly:integrity). Responde só com números, ids e hashes.
 */
interface ConversationIntegrity
{
    public function facts(int $organizationId): IntegrityFacts;

    /**
     * O que está errado nos dados de hoje; vazio quando está tudo certo.
     *
     * @return list<string>
     */
    public function problems(int $organizationId): array;
}
