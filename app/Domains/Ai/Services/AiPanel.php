<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Data\AiFailed;
use App\Domains\Ai\Models\AiConnection;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Ai\ProviderCatalog;

/**
 * O que a tela da conversa mostra da IA: se há conexão e as perguntas já
 * feitas sobre aquela conversa.
 */
class AiPanel
{
    public function __construct(private readonly ProviderCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function props(int $organizationId, int $conversationId): array
    {
        $connection = AiConnection::where('organization_id', $organizationId)->where('is_default', true)->first();

        return [
            'connection' => $connection ? [
                'providerName' => $this->catalog->name($connection->provider),
                'model' => $connection->model,
            ] : null,
            'questions' => AiQuestion::where('organization_id', $organizationId)
                ->where('conversation_id', $conversationId)
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (AiQuestion $q) => [
                    'id' => $q->id,
                    'question' => $q->question,
                    'messageId' => $q->message_id,
                    'answer' => $q->answer,
                    'error' => $q->error_code ? (AiFailed::MESSAGES[$q->error_code] ?? AiFailed::MESSAGES['unexpected']) : null,
                    'providerName' => $this->catalog->name($q->provider),
                    'model' => $q->model,
                    'createdAt' => $q->created_at->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
