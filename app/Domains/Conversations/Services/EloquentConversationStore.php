<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\IncomingMessage;
use App\Domains\Conversations\Data\StoreResult;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Conversations\Models\Seller;
use Illuminate\Support\Facades\DB;

class EloquentConversationStore implements ConversationStore
{
    public function __construct(private readonly CurrentOrganization $organization) {}

    public function store(int $organizationId, string $source, IncomingConversation $incoming): StoreResult
    {
        return $this->organization->ensure($organizationId, fn () => DB::transaction(function () use ($organizationId, $source, $incoming) {
            $contact = Contact::firstOrCreate(
                ['organization_id' => $organizationId, 'external_key' => $incoming->contactKey],
                ['phone' => $incoming->contactPhone, 'name' => $incoming->contactName],
            );

            // Um zip mais novo pode trazer o nome que faltava.
            if ($contact->name === null && $incoming->contactName !== null) {
                $contact->update(['name' => $incoming->contactName]);
            }

            $conversation = Conversation::firstOrCreate(
                ['organization_id' => $organizationId, 'contact_id' => $contact->id],
            );

            $known = Message::where('conversation_id', $conversation->id)
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
                    'conversation_id' => $conversation->id,
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

            foreach (array_chunk($rows, 500) as $chunk) {
                Message::insert($chunk);
            }

            $this->refreshTotals($conversation);

            return new StoreResult(
                conversationId: $conversation->id,
                created: $conversation->wasRecentlyCreated,
                newMessages: count($rows),
                knownMessages: count($incoming->messages) - count($rows),
            );
        }));
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

    private function refreshTotals(Conversation $conversation): void
    {
        $totals = Message::where('conversation_id', $conversation->id)
            ->selectRaw('count(*) as total, min(sent_at) as first_at, max(sent_at) as last_at')
            ->first();

        $conversation->update([
            'messages_count' => (int) $totals->total,
            'first_message_at' => $totals->first_at,
            'last_message_at' => $totals->last_at,
        ]);
    }
}
