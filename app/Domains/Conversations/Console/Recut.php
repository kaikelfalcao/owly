<?php

namespace App\Domains\Conversations\Console;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Events\ConversationsRecut;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Rules\ConversationCut;
use App\Domains\Conversations\Services\ConversationCutter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Refaz os atendimentos de uma empresa com a regra e o calendário de hoje.
 * Roda depois da migração que transformou conversa em atendimento, ou de
 * propósito depois de mudar o horário. Um cliente por transação: pode parar
 * no meio e rodar de novo, e rodar de novo sem mudança não muda nada.
 */
class Recut extends Command
{
    protected $signature = 'owly:recut
        {--empresa= : Id da empresa}
        {--dry-run : Só mostra o que mudaria}';

    protected $description = 'Refaz os atendimentos de uma empresa pela regra de corte';

    public function handle(CurrentOrganization $current, ConversationCutter $cutter, ConversationStore $store, ConversationFacts $facts): int
    {
        $organizationId = (int) $this->option('empresa');
        $dry = (bool) $this->option('dry-run');

        try {
            $current->runAs($organizationId, fn () => $current->get());
        } catch (ModelNotFoundException) {
            $this->components->error('Empresa não encontrada. Use --empresa=ID.');

            return self::INVALID;
        }

        return $current->runAs($organizationId, function () use ($organizationId, $dry, $cutter, $store, $facts): int {
            $reference = $facts->latestMessageAt($organizationId);

            if ($reference === null) {
                $this->components->info('A empresa ainda não tem mensagens.');

                return self::SUCCESS;
            }

            $totals = ['contacts' => 0, 'before' => Conversation::count(), 'after' => 0, 'company' => 0, 'moved' => 0, 'created' => 0, 'deleted' => 0];

            foreach (Contact::query()->lazyById(200) as $contact) {
                $totals['contacts']++;

                $plan = $dry
                    ? $cutter->plan($contact, [], $reference, full: true)
                    : DB::transaction(function () use ($cutter, $contact, $reference, $organizationId) {
                        $plan = $cutter->plan($contact, [], $reference, full: true);
                        $outcome = $cutter->apply($plan);

                        if ($outcome->changed !== []) {
                            ConversationsRecut::dispatch($organizationId, [$contact->id], $outcome->changed, $outcome->replaced);
                        }

                        return $plan;
                    });

                $totals['after'] += count($plan->segments);
                $totals['company'] += count(array_filter($plan->segments, fn ($segment) => $segment->openedBy === ConversationCut::COMPANY));
                $totals['moved'] += count($plan->moves);
                $totals['created'] += count(array_filter($plan->targets, fn ($target) => $target === null));
                $totals['deleted'] += count($plan->leftovers);
            }

            if (! $dry) {
                $store->closeIdle($organizationId, $reference);
            }

            $this->table(['', $dry ? 'Sairia' : 'Ficou'], [
                ['Clientes', $totals['contacts']],
                ['Atendimentos antes', $totals['before']],
                ['Atendimentos depois', $totals['after']],
                ['Começaram pela empresa', $totals['company']],
                ['Mensagens que mudam de atendimento', $totals['moved']],
                ['Atendimentos novos', $totals['created']],
                ['Atendimentos apagados (vazios depois de juntar)', $totals['deleted']],
            ]);

            $this->components->info($dry ? 'Nada foi gravado (--dry-run).' : 'Atendimentos refeitos.');

            return self::SUCCESS;
        });
    }
}
