<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Conversations\Data\ConversationSummary;
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

        $ids = $within(fn () => Conversation::where('organization_id', $organizationId)
            ->when($since, fn ($q) => $q->where('last_message_at', '>=', $since))
            ->orderBy('id')
            ->pluck('id'));

        // Em blocos, para não carregar todas as mensagens da empresa de uma vez.
        foreach ($ids->chunk(200) as $chunk) {
            $messages = $within(fn () => Message::where('organization_id', $organizationId)
                ->whereIn('conversation_id', $chunk->all())
                ->orderBy('conversation_id')
                ->orderBy('sent_at')
                ->orderBy('id')
                ->get(['id', 'conversation_id', 'sent_at', 'direction', 'author', 'seller_id', 'body', 'media_type', 'event'])
                ->groupBy('conversation_id'));

            foreach ($chunk as $id) {
                yield new Timeline($id, ($messages[$id] ?? collect())->map(fn (Message $m) => new MessageFact(
                    id: $m->id,
                    at: CarbonImmutable::instance($m->sent_at)->utc(),
                    direction: $m->direction,
                    author: $m->author,
                    seller: $m->seller_id ? ($sellers[$m->seller_id] ?? null) : null,
                    body: $m->body,
                    mediaType: $m->media_type,
                    event: $m->event,
                ))->values()->all());
            }
        }
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
            )])
            ->all();
    }
}
