<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Conversations\Contracts\ConversationIntegrity;
use App\Domains\Conversations\Data\IntegrityFacts;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Conversations\Rules\ConversationCut;
use App\Domains\Conversations\Rules\CutMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EloquentConversationIntegrity implements ConversationIntegrity
{
    private const EXAMPLES = 5;

    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly ConversationCutter $cutter,
        private readonly ConversationFacts $facts,
    ) {}

    public function facts(int $organizationId): IntegrityFacts
    {
        return $this->organization->ensure($organizationId, function (): IntegrityFacts {
            // O cliente vem da conversa, e não de messages.contact_id: assim a
            // foto tirada antes da migração que criou a coluna vale depois.
            $contactOf = Conversation::pluck('contact_id', 'id');
            $keys = [];

            foreach (Message::query()->select(['id', 'conversation_id', 'external_id'])->lazyById(2000) as $message) {
                $keys[] = ($contactOf[$message->conversation_id] ?? '?').':'.$message->external_id;
            }

            sort($keys, SORT_STRING);

            $first = [];

            foreach (Conversation::orderBy('first_message_at')->orderBy('id')->get(['id', 'contact_id']) as $conversation) {
                $first[$conversation->contact_id] ??= $conversation->id;
            }

            ksort($first);

            return new IntegrityFacts(
                messages: count($keys),
                contacts: Contact::count(),
                conversations: $contactOf->count(),
                emptyConversations: Conversation::whereDoesntHave('messages')->count(),
                messagesHash: hash('sha256', implode("\n", $keys)),
                firstConversations: $first,
                openedByCompany: $this->cut() ? Conversation::where('opened_by', ConversationCut::COMPANY)->count() : null,
            );
        });
    }

    public function problems(int $organizationId): array
    {
        return $this->organization->ensure($organizationId, function () use ($organizationId): array {
            /** @var Collection<int, Conversation> $conversations */
            $conversations = Conversation::query()
                ->orderBy('first_message_at')
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            // Antes da migração que liga a mensagem ao cliente, só dá para
            // conferir pela conversa.
            $withContact = Schema::hasColumn('messages', 'contact_id');
            $columns = ['id', 'conversation_id', 'external_id', 'sent_at', 'author', 'direction', 'event', ...($withContact ? ['contact_id'] : [])];

            $found = ['outside' => [], 'contact' => [], 'repeated' => []];
            $seen = [];
            $totals = [];
            $openings = [];

            foreach (Message::query()->select($columns)->lazyById(2000) as $message) {
                $conversation = $conversations[$message->conversation_id] ?? null;

                if ($conversation === null) {
                    $found['outside'][] = $message->id;

                    continue;
                }

                if ($withContact && (int) $message->contact_id !== (int) $conversation->contact_id) {
                    $found['contact'][] = $message->id;
                }

                $key = $message->conversation_id.':'.$message->external_id;

                if (isset($seen[$key])) {
                    $found['repeated'][] = $message->id;
                }

                $seen[$key] = true;
                $at = $message->sent_at->getTimestamp();
                $total = $totals[$message->conversation_id] ?? ['count' => 0, 'first' => $at, 'last' => $at];
                $totals[$message->conversation_id] = [
                    'count' => $total['count'] + 1,
                    'first' => min($total['first'], $at),
                    'last' => max($total['last'], $at),
                ];

                // A mensagem que abre: a primeira por (sent_at, id).
                $opening = $openings[$message->conversation_id] ?? null;

                if ($opening === null || [$at, $message->id] < [$opening->at->getTimestamp(), $opening->key]) {
                    $openings[$message->conversation_id] = new CutMessage($message->id, $message->sent_at->toImmutable(), $message->author, $message->direction, $message->event);
                }
            }

            $empty = [];
            $wrongTotals = [];

            foreach ($conversations as $id => $conversation) {
                $total = $totals[$id] ?? null;

                if ($total === null) {
                    $empty[] = $id;
                } elseif ($conversation->messages_count !== $total['count']
                    || $conversation->first_message_at?->getTimestamp() !== $total['first']
                    || $conversation->last_message_at?->getTimestamp() !== $total['last']) {
                    $wrongTotals[] = $id;
                }
            }

            $problems = [
                self::describe('Mensagens com conversa de outra empresa ou que não existe', $found['outside']),
                self::describe('Mensagens com cliente diferente do da conversa', $found['contact']),
                self::describe('Mensagens repetidas na mesma conversa', $found['repeated']),
                self::describe('Conversas sem mensagem', $empty),
                self::describe('Conversas com total, início ou fim diferente das mensagens', $wrongTotals),
            ];

            // Depois que a conversa virou atendimento.
            if ($this->cut()) {
                [$overlapping, $wrongOpening] = $this->episodes($conversations, $openings);
                $problems[] = self::describe('Atendimentos que se sobrepõem a outro do mesmo cliente', $overlapping);
                $problems[] = self::describe('Atendimentos abertos por mensagem que não é do cliente (fora o primeiro do histórico)', $wrongOpening);
                $problems[] = self::describe('Clientes cujo corte mudaria se o owly:recut rodasse de novo', $this->notRecut($organizationId));
            }

            return array_values(array_filter($problems));
        });
    }

    /**
     * Por cliente, em ordem: o fim de um atendimento vem antes do começo do
     * seguinte, e só o primeiro pode começar pela empresa.
     *
     * @param  Collection<int, Conversation>  $conversations  em ordem de começo
     * @param  array<int, CutMessage>  $openings
     * @return array{0: list<int>, 1: list<int>}
     */
    private function episodes(Collection $conversations, array $openings): array
    {
        $overlapping = [];
        $wrongOpening = [];
        $previous = [];

        foreach ($conversations as $id => $conversation) {
            $before = $previous[$conversation->contact_id] ?? null;
            $opening = $openings[$id] ?? null;

            if ($before !== null && $before->last_message_at >= $conversation->first_message_at) {
                $overlapping[] = $id;
            }

            $byClient = $opening?->fromClient() ?? false;
            $expected = $byClient ? ConversationCut::CONTACT : ConversationCut::COMPANY;

            if ($opening !== null && (($before !== null && ! $byClient) || $conversation->opened_by !== $expected)) {
                $wrongOpening[] = $id;
            }

            $previous[$conversation->contact_id] = $conversation;
        }

        return [$overlapping, $wrongOpening];
    }

    /**
     * Clientes em que o corte gravado não é o corte da regra hoje.
     *
     * @return list<int>
     */
    private function notRecut(int $organizationId): array
    {
        $reference = $this->facts->latestMessageAt($organizationId);
        $contacts = [];

        if ($reference === null) {
            return [];
        }

        foreach (Contact::query()->lazyById(200) as $contact) {
            if ($this->cutter->differences($this->cutter->plan($contact, [], $reference, full: true)) > 0) {
                $contacts[] = $contact->id;
            }
        }

        return $contacts;
    }

    /** Se a conversa já virou atendimento (a migração do corte rodou). */
    private function cut(): bool
    {
        return Schema::hasColumn('conversations', 'opened_by');
    }

    /**
     * "Mensagens repetidas na mesma conversa: 3 (#12, #40, #77)".
     *
     * @param  list<int>  $ids
     */
    private static function describe(string $what, array $ids): ?string
    {
        if ($ids === []) {
            return null;
        }

        $examples = implode(', ', array_map(fn (int $id) => "#{$id}", array_slice($ids, 0, self::EXAMPLES)));

        return sprintf('%s: %d (%s%s)', $what, count($ids), $examples, count($ids) > self::EXAMPLES ? ', …' : '');
    }
}
