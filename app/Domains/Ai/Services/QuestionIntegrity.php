<?php

namespace App\Domains\Ai\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Conversations\Contracts\ConversationFacts;

/**
 * Para o owly:integrity: toda pergunta à IA aponta para um atendimento que
 * existe e que contém a mensagem em foco.
 */
class QuestionIntegrity
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly ConversationFacts $facts,
    ) {}

    /**
     * @return list<string>
     */
    public function problems(int $organizationId): array
    {
        return $this->organization->ensure($organizationId, function () use ($organizationId): array {
            $questions = AiQuestion::query()->get(['id', 'conversation_id', 'message_id']);

            if ($questions->isEmpty()) {
                return [];
            }

            $existing = $this->facts->summaries($organizationId, $questions->pluck('conversation_id')->unique()->values()->all());
            $holding = $this->facts->conversationsOfMessages($organizationId, $questions->pluck('message_id')->filter()->unique()->values()->all());

            $wrong = $questions->filter(fn (AiQuestion $question) => ! isset($existing[$question->conversation_id])
                || ($question->message_id !== null && ($holding[$question->message_id] ?? null) !== $question->conversation_id));

            return $wrong->isEmpty() ? [] : [sprintf(
                'Perguntas à IA fora do atendimento da mensagem ou em atendimento que não existe: %d (%s)',
                $wrong->count(),
                $wrong->take(5)->map(fn (AiQuestion $question) => "#{$question->id}")->implode(', '),
            )];
        });
    }
}
