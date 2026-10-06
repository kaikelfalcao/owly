<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\IncomingMessage;
use App\Domains\Conversations\Data\StoreResult;
use App\Domains\Conversations\Events\ConversationsRecut;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Conversations\Models\Seller;
use App\Domains\Conversations\Rules\ConversationCut;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EloquentConversationStore implements ConversationStore
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly ConversationCutter $cutter,
    ) {}

    public function store(int $organizationId, string $source, IncomingConversation $incoming, ?int $importId = null): StoreResult
    {
        return $this->organization->ensure($organizationId, fn () => DB::transaction(function () use ($organizationId, $source, $incoming, $importId) {
            $contact = Contact::firstOrCreate(
                ['organization_id' => $organizationId, 'external_key' => $incoming->contactKey],
                ['phone' => $incoming->contactPhone, 'name' => $incoming->contactName],
            );

            // Um zip mais novo pode trazer o nome que faltava.
            if ($contact->name === null && $incoming->contactName !== null) {
                $contact->update(['name' => $incoming->contactName]);
            }

            // A mensagem é do cliente, não do atendimento: quando o corte
            // refaz os atendimentos, a mesma mensagem continua reconhecida.
            $known = Message::where('contact_id', $contact->id)
                ->pluck('external_id')
                ->flip();

            $sellers = $this->sellerIds($organizationId, $incoming->messages);
            $now = now();
            $rows = [];

            foreach ($incoming->messages as $message) {
                if (isset($known[$message->externalId])) {
                    continue;
                }

                $known[$message->externalId] = true;
                $rows[] = [
                    'organization_id' => $organizationId,
                    'contact_id' => $contact->id,
                    'import_id' => $importId,
                    'source' => $source,
                    'external_id' => $message->externalId,
                    'sent_at' => $message->sentAt->utc(),
                    'direction' => $message->direction,
                    'author' => $message->author,
                    'seller_id' => $message->sellerName ? $sellers[$message->sellerName] : null,
                    'body' => $message->body,
                    'media_type' => $message->mediaType,
                    'media_name' => $message->mediaName,
                    'event' => $message->event,
                    'quoted' => $message->quoted,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $plan = $this->cutter->plan($contact, $rows, $this->reference($rows));
            $outcome = $this->cutter->apply($plan);

            if ($outcome->changed !== []) {
                ConversationsRecut::dispatch($organizationId, [$contact->id], $outcome->changed, $outcome->replaced);
            }

            return new StoreResult(
                contactId: $contact->id,
                contactCreated: $contact->wasRecentlyCreated,
                newMessages: count($rows),
                knownMessages: count($incoming->messages) - count($rows),
                conversationsCreated: $outcome->created,
                conversationsChanged: count($outcome->changed),
            );
        }));
    }

    public function closeIdle(int $organizationId, CarbonImmutable $reference): int
    {
        return $this->organization->ensure($organizationId, function () use ($reference): int {
            $cut = new ConversationCut($this->organization->calendar());
            $open = Conversation::where('status', Conversation::OPEN)->pluck('first_message_at', 'id');
            $closed = [];

            foreach ($open->keys()->chunk(500) as $chunk) {
                $lastPerson = Message::whereIn('conversation_id', $chunk->all())
                    ->where(fn ($query) => $query
                        ->whereIn('author', ['contact', 'seller'])
                        ->orWhere(fn ($call) => $call->where('author', 'system')->where('event', 'missed_call')->where('direction', IncomingMessage::IN)))
                    ->groupBy('conversation_id')
                    ->selectRaw('conversation_id, max(sent_at) as at')
                    ->pluck('at', 'conversation_id');

                foreach ($chunk as $id) {
                    // Sem mensagem de pessoa (só o robô), o relógio parte do começo.
                    $since = CarbonImmutable::parse($lastPerson[$id] ?? $open[$id], 'UTC');

                    if ($cut->isClosed($since, $reference)) {
                        $closed[] = $id;
                    }
                }
            }

            foreach (array_chunk($closed, 500) as $chunk) {
                Conversation::whereIn('id', $chunk)->update(['status' => Conversation::CLOSED]);
            }

            return count($closed);
        });
    }

    /**
     * O "hoje" do corte: a mensagem mais recente da empresa no Owly, contando
     * as que estão chegando.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function reference(array $rows): CarbonImmutable
    {
        $stored = Message::max('sent_at');
        $candidates = array_map(fn (array $row) => CarbonImmutable::instance($row['sent_at'])->utc(), $rows);

        if ($stored !== null) {
            $candidates[] = CarbonImmutable::parse($stored, 'UTC');
        }

        return $candidates === [] ? CarbonImmutable::now('UTC') : max($candidates);
    }

    /**
     * @param  list<IncomingMessage>  $messages
     * @return array<string, int>
     */
    private function sellerIds(int $organizationId, array $messages): array
    {
        $ids = [];

        foreach ($messages as $message) {
            if ($message->sellerName !== null && ! isset($ids[$message->sellerName])) {
                $ids[$message->sellerName] = Seller::firstOrCreate([
                    'organization_id' => $organizationId,
                    'name' => $message->sellerName,
                ])->id;
            }
        }

        return $ids;
    }
}
