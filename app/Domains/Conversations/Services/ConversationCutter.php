<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Conversations\Rules\ConversationCut;
use App\Domains\Conversations\Rules\CutMessage;
use Carbon\CarbonImmutable;

/**
 * Aplica o corte aos atendimentos de um cliente: calcula (plan) e grava
 * (apply). Quem chama abre a transação: tudo de um cliente muda junto ou
 * nada muda (docs/arquitetura.md, "Atendimentos").
 *
 * Mensagem nunca é apagada nem reinserida: só o conversation_id muda. O
 * atendimento que começa na mesma mensagem fica com o mesmo id.
 */
class ConversationCutter
{
    private ?ConversationCut $cut = null;

    private ?int $cutFor = null;

    public function __construct(private readonly CurrentOrganization $organization) {}

    /**
     * @param  list<array<string, mixed>>  $newRows  mensagens novas do cliente, prontas para gravar sem conversation_id
     * @param  bool  $full  refaz o histórico inteiro (owly:recut); senão, só o necessário
     */
    public function plan(Contact $contact, array $newRows, CarbonImmutable $reference, bool $full = false): CutPlan
    {
        $conversations = Conversation::where('contact_id', $contact->id)
            ->orderBy('first_message_at')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if (! $full && $newRows === []) {
            return new CutPlan($contact, [], [], [], [], [], [], [], $reference);
        }

        // Caso normal: tudo o que chegou é depois do começo do último
        // atendimento. O corte não tem memória antes de um começo, então
        // basta recalcular a partir dele. Arquivo com mensagens mais antigas
        // refaz o histórico inteiro.
        $last = $conversations->last();
        $oldestNew = $newRows === [] ? null : min(array_map(fn (array $row) => $row['sent_at'], $newRows));
        $partial = ! $full && $last !== null && $oldestNew >= $last->first_message_at;
        $scope = $partial ? [$last->id => $last] : $conversations->all();

        $stored = Message::query()
            ->when($partial, fn ($query) => $query->where('conversation_id', $last->id), fn ($query) => $query->where('contact_id', $contact->id))
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get(['id', 'conversation_id', 'sent_at', 'author', 'direction', 'event', 'seller_id']);

        $messages = [];

        foreach ($stored as $message) {
            $messages[] = [$message->sent_at->getTimestamp(), 0, $message->id, new CutMessage(
                key: $message->id,
                at: CarbonImmutable::instance($message->sent_at)->utc(),
                author: $message->author,
                direction: $message->direction,
                event: $message->event,
                sellerId: $message->seller_id,
            )];
        }

        // As novas entram depois das gravadas no mesmo segundo, na ordem do
        // arquivo: é a ordem que o id vai ter quando forem gravadas.
        foreach ($newRows as $index => $row) {
            $messages[] = [$row['sent_at']->getTimestamp(), 1, $index, new CutMessage(
                key: 'new:'.$index,
                at: CarbonImmutable::instance($row['sent_at'])->utc(),
                author: $row['author'],
                direction: $row['direction'],
                event: $row['event'],
                sellerId: $row['seller_id'],
            )];
        }

        usort($messages, fn (array $a, array $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        $segments = $this->cut()->segments(array_column($messages, 3));

        // Onde cada mensagem gravada está hoje, e qual abre cada atendimento.
        $current = [];
        $openings = [];

        foreach ($stored as $message) {
            $current[$message->id] = $message->conversation_id;
            $openings[$message->conversation_id] ??= $message->id;
        }

        $byOpening = array_flip($openings);
        $targets = [];
        $used = [];

        // O atendimento que começa na mesma mensagem fica com o id.
        foreach ($segments as $index => $segment) {
            $opening = $segment->opening();

            if (is_int($opening) && isset($byOpening[$opening]) && isset($scope[$byOpening[$opening]])) {
                $targets[$index] = $byOpening[$opening];
                $used[$byOpening[$opening]] = true;
            }
        }

        // Os outros reaproveitam um atendimento que tinha mensagens deles
        // (arquivo antigo que mudou o começo), para os ids mudarem o mínimo.
        foreach ($segments as $index => $segment) {
            if (array_key_exists($index, $targets)) {
                continue;
            }

            $targets[$index] = null;

            foreach ($segment->keys as $key) {
                if (is_int($key) && isset($scope[$current[$key]]) && ! isset($used[$current[$key]])) {
                    $targets[$index] = $current[$key];
                    $used[$current[$key]] = true;

                    break;
                }
            }
        }

        $moves = [];

        foreach ($segments as $index => $segment) {
            foreach ($segment->keys as $key) {
                if (is_int($key) && $current[$key] !== $targets[$index]) {
                    $moves[$key] = [$current[$key], $index];
                }
            }
        }

        $leftovers = array_values(array_diff(array_keys($scope), array_keys($used)));

        return new CutPlan(
            contact: $contact,
            segments: $segments,
            targets: $targets,
            scope: $scope,
            leftovers: $leftovers,
            moves: $moves,
            newRows: $newRows,
            openings: array_intersect_key($openings, array_flip($leftovers)),
            reference: $reference,
        );
    }

    public function apply(CutPlan $plan): CutOutcome
    {
        $ids = [];
        $updates = [];
        $created = 0;

        foreach ($plan->segments as $index => $segment) {
            $attributes = $this->attributes($plan, $index);

            if ($plan->targets[$index] === null) {
                $ids[$index] = Conversation::create(['organization_id' => $plan->contact->organization_id, 'contact_id' => $plan->contact->id, ...$attributes])->id;
                $created++;
            } else {
                $ids[$index] = $plan->targets[$index];
                $updates[$index] = $attributes;
            }
        }

        $moving = [];

        foreach ($plan->moves as $messageId => [, $index]) {
            $moving[$ids[$index]][] = $messageId;
        }

        foreach ($moving as $conversationId => $messageIds) {
            foreach (array_chunk($messageIds, 500) as $chunk) {
                Message::whereIn('id', $chunk)->update(['conversation_id' => $conversationId]);
            }
        }

        $segmentOfNew = [];

        foreach ($plan->segments as $index => $segment) {
            foreach ($segment->keys as $key) {
                if (is_string($key)) {
                    $segmentOfNew[(int) substr($key, 4)] = $index;
                }
            }
        }

        // Na ordem do arquivo: o id segue a ordem em que o corte as viu.
        $rows = [];

        foreach ($plan->newRows as $position => $row) {
            $rows[] = ['conversation_id' => $ids[$segmentOfNew[$position]], ...$row];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Message::insert($chunk);
        }

        // Já sem mensagens: a chave sem cascata deixa apagar.
        if ($plan->leftovers !== []) {
            Conversation::whereIn('id', $plan->leftovers)->delete();
        }

        foreach ($updates as $index => $attributes) {
            $plan->scope[$ids[$index]]->update($attributes);
        }

        $replaced = [];

        foreach ($plan->openings as $conversationId => $messageId) {
            foreach ($plan->segments as $index => $segment) {
                if (in_array($messageId, $segment->keys, true)) {
                    $replaced[$conversationId] = $ids[$index];
                }
            }
        }

        return new CutOutcome(
            created: $created,
            changed: $plan->changed(),
            replaced: $replaced,
            conversationIds: array_values($ids),
            movedMessages: count($plan->moves),
        );
    }

    /**
     * Quantas linhas o plano mudaria: atendimentos novos, apagados ou com
     * outro valor, e mensagens que trocam de atendimento. Zero quer dizer
     * que o corte gravado já é o corte da regra.
     */
    public function differences(CutPlan $plan): int
    {
        $differences = count($plan->moves) + count($plan->leftovers);

        foreach ($plan->segments as $index => $segment) {
            $target = $plan->targets[$index];

            if ($target === null) {
                $differences++;

                continue;
            }

            $conversation = $plan->scope[$target];

            foreach ($this->attributes($plan, $index) as $key => $value) {
                $now = $conversation->{$key};

                if ($value instanceof CarbonImmutable ? $now?->getTimestamp() !== $value->getTimestamp() : $now !== $value) {
                    $differences++;

                    break;
                }
            }
        }

        return $differences;
    }

    /**
     * @return array{seller_id: int|null, opened_by: string, status: string, first_message_at: CarbonImmutable, last_message_at: CarbonImmutable, messages_count: int}
     */
    private function attributes(CutPlan $plan, int $index): array
    {
        $segment = $plan->segments[$index];
        // Só o último pode estar aberto: os outros terminaram quando o cliente
        // voltou depois de um dia útil inteiro.
        $closed = $index < count($plan->segments) - 1 || $this->cut()->isClosed($segment->lastPersonAt, $plan->reference);

        return [
            'seller_id' => $segment->sellerId(),
            'opened_by' => $segment->openedBy,
            'status' => $closed ? Conversation::CLOSED : Conversation::OPEN,
            'first_message_at' => $segment->firstAt,
            'last_message_at' => $segment->lastAt,
            'messages_count' => count($segment->keys),
        ];
    }

    private function cut(): ConversationCut
    {
        // Um corte por empresa: o calendário guarda os feriados já calculados.
        $organizationId = $this->organization->id();

        if ($this->cut === null || $this->cutFor !== $organizationId) {
            $this->cut = new ConversationCut($this->organization->calendar());
            $this->cutFor = $organizationId;
        }

        return $this->cut;
    }
}
