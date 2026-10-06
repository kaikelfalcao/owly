<?php

namespace App\Domains\Insights\Services;

use App\Domains\Accounts\Data\WorkingCalendar;
use App\Domains\Conversations\Data\MessageFact;
use App\Domains\Conversations\Data\Timeline;
use App\Domains\Insights\Data\Period;
use App\Domains\Insights\Data\Turn;
use App\Domains\Insights\Rules\ClientTurns;
use App\Domains\Insights\Rules\ClosingMessage;
use App\Domains\Insights\Rules\Median;
use App\Domains\Insights\Rules\QuoteMessage;
use App\Domains\Insights\Rules\SaleSignal;
use App\Domains\Insights\Rules\Topics;

/**
 * Passa uma vez por cada conversa e junta o que o painel mostra. Cada número
 * guarda as conversas que o formam, para a tela abrir a lista.
 */
class Insights
{
    /** Conversas por insight: id => detalhe para a lista. */
    public array $unanswered = [];

    public array $stalledQuotes = [];

    public array $sales = [];

    public array $outOfHours = [];

    /** @var array<int, array{seconds: int, startedAt: string, seller: string|null}> pior espera por conversa */
    public array $responses = [];

    /** @var array<string, array<int, true>> tema => conversas */
    public array $topics = [];

    /** @var array<string, array{conversations: array<int, true>, messages: int, seconds: list<int>}> */
    public array $sellers = [];

    public int $conversations = 0;

    public int $turns = 0;

    public int $quoted = 0;

    public int $outOfHoursTurns = 0;

    public int $answeredTurns = 0;

    /** @var list<int> */
    private array $responseSeconds = [];

    public function __construct(
        private readonly Period $period,
        private readonly WorkingCalendar $hours,
        private readonly Topics $topicRules,
        private readonly int $stalledDays,
    ) {}

    /**
     * @param  iterable<Timeline>  $timelines
     */
    public function read(iterable $timelines): self
    {
        foreach ($timelines as $timeline) {
            $this->readOne($timeline);
        }

        return $this;
    }

    private function readOne(Timeline $timeline): void
    {
        $id = $timeline->conversationId;
        $inPeriod = array_values(array_filter($timeline->messages, fn (MessageFact $m) => $this->period->contains($m->at)));

        if ($inPeriod === []) {
            return;
        }

        $this->conversations++;
        $turns = ClientTurns::of($timeline);

        foreach ($turns as $turn) {
            if (! $this->period->contains($turn->startedAt)) {
                continue;
            }

            $this->turns++;
            $this->readTurn($turn);
        }

        $this->readUnanswered($id, $turns);
        $this->readQuotesAndSales($timeline);

        foreach ($inPeriod as $message) {
            if ($message->author === 'contact' && $message->body !== null) {
                foreach ($this->topicRules->in($message->body) as $topic) {
                    $this->topics[$topic][$id] = true;
                }
            }

            if ($message->author === 'seller' && $message->seller !== null) {
                $this->sellers[$message->seller]['conversations'][$id] = true;
                $this->sellers[$message->seller]['messages'] = ($this->sellers[$message->seller]['messages'] ?? 0) + 1;
            }
        }
    }

    private function readTurn(Turn $turn): void
    {
        $id = $turn->conversationId;

        if (! $this->hours->isOpen($turn->startedAt)) {
            $this->outOfHoursTurns++;
            $this->outOfHours[$id] ??= ['startedAt' => $turn->startedAt->toIso8601String()];
        }

        if (! $turn->answered()) {
            return;
        }

        $seconds = $this->hours->secondsBetween($turn->startedAt, $turn->answeredAt);
        $this->responseSeconds[] = $seconds;
        $this->answeredTurns++;

        if (! isset($this->responses[$id]) || $this->responses[$id]['seconds'] < $seconds) {
            $this->responses[$id] = ['seconds' => $seconds, 'startedAt' => $turn->startedAt->toIso8601String(), 'seller' => $turn->answeredBy];
        }

        if ($turn->answeredBy !== null) {
            $this->sellers[$turn->answeredBy]['seconds'][] = $seconds;
        }
    }

    /**
     * @param  list<Turn>  $turns
     */
    private function readUnanswered(int $id, array $turns): void
    {
        $last = end($turns);

        if ($last === false || $last->answered() || ClosingMessage::is($last->lastContactBody) || ! $this->period->contains($last->startedAt)) {
            return;
        }

        $this->unanswered[$id] = [
            'since' => $last->startedAt->toIso8601String(),
            'seconds' => $this->hours->secondsBetween($last->startedAt, $this->period->until),
        ];
    }

    private function readQuotesAndSales(Timeline $timeline): void
    {
        $id = $timeline->conversationId;
        $lastQuote = null;
        $lastContactAfterQuote = null;
        $saleAfterQuote = false;

        foreach ($timeline->messages as $message) {
            if (QuoteMessage::is($message)) {
                $lastQuote = $message;
                $lastContactAfterQuote = null;
                $saleAfterQuote = false;

                continue;
            }

            if (SaleSignal::is($message)) {
                $saleAfterQuote = true;

                if ($this->period->contains($message->at)) {
                    $this->sales[$id] ??= ['at' => $message->at->toIso8601String()];
                }
            }

            if ($message->author === 'contact' && $lastQuote !== null) {
                $lastContactAfterQuote = $message->at;
            }
        }

        if ($lastQuote === null || ! $this->period->contains($lastQuote->at)) {
            return;
        }

        $this->quoted++;

        if ($saleAfterQuote) {
            return;
        }

        $silentSince = $lastContactAfterQuote ?? $lastQuote->at;

        if ($silentSince->diffInDays($this->period->until) >= $this->stalledDays) {
            $this->stalledQuotes[$id] = [
                'quotedAt' => $lastQuote->at->toIso8601String(),
                'silentSince' => $silentSince->toIso8601String(),
            ];
        }
    }

    public function medianResponse(): ?float
    {
        return Median::of($this->responseSeconds);
    }

    /** Quanto das respostas saiu em até uma hora útil. */
    public function answeredWithinHourShare(): ?float
    {
        if ($this->responseSeconds === []) {
            return null;
        }

        return count(array_filter($this->responseSeconds, fn (int $s) => $s <= 3600)) / count($this->responseSeconds);
    }
}
