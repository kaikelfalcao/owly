<?php

namespace App\Domains\Insights\Services;

use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Insights\Models\Opportunity;
use Carbon\CarbonImmutable;

/**
 * O que a tela do atendimento mostra das oportunidades do cliente: as que
 * abriram ou fecharam ali e as que vêm abertas de um atendimento anterior.
 */
class OpportunityPanel
{
    public function __construct(private readonly ConversationFacts $facts) {}

    /**
     * @return array<string, mixed>
     */
    public function props(int $organizationId, int $conversationId): array
    {
        $summary = $this->facts->summaries($organizationId, [$conversationId])[$conversationId] ?? null;
        $reasons = collect(Opportunity::LOSS_REASONS)->map(fn (string $label, string $code) => ['code' => $code, 'label' => $label])->values()->all();

        if ($summary === null) {
            return ['opportunities' => [], 'reasons' => $reasons];
        }

        $lastHere = $summary->lastMessageAt !== null ? CarbonImmutable::parse($summary->lastMessageAt) : null;
        $all = Opportunity::where('organization_id', $organizationId)
            ->where('contact_id', $summary->contactId)
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();

        $closingIn = $this->facts->conversationsOfMessages($organizationId, $all->pluck('closing_message_id')->filter()->values()->all());

        $shown = $all->filter(function (Opportunity $o) use ($conversationId, $closingIn, $lastHere) {
            $closingHere = $o->closing_message_id !== null && ($closingIn[$o->closing_message_id] ?? null) === $conversationId;
            $carried = $o->status === Opportunity::OPEN && $lastHere !== null && $o->opened_at->lessThan($lastHere);

            return $o->conversation_id === $conversationId || $closingHere || $carried;
        });

        return [
            'opportunities' => $shown->map(fn (Opportunity $o) => [
                'id' => $o->id,
                'status' => $o->status,
                'source' => $o->source,
                'decided' => $o->decided(),
                'openedAt' => $o->opened_at->toIso8601String(),
                'closedAt' => $o->closed_at?->toIso8601String(),
                'lossReason' => $o->loss_reason !== null ? (Opportunity::LOSS_REASONS[$o->loss_reason] ?? $o->loss_reason) : null,
                'anchorMessageId' => $o->anchor_message_id,
                'anchorConversationId' => $o->conversation_id,
                'closingMessageId' => $o->closing_message_id,
                'closingConversationId' => $o->closing_message_id !== null ? ($closingIn[$o->closing_message_id] ?? null) : null,
                // Nasceu da mensagem de venda, sem orçamento antes.
                'bornWon' => $o->closing_message_id !== null && $o->closing_message_id === $o->anchor_message_id,
            ])->values()->all(),
            'reasons' => $reasons,
        ];
    }
}
