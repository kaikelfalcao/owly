<?php

namespace App\Platform\Audit;

use App\Models\User;
use App\Platform\Http\ActivityRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Monta a consulta da Atividade de uma empresa a partir dos filtros da tela.
 * Tudo é filtrado no banco; criticidade, resultado e texto da ação viram a
 * lista de códigos que o catálogo conhece.
 */
class AuditSearch
{
    public function __construct(
        private readonly int $organizationId,
        private readonly string $timezone,
    ) {}

    /**
     * @param  array<string, string>  $filters  já validados ({@see ActivityRequest})
     * @return Builder<AuditEntry>
     */
    public function query(array $filters): Builder
    {
        $query = AuditEntry::query()->where('organization_id', $this->organizationId);

        if ($range = $this->period($filters)) {
            $query->where('created_at', '>=', $range[0])->where('created_at', '<', $range[1]);
        }

        match ($filters['user'] ?? null) {
            null => null,
            'none' => $query->whereNull('user_id'),
            default => $query->where('user_id', (int) $filters['user']),
        };

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['resource'])) {
            $query->where('subject_type', AuditCatalog::resourceType($filters['resource']));

            if (isset($filters['resource_id'])) {
                $query->where('subject_id', (int) $filters['resource_id']);
            }
        }

        match (isset($filters['result']) ? Outcome::from($filters['result']) : null) {
            null => null,
            Outcome::Failure => $query->whereIn('action', AuditCatalog::failures()),
            Outcome::Success => $query->whereNotIn('action', AuditCatalog::failures()),
        };

        if (isset($filters['severity'])) {
            $this->severity($query, Severity::from($filters['severity']));
        }

        if (isset($filters['q'])) {
            $this->search($query, $filters['q']);
        }

        return $query;
    }

    /**
     * Início e fim (exclusivo) do período, contados no fuso da empresa e
     * convertidos para UTC, que é como o banco guarda.
     *
     * @param  array<string, string>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function period(array $filters): ?array
    {
        $today = CarbonImmutable::now($this->timezone)->startOfDay();
        $tomorrow = $today->addDay();

        $range = match ($filters['period'] ?? null) {
            'today' => [$today, $tomorrow],
            'yesterday' => [$today->subDay(), $today],
            '7d' => [$today->subDays(6), $tomorrow],
            '30d' => [$today->subDays(29), $tomorrow],
            'month' => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
            'custom' => [
                CarbonImmutable::createFromFormat('Y-m-d', $filters['from'], $this->timezone)->startOfDay(),
                CarbonImmutable::createFromFormat('Y-m-d', $filters['to'], $this->timezone)->startOfDay()->addDay(),
            ],
            default => null,
        };

        return $range === null ? null : [$range[0]->utc(), $range[1]->utc()];
    }

    /**
     * Ação fora do catálogo conta como Normal, então "Normal" é o que não
     * está nas outras listas.
     *
     * @param  Builder<AuditEntry>  $query
     */
    private function severity(Builder $query, Severity $severity): void
    {
        if ($severity === Severity::Normal) {
            $query->whereNotIn('action', [
                ...AuditCatalog::actionsWithSeverity(Severity::Important),
                ...AuditCatalog::actionsWithSeverity(Severity::Critical),
            ]);

            return;
        }

        $query->whereIn('action', AuditCatalog::actionsWithSeverity($severity));
    }

    /**
     * Uma busca procura ao mesmo tempo no nome de quem fez (ou do login
     * afetado), no texto da ação, no id do registro, no IP e no request id.
     *
     * @param  Builder<AuditEntry>  $query
     */
    private function search(Builder $query, string $search): void
    {
        // Curinga digitado vira texto comum, não "qualquer coisa".
        $term = str_replace(['%', '_', '\\'], '', $search);
        $actions = AuditCatalog::actionsMatching($search);
        $users = $term === '' ? collect() : User::query()
            ->where('organization_id', $this->organizationId)
            ->whereLike('name', "%{$term}%")
            ->pluck('id');

        $query->where(function (Builder $where) use ($search, $term, $actions, $users) {
            $where->whereRaw('1 = 0');

            if ($term !== '') {
                $where->orWhereLike('ip', "{$term}%")->orWhereLike('request_id', "{$term}%");
            }

            if ($actions !== []) {
                $where->orWhereIn('action', $actions);
            }

            if (preg_match('/^#?(\d{1,18})$/', $search, $id) === 1) {
                $where->orWhere('subject_id', (int) $id[1]);
            }

            if ($users->isNotEmpty()) {
                $where->orWhereIn('user_id', $users)->orWhere(fn (Builder $subject) => $subject
                    ->where('subject_type', AuditCatalog::resourceType('user'))
                    ->whereIn('subject_id', $users));
            }
        });
    }
}
