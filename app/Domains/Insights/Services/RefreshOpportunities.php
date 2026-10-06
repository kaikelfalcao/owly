<?php

namespace App\Domains\Insights\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Conversations\Data\History;
use App\Domains\Insights\Data\DecidedOpportunity;
use App\Domains\Insights\Models\Opportunity;
use App\Domains\Insights\Rules\OpportunityRule;
use Illuminate\Support\Facades\DB;

/**
 * Grava as oportunidades que a regra espera, cliente a cliente. Pode rodar
 * quantas vezes for: acha a linha pela âncora e atualiza. Linha decidida
 * pelo dono só acompanha o atendimento onde a âncora está (invariante I9);
 * linha da regra que deixou de ser esperada é apagada.
 */
class RefreshOpportunities
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly ConversationFacts $facts,
    ) {}

    /**
     * @param  list<int>  $contactIds
     * @return array{created: int, updated: int, deleted: int}
     */
    public function refresh(int $organizationId, array $contactIds): array
    {
        $totals = ['created' => 0, 'updated' => 0, 'deleted' => 0];

        foreach ($this->facts->histories($organizationId, $contactIds) as $history) {
            $counts = $this->organization->ensure($organizationId, fn () => DB::transaction(fn () => $this->one($organizationId, $history)));

            foreach ($counts as $key => $count) {
                $totals[$key] += $count;
            }
        }

        return $totals;
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    private function one(int $organizationId, History $history): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $stored = Opportunity::where('contact_id', $history->contactId)->get()->keyBy('anchor_message_id');
        $conversationOf = [];

        foreach ($history->messages as $message) {
            $conversationOf[$message->id] = $message->conversationId;
        }

        $decided = $stored->filter(fn (Opportunity $o) => $o->decided())
            ->map(fn (Opportunity $o) => new DecidedOpportunity($o->anchor_message_id, $o->status, $o->closed_at?->toImmutable()))
            ->all();

        $expected = OpportunityRule::expected($history->messages, $decided);
        $keep = [];

        foreach ($expected as $rule) {
            $keep[$rule->anchorMessageId] = true;
            $values = [
                'contact_id' => $history->contactId,
                'conversation_id' => $rule->conversationId,
                'seller_id' => $rule->sellerId,
                'status' => $rule->status,
                'opened_at' => $rule->openedAt,
                'closing_message_id' => $rule->closingMessageId,
                'closed_at' => $rule->closedAt,
            ];

            $row = $stored[$rule->anchorMessageId] ?? null;

            if ($row === null) {
                Opportunity::create([...$values, 'organization_id' => $organizationId, 'anchor_message_id' => $rule->anchorMessageId, 'source' => Opportunity::RULE]);
                $counts['created']++;

                continue;
            }

            if ($this->apply($row, $values)) {
                $counts['updated']++;
            }
        }

        foreach ($stored as $anchor => $row) {
            if ($row->decided()) {
                // Decisão do dono fica; o atendimento da âncora pode ter mudado no corte.
                if ($this->apply($row, ['conversation_id' => $conversationOf[$anchor] ?? $row->conversation_id])) {
                    $counts['updated']++;
                }
            } elseif (! isset($keep[$anchor])) {
                $row->delete();
                $counts['deleted']++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function apply(Opportunity $row, array $values): bool
    {
        // O Eloquent compara datas e números pelo valor: só grava o que mudou.
        $row->fill($values);

        if (! $row->isDirty()) {
            return false;
        }

        $row->save();

        return true;
    }
}
