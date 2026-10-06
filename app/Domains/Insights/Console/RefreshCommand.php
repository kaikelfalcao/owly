<?php

namespace App\Domains\Insights\Console;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Insights\Models\Opportunity;
use App\Domains\Insights\Services\RefreshOpportunities;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Recalcula as oportunidades de todos os clientes de uma empresa. Roda uma
 * vez depois de subir a tabela (as importações antigas não passaram pela
 * regra) e pode rodar de novo sem duplicar nem mexer em decisão do dono.
 */
class RefreshCommand extends Command
{
    protected $signature = 'owly:insights {--empresa= : Id da empresa}';

    protected $description = 'Recalcula as oportunidades de uma empresa';

    public function handle(CurrentOrganization $current, ConversationFacts $facts, RefreshOpportunities $opportunities): int
    {
        $organizationId = (int) $this->option('empresa');

        try {
            $current->runAs($organizationId, fn () => $current->get());
        } catch (ModelNotFoundException) {
            $this->components->error('Empresa não encontrada. Use --empresa=ID.');

            return self::INVALID;
        }

        $contacts = $facts->contactsWithMessages($organizationId);
        $counts = $opportunities->refresh($organizationId, $contacts);

        $byStatus = $current->runAs($organizationId, fn () => Opportunity::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all());

        $this->table(['', 'Total'], [
            ['Clientes', count($contacts)],
            ['Oportunidades criadas', $counts['created']],
            ['Oportunidades atualizadas', $counts['updated']],
            ['Oportunidades apagadas (a regra não espera mais)', $counts['deleted']],
            ['Abertas', (int) ($byStatus[Opportunity::OPEN] ?? 0)],
            ['Ganhas', (int) ($byStatus[Opportunity::WON] ?? 0)],
            ['Perdidas', (int) ($byStatus[Opportunity::LOST] ?? 0)],
            ['Descartadas', (int) ($byStatus[Opportunity::DISCARDED] ?? 0)],
        ]);

        return self::SUCCESS;
    }
}
