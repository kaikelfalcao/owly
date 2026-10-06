<?php

namespace App\Console\Commands;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Accounts\Models\Organization;
use App\Domains\Conversations\Contracts\ConversationIntegrity;
use App\Domains\Insights\Data\Period;
use App\Domains\Insights\Services\Dashboard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Foto dos dados de uma empresa antes de uma migração e conferência depois.
 * Junta Conversas (contagens, hash das mensagens, problemas) e o painel, por
 * isso mora fora dos domínios. Só imprime e guarda números e hashes.
 */
class CheckIntegrity extends Command
{
    protected $signature = 'owly:integrity
        {--empresa= : Id da empresa}
        {--snapshot : Guarda a foto de antes da migração}
        {--compare : Confere os dados de hoje contra a última foto}';

    protected $description = 'Confere que uma migração não perdeu nem mudou mensagens';

    /** O que, do painel, entra na foto: a migração pode mudar de propósito, então só aparece lado a lado. */
    private const PANEL = [
        'conversations' => 'Conversas',
        'turns' => 'Vezes do cliente',
        'unanswered' => 'Sem resposta',
        'answered' => 'Respondidas',
        'median_response_seconds' => 'Mediana até responder (s)',
        'out_of_hours' => 'Fora do horário',
        'quotes_sent' => 'Orçamentos',
        'quotes_stalled' => 'Orçamentos parados',
        'sales' => 'Vendas prováveis',
    ];

    public function handle(CurrentOrganization $current, ConversationIntegrity $integrity, Dashboard $dashboard): int
    {
        $organizationId = (int) $this->option('empresa');

        if ($this->option('snapshot') === $this->option('compare')) {
            $this->components->error('Use --snapshot ou --compare.');

            return self::INVALID;
        }

        if (Organization::find($organizationId) === null) {
            $this->components->error('Empresa não encontrada. Use --empresa=ID.');

            return self::INVALID;
        }

        return $current->runAs($organizationId, function () use ($current, $integrity, $dashboard, $organizationId): int {
            $now = [
                'facts' => $integrity->facts($organizationId)->toArray(),
                'panel' => $this->panel($current, $dashboard),
            ];
            $problems = $integrity->problems($organizationId);

            return $this->option('snapshot')
                ? $this->snapshot($organizationId, $now, $problems)
                : $this->compare($organizationId, $now, $problems);
        });
    }

    /**
     * @param  array{facts: array<string, int|string>, panel: array<string, array<string, int|null>>}  $now
     * @param  list<string>  $problems
     */
    private function snapshot(int $organizationId, array $now, array $problems): int
    {
        // Dado já errado antes da migração barra a migração.
        if ($problems !== []) {
            $this->problems($problems);
            $this->components->error('Os dados de hoje já têm problemas. Corrija antes de migrar.');

            return self::FAILURE;
        }

        $path = sprintf('integrity/organization-%d-%s.json', $organizationId, now()->format('Ymd-His'));
        Storage::disk('local')->put($path, (string) json_encode(['taken_at' => now()->toIso8601String(), ...$now], JSON_PRETTY_PRINT));

        $this->table(['', 'Agora'], $this->rows($now['facts']));
        $this->components->info('Foto guardada em '.Storage::disk('local')->path($path).'.');

        return self::SUCCESS;
    }

    /**
     * @param  array{facts: array<string, int|string>, panel: array<string, array<string, int|null>>}  $now
     * @param  list<string>  $problems
     */
    private function compare(int $organizationId, array $now, array $problems): int
    {
        $path = collect(Storage::disk('local')->files('integrity'))
            ->filter(fn (string $file) => str_starts_with(basename($file), "organization-{$organizationId}-"))
            ->sort()
            ->last();

        if ($path === null) {
            $this->components->error('Não há foto desta empresa. Rode antes com --snapshot.');

            return self::FAILURE;
        }

        $before = json_decode((string) Storage::disk('local')->get($path), true);

        $this->table(['', 'Antes', 'Agora'], $this->rows($before['facts'], $now['facts']));

        foreach (array_keys(Period::OPTIONS) as $key) {
            $this->line("Painel, período {$key}:");
            $this->table(['', 'Antes', 'Agora'], array_map(
                fn (string $field, string $label) => [$label, $before['panel'][$key][$field] ?? '-', $now['panel'][$key][$field] ?? '-'],
                array_keys(self::PANEL),
                self::PANEL,
            ));
        }

        foreach (['messages' => 'O total de mensagens mudou.', 'messages_hash' => 'As mensagens mudaram (hash diferente).', 'contacts' => 'O total de clientes mudou.'] as $field => $message) {
            if ($before['facts'][$field] !== $now['facts'][$field]) {
                $problems[] = $message;
            }
        }

        if ($problems !== []) {
            $this->problems($problems);
            $this->components->error('A conferência falhou.');

            return self::FAILURE;
        }

        $this->components->info('Tudo confere com a foto de '.basename($path).'.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<string, int|null>>
     */
    private function panel(CurrentOrganization $current, Dashboard $dashboard): array
    {
        $calendar = $current->calendar();
        $panel = [];

        foreach (array_keys(Period::OPTIONS) as $key) {
            $overview = $dashboard->overview($calendar, (string) $key);
            $panel[$key] = [
                'conversations' => $overview['totals']['conversations'],
                'turns' => $overview['totals']['turns'],
                'unanswered' => $overview['unanswered'],
                'answered' => $overview['response']['answered'],
                'median_response_seconds' => $overview['response']['medianSeconds'],
                'out_of_hours' => $overview['outOfHours']['turns'],
                'quotes_sent' => $overview['quotes']['sent'],
                'quotes_stalled' => $overview['quotes']['stalled'],
                'sales' => $overview['sales'],
            ];
        }

        return $panel;
    }

    /**
     * @param  array<string, int|string>  ...$columns
     * @return list<list<int|string>>
     */
    private function rows(array ...$columns): array
    {
        $labels = [
            'messages' => 'Mensagens',
            'contacts' => 'Clientes',
            'conversations' => 'Conversas',
            'empty_conversations' => 'Conversas sem mensagem',
            'messages_hash' => 'Hash das mensagens',
        ];

        return array_map(
            fn (string $field, string $label) => [$label, ...array_map(fn (array $column) => $column[$field] ?? '-', $columns)],
            array_keys($labels),
            $labels,
        );
    }

    /**
     * @param  list<string>  $problems
     */
    private function problems(array $problems): void
    {
        foreach ($problems as $problem) {
            $this->components->warn($problem);
        }
    }
}
