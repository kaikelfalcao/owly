<?php

namespace App\Domains\Insights\Services;

use App\Domains\Insights\Models\Opportunity;
use App\Platform\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * As decisões do dono numa oportunidade. Toda decisão trava a linha contra
 * a regra (decided_at) e vai para a auditoria só com códigos.
 */
class DecideOpportunity
{
    /** De onde cada decisão pode partir. */
    private const FROM = [
        Opportunity::WON => [Opportunity::OPEN],
        Opportunity::LOST => [Opportunity::OPEN],
        Opportunity::DISCARDED => [Opportunity::OPEN],
        Opportunity::OPEN => [Opportunity::WON, Opportunity::LOST, Opportunity::DISCARDED],
    ];

    private const ACTIONS = [
        Opportunity::WON => 'insights.opportunity_won',
        Opportunity::LOST => 'insights.opportunity_lost',
        Opportunity::DISCARDED => 'insights.opportunity_discarded',
        Opportunity::OPEN => 'insights.opportunity_reopened',
    ];

    public function __construct(private readonly Audit $audit) {}

    public function decide(int $organizationId, int $userId, int $opportunityId, string $status, ?string $lossReason = null): Opportunity
    {
        return DB::transaction(function () use ($organizationId, $userId, $opportunityId, $status, $lossReason): Opportunity {
            $opportunity = Opportunity::where('organization_id', $organizationId)->lockForUpdate()->findOrFail($opportunityId);
            $from = $opportunity->status;

            if (! in_array($from, self::FROM[$status], true)) {
                throw ValidationException::withMessages(['opportunity' => 'Esta oportunidade mudou enquanto a tela estava aberta. Atualize a página.']);
            }

            $closed = $status !== Opportunity::OPEN;

            $opportunity->update([
                'status' => $status,
                'loss_reason' => $status === Opportunity::LOST ? $lossReason : null,
                'closed_at' => $closed ? now() : null,
                // Ganha pelo dono não tem mensagem de venda; reaberta perde a dela.
                'closing_message_id' => $status === Opportunity::OPEN || $status === Opportunity::WON ? null : $opportunity->closing_message_id,
                'decided_by' => $userId,
                'decided_at' => now(),
            ]);

            $this->audit->record(
                self::ACTIONS[$status],
                $opportunity,
                $status === Opportunity::LOST ? ['reason' => $lossReason] : [],
                userId: $userId,
                organizationId: $organizationId,
                changes: ['status' => [$from, $status]],
            );

            return $opportunity;
        });
    }
}
