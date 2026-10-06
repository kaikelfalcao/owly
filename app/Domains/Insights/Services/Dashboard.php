<?php

namespace App\Domains\Insights\Services;

use App\Domains\Accounts\Data\WorkingCalendar;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Imports\Contracts\ImportHealth;
use App\Domains\Insights\Data\Period;
use App\Domains\Insights\Rules\Median;
use App\Domains\Insights\Rules\Topics;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * O painel e as listas por trás de cada número.
 */
class Dashboard
{
    public const INSIGHTS = [
        'sem-resposta' => 'Clientes sem resposta',
        'tempo-de-resposta' => 'Tempo até responder',
        'fora-do-horario' => 'Chegaram fora do horário',
        'orcamentos-parados' => 'Orçamentos parados',
        'vendas' => 'Vendas prováveis',
        'procuram' => 'O que os clientes procuram',
        'vendedora' => 'Conversas da vendedora',
    ];

    public function __construct(
        private readonly ConversationFacts $facts,
        private readonly ImportHealth $health,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(WorkingCalendar $calendar, ?string $periodKey): array
    {
        [$period, $insights] = $this->read($calendar, $periodKey);
        $topics = $this->topics();
        $import = $this->health->latest($calendar->organizationId);

        $sellers = [];

        foreach ($insights->sellers as $name => $seller) {
            $sellers[] = [
                'name' => $name,
                'conversations' => count($seller['conversations'] ?? []),
                'messages' => $seller['messages'] ?? 0,
                'medianSeconds' => Median::of($seller['seconds'] ?? []),
            ];
        }

        usort($sellers, fn ($a, $b) => $b['conversations'] <=> $a['conversations']);

        $topicRows = [];

        foreach ($insights->topics as $key => $conversations) {
            $topicRows[] = ['key' => $key, 'label' => $topics->label($key), 'count' => count($conversations)];
        }

        usort($topicRows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'period' => $this->periodProps($period),
            'hasData' => $this->facts->latestMessageAt($calendar->organizationId) !== null,
            'totals' => ['conversations' => $insights->conversations, 'turns' => $insights->turns],
            'unanswered' => count($insights->unanswered),
            'response' => [
                'medianSeconds' => $insights->medianResponse(),
                'withinHourShare' => $insights->answeredWithinHourShare(),
                'answered' => $insights->answeredTurns,
            ],
            'outOfHours' => [
                'turns' => $insights->outOfHoursTurns,
                'share' => $insights->turns > 0 ? $insights->outOfHoursTurns / $insights->turns : null,
            ],
            'quotes' => ['sent' => $insights->quoted, 'stalled' => count($insights->stalledQuotes)],
            'sales' => count($insights->sales),
            'topics' => $topicRows,
            'sellers' => $sellers,
            'health' => $import ? [
                'finishedAt' => $import->finishedAt,
                'gaps' => $import->gaps,
                'problems' => $import->problems,
            ] : null,
            'hoursConfigured' => $calendar->configured,
        ];
    }

    /**
     * As conversas por trás de um número.
     *
     * @return array<string, mixed>
     */
    public function detail(WorkingCalendar $calendar, string $insight, ?string $periodKey, ?string $filter): array
    {
        if (! isset(self::INSIGHTS[$insight])) {
            throw new NotFoundHttpException;
        }

        [$period, $insights] = $this->read($calendar, $periodKey);
        $topics = $this->topics();

        $rows = match ($insight) {
            'sem-resposta' => collect($insights->unanswered)->sortByDesc('seconds')
                ->map(fn ($r) => ['at' => $r['since'], 'seconds' => $r['seconds']]),
            'tempo-de-resposta' => collect($insights->responses)->sortByDesc('seconds')
                ->map(fn ($r) => ['at' => $r['startedAt'], 'seconds' => $r['seconds'], 'seller' => $r['seller']]),
            'fora-do-horario' => collect($insights->outOfHours)->sortByDesc('startedAt')
                ->map(fn ($r) => ['at' => $r['startedAt']]),
            'orcamentos-parados' => collect($insights->stalledQuotes)->sortBy('silentSince')
                ->map(fn ($r) => ['at' => $r['quotedAt'], 'silentSince' => $r['silentSince']]),
            'vendas' => collect($insights->sales)->sortByDesc('at')->map(fn ($r) => ['at' => $r['at']]),
            'procuram' => $topics->has((string) $filter)
                ? collect($insights->topics[$filter] ?? [])->map(fn () => [])
                : throw new NotFoundHttpException,
            'vendedora' => isset($insights->sellers[(string) $filter])
                ? collect($insights->sellers[$filter]['conversations'] ?? [])->map(fn () => [])
                : throw new NotFoundHttpException,
        };

        $summaries = $this->facts->summaries($calendar->organizationId, $rows->keys()->all());

        $list = $rows->map(fn (array $row, int $id) => isset($summaries[$id]) ? [
            'id' => $id,
            'contact' => $summaries[$id]->contact,
            'phone' => $summaries[$id]->phone,
            'lastMessageAt' => $summaries[$id]->lastMessageAt,
            ...$row,
        ] : null)->filter()->values();

        if (in_array($insight, ['procuram', 'vendedora'], true)) {
            $list = $list->sortByDesc('lastMessageAt')->values();
        }

        return [
            'insight' => $insight,
            'title' => match ($insight) {
                'procuram' => 'Procuram: '.$topics->label((string) $filter),
                'vendedora' => 'Conversas de '.$filter,
                default => self::INSIGHTS[$insight],
            },
            'filter' => $filter,
            'period' => $this->periodProps($period),
            'conversations' => $list->take(300)->all(),
            'total' => $list->count(),
        ];
    }

    /**
     * @return array{0: Period, 1: Insights}
     */
    private function read(WorkingCalendar $calendar, ?string $periodKey): array
    {
        $period = Period::make($periodKey, $this->facts->latestMessageAt($calendar->organizationId), $calendar->timezone);
        $insights = (new Insights($period, $calendar, $this->topics(), (int) config('owly.insights.stalled_quote_days', 2)))
            ->read($this->facts->timelines($calendar->organizationId, $period->since));

        return [$period, $insights];
    }

    private function topics(): Topics
    {
        return new Topics(config('owly.insights.topics', []));
    }

    /**
     * @return array<string, string|null>
     */
    private function periodProps(Period $period): array
    {
        return [
            'key' => $period->key,
            'since' => $period->since?->toIso8601String(),
            'until' => $period->until->toIso8601String(),
        ];
    }
}
