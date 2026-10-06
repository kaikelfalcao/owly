<?php

namespace App\Domains\Ai\Listeners;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Conversations\Events\ConversationsRecut;

/**
 * Quando atendimentos são refeitos, a pergunta à IA acompanha a mensagem em
 * foco. Sem mensagem em foco, fica no atendimento que herdou o começo do
 * antigo (ou onde estava, se ele continua existindo).
 */
class ReassignQuestions
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly ConversationFacts $facts,
    ) {}

    public function handle(ConversationsRecut $event): void
    {
        $this->organization->ensure($event->organizationId, function () use ($event): void {
            $questions = AiQuestion::whereIn('conversation_id', $event->conversationIds)->get(['id', 'conversation_id', 'message_id']);

            if ($questions->isEmpty()) {
                return;
            }

            $now = $this->facts->conversationsOfMessages($event->organizationId, $questions->pluck('message_id')->filter()->values()->all());

            foreach ($questions as $question) {
                $target = $question->message_id !== null
                    ? ($now[$question->message_id] ?? null)
                    : ($event->replaced[$question->conversation_id] ?? null);

                if ($target !== null && $target !== $question->conversation_id) {
                    $question->update(['conversation_id' => $target]);
                }
            }
        });
    }
}
