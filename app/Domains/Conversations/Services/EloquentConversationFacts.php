<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Conversations\Data\ConversationSummary;
use App\Domains\Conversations\Data\History;
use App\Domains\Conversations\Data\MessageFact;
use App\Domains\Conversations\Data\Timeline;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Conversations\Models\Seller;
use Carbon\CarbonImmutable;
use Closure;

class EloquentConversationFacts implements ConversationFacts
{
    private const COLUMNS = ['id', 'conversation_id', 'sent_at', 'direction', 'author', 'seller_id', 'body', 'media_type', 'event'];

    public function __construct(private readonly CurrentOrganization $organization) {}

    public function latestMessageAt(int $organizationId): ?CarbonImmutable
    {
        $at = $this->organization->ensure($organizationId, fn () => Conversation::where('organization_id', $organizationId)->max('last_message_at'));

        return $at ? CarbonImmutable::parse($at, 'UTC') : null;
    }

    public function timelines(int $organizationId, ?CarbonImmutable $since): iterable
    {
        // Gerador: cada consulta abre o contexto da empresa por conta própria,
        // porque quem consome pode iterar depois de o contexto ter fechado.
        $within = fn (Closure $query) => $this->organization->ensure($organizationId, $query);

        $sellers = $within(fn () => Seller::where('organization_id', $organizationId)->pluck('name', 'id')->all());

        $conversations = $within(fn () => Conversation::where('organization_id', $organizationId)
            ->when($since, fn ($q) => $q->where('last_message_at', '>=', $since))
            ->orderBy('id')
            ->get(['id', 'contact_id', 'status', 'opened_by'])
            ->keyBy('id'));

        // Em blocos, para não carregar todas as mensagens da empresa de uma vez.
        foreach ($conversations->keys()->chunk(200) as $chunk) {
            $messages = $within(fn () => Message::where('organization_id', $organizationId)
                ->whereIn('conversation_id', $chunk->all())
                ->orderBy('conversation_id')
                ->orderBy('sent_at')
                ->orderBy('id')
                ->get(self::COLUMNS)
                ->groupBy('conversation_id'));

            foreach ($chunk as $id) {
                $conversation = $conversations[$id];

                yield new Timeline(
                    conversationId: $id,
                    messages: ($messages[$id] ?? collect())->map(fn (Message $m) => self::fact($m, $sellers))->values()->all(),
                    contactId: $conversation->contact_id,
                    status: $conversation->status,
                    openedBy: $conversation->opened_by,
                );
            }
        }
    }

    public function histories(int $organizationId, array $contactIds): iterable
    {
        $within = fn (Closure $query) => $this->organization->ensure($organizationId, $query);
        $sellers = $within(fn () => Seller::where('organization_id', $organizationId)->pluck('name', 'id')->all());

        foreach (array_chunk($contactIds, 100) as $chunk) {
            $messages = $within(fn () => Message::where('organization_id', $organizationId)
                ->whereIn('contact_id', $chunk)
                ->orderBy('contact_id')
                ->orderBy('sent_at')
                ->orderBy('id')
                ->get([...self::COLUMNS, 'contact_id'])
                ->groupBy('contact_id'));

            foreach ($chunk as $contactId) {
                yield new History($contactId, ($messages[$contactId] ?? collect())->map(fn (Message $m) => self::fact($m, $sellers))->values()->all());
            }
        }
    }

    public function contactsWithMessages(int $organizationId, ?int $importId = null): array
    {
        return $this->organization->ensure($organizationId, fn (): array => Message::where('organization_id', $organizationId)
            ->when($importId !== null, fn ($query) => $query->where('import_id', $importId))
            ->distinct()
            ->orderBy('contact_id')
            ->pluck('contact_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    public function conversationsOfMessages(int $organizationId, array $messageIds): array
    {
        return $this->organization->ensure($organizationId, function () use ($organizationId, $messageIds): array {
            $conversations = [];

            foreach (array_chunk($messageIds, 500) as $chunk) {
                $conversations += Message::where('organization_id', $organizationId)->whereIn('id', $chunk)->pluck('conversation_id', 'id')->all();
            }

            return $conversations;
        });
    }

    public function summaries(int $organizationId, array $conversationIds): array
    {
        return $this->organization->ensure($organizationId, fn () => Conversation::where('organization_id', $organizationId)
            ->whereIn('id', $conversationIds)
            ->with('contact')
            ->get())
            ->mapWithKeys(fn (Conversation $c) => [$c->id => new ConversationSummary(
                id: $c->id,
                contact: $c->contact->displayName(),
                phone: $c->contact->phone ? Contact::formatPhone($c->contact->phone) : null,
                lastMessageAt: $c->last_message_at?->toIso8601String(),
                contactId: $c->contact_id,
                status: $c->status,
            )])
            ->all();
    }

    /**
     * @param  array<int, string>  $sellers
     */
    private static function fact(Message $m, array $sellers): MessageFact
    {
        return new MessageFact(
            id: $m->id,
            at: CarbonImmutable::instance($m->sent_at)->utc(),
            direction: $m->direction,
            author: $m->author,
            seller: $m->seller_id ? ($sellers[$m->seller_id] ?? null) : null,
            body: $m->body,
            mediaType: $m->media_type,
            event: $m->event,
            conversationId: $m->conversation_id,
            sellerId: $m->seller_id,
        );
    }
}
