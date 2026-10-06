<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationIntegrity;
use App\Domains\Conversations\Data\IntegrityFacts;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EloquentConversationIntegrity implements ConversationIntegrity
{
    private const EXAMPLES = 5;

    public function __construct(private readonly CurrentOrganization $organization) {}

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

            return new IntegrityFacts(
                messages: count($keys),
                contacts: Contact::count(),
                conversations: $contactOf->count(),
                emptyConversations: Conversation::whereDoesntHave('messages')->count(),
                messagesHash: hash('sha256', implode("\n", $keys)),
            );
        });
    }

    public function problems(int $organizationId): array
    {
        return $this->organization->ensure($organizationId, function (): array {
            /** @var Collection<int, Conversation> $conversations */
            $conversations = Conversation::query()
                ->get(['id', 'contact_id', 'messages_count', 'first_message_at', 'last_message_at'])
                ->keyBy('id');

            // Antes da migração que liga a mensagem ao cliente, só dá para
            // conferir pela conversa.
            $withContact = Schema::hasColumn('messages', 'contact_id');
            $columns = ['id', 'conversation_id', 'external_id', 'sent_at', ...($withContact ? ['contact_id'] : [])];

            $found = ['outside' => [], 'contact' => [], 'repeated' => []];
            $seen = [];
            $totals = [];

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

            return array_values(array_filter([
                self::describe('Mensagens com conversa de outra empresa ou que não existe', $found['outside']),
                self::describe('Mensagens com cliente diferente do da conversa', $found['contact']),
                self::describe('Mensagens repetidas na mesma conversa', $found['repeated']),
                self::describe('Conversas sem mensagem', $empty),
                self::describe('Conversas com total, início ou fim diferente das mensagens', $wrongTotals),
            ]));
        });
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
