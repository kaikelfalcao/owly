<?php

namespace App\Domains\Insights\Jobs;

use App\Domains\Accounts\Jobs\ForOrganization;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Insights\Services\RefreshOpportunities;
use App\Platform\Queue\OneAtATime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Recalcula o que Insights guarda (as oportunidades) para os clientes de
 * uma importação. Usa a mesma trava da importação: não corre junto com
 * outra importação nem com outro recálculo da empresa. Nunca chama IA.
 */
class RefreshInsights implements ShouldQueue
{
    use Queueable;

    /** Esperar a importação da empresa terminar não conta como falha. */
    public int $tries = 20;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 600;

    public function __construct(public int $organizationId, public int $importId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new ForOrganization($this->organizationId),
            OneAtATime::organization($this->organizationId, $this->timeout),
        ];
    }

    public function handle(ConversationFacts $facts, RefreshOpportunities $opportunities): void
    {
        $contacts = $facts->contactsWithMessages($this->organizationId, $this->importId);
        $counts = $opportunities->refresh($this->organizationId, $contacts);

        Log::info('oportunidades recalculadas', ['import_id' => $this->importId, 'contacts' => count($contacts), ...$counts]);
    }
}
