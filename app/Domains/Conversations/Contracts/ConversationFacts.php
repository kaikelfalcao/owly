<?php

namespace App\Domains\Conversations\Contracts;

use App\Domains\Conversations\Data\ConversationSummary;
use App\Domains\Conversations\Data\Timeline;
use Carbon\CarbonImmutable;

/**
 * Leitura em lote para quem analisa as conversas (o painel), sempre dentro
 * da empresa.
 */
interface ConversationFacts
{
    /** A mensagem mais recente da empresa: o "hoje" de quem só tem zip. */
    public function latestMessageAt(int $organizationId): ?CarbonImmutable;

    /**
     * As conversas com mensagem a partir de $since (todas, se null), cada
     * uma com todas as mensagens.
     *
     * @return iterable<Timeline>
     */
    public function timelines(int $organizationId, ?CarbonImmutable $since): iterable;

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, ConversationSummary> por id
     */
    public function summaries(int $organizationId, array $conversationIds): array;
}
