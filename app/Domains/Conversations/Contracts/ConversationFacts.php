<?php

namespace App\Domains\Conversations\Contracts;

use App\Domains\Conversations\Data\ConversationSummary;
use App\Domains\Conversations\Data\History;
use App\Domains\Conversations\Data\Timeline;
use Carbon\CarbonImmutable;

/**
 * Leitura em lote para quem analisa as conversas (o painel), sempre dentro
 * da empresa. A unidade é o atendimento.
 */
interface ConversationFacts
{
    /** A mensagem mais recente da empresa: o "hoje" de quem só tem zip. */
    public function latestMessageAt(int $organizationId): ?CarbonImmutable;

    /**
     * Os atendimentos com mensagem a partir de $since (todos, se null), cada
     * um com todas as mensagens.
     *
     * @return iterable<Timeline>
     */
    public function timelines(int $organizationId, ?CarbonImmutable $since): iterable;

    /**
     * O histórico inteiro de cada cliente, com o atendimento de cada mensagem.
     *
     * @param  list<int>  $contactIds
     * @return iterable<History>
     */
    public function histories(int $organizationId, array $contactIds): iterable;

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, ConversationSummary> por id; atendimento que não existe fica de fora
     */
    public function summaries(int $organizationId, array $conversationIds): array;

    /**
     * Em que atendimento cada mensagem está hoje.
     *
     * @param  list<int>  $messageIds
     * @return array<int, int> mensagem => atendimento; mensagem que não existe fica de fora
     */
    public function conversationsOfMessages(int $organizationId, array $messageIds): array;
}
