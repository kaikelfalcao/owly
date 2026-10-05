<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Leituras das telas de Conversas, sempre dentro da empresa.
 */
class ConversationQueries
{
    private const MEDIA = [
        'image' => 'Imagem',
        'sticker' => 'Figurinha',
        'video' => 'Vídeo',
        'audio' => 'Áudio',
        'ptt' => 'Áudio',
    ];

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function list(int $organizationId, ?string $search): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->where('conversations.organization_id', $organizationId)
            ->with('contact')
            ->orderByDesc('last_message_at');

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $digits = preg_replace('/\D/', '', $term);

            $query->whereHas('contact', function ($contact) use ($term, $digits) {
                $contact->where('name', 'like', '%'.$term.'%');

                if ($digits !== '') {
                    $contact->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        $page = $query->paginate(30)->withQueryString();
        $last = $this->lastMessages($page->getCollection()->pluck('id')->all());

        return $page->through(fn (Conversation $conversation) => [
            'id' => $conversation->id,
            'contact' => $conversation->contact->displayName(),
            'phone' => $conversation->contact->phone ? Contact::formatPhone($conversation->contact->phone) : null,
            'messagesCount' => $conversation->messages_count,
            'lastMessageAt' => $conversation->last_message_at?->toIso8601String(),
            'lastMessage' => isset($last[$conversation->id]) ? $this->preview($last[$conversation->id]) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $organizationId, int $conversationId): array
    {
        $conversation = Conversation::where('organization_id', $organizationId)
            ->with('contact')
            ->findOrFail($conversationId);

        $messages = $conversation->messages()
            ->with('seller')
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get();

        $sellers = $messages->whereNotNull('seller_id')
            ->groupBy('seller_id')
            ->map(fn ($group) => ['name' => $group->first()->seller->name, 'messages' => $group->count()])
            ->sortByDesc('messages')
            ->values();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'contact' => $conversation->contact->displayName(),
                'phone' => $conversation->contact->phone ? Contact::formatPhone($conversation->contact->phone) : null,
                'firstMessageAt' => $conversation->first_message_at?->toIso8601String(),
                'lastMessageAt' => $conversation->last_message_at?->toIso8601String(),
                'messagesCount' => $conversation->messages_count,
                'fromContact' => $messages->where('direction', 'in')->count(),
                'sellers' => $sellers,
            ],
            'messages' => $messages->map(fn (Message $message) => [
                'id' => $message->id,
                'sentAt' => $message->sent_at->toIso8601String(),
                'direction' => $message->direction,
                'author' => $message->author,
                'seller' => $message->seller?->name,
                'body' => $message->body,
                'mediaType' => $message->media_type,
                'mediaName' => $message->media_name,
                'event' => $message->event,
                'quoted' => $message->quoted,
            ])->values(),
        ];
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, Message>
     */
    private function lastMessages(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $latest = Message::query()
            ->select('conversation_id', DB::raw('max(id) as id'))
            ->whereIn('conversation_id', $conversationIds)
            ->groupBy('conversation_id');

        return Message::query()
            ->joinSub($latest, 'latest', 'latest.id', '=', 'messages.id')
            ->select('messages.*')
            ->with('seller')
            ->get()
            ->keyBy('conversation_id')
            ->all();
    }

    /**
     * @return array{text: string, who: string}
     */
    private function preview(Message $message): array
    {
        $text = match (true) {
            $message->event === 'missed_call' => 'Ligação perdida',
            $message->event === 'deleted' => 'Mensagem apagada',
            $message->body !== null => mb_strimwidth(str_replace("\n", ' ', $message->body), 0, 90, '…'),
            $message->media_type !== null => self::MEDIA[$message->media_type] ?? 'Arquivo',
            default => '',
        };

        $who = match ($message->author) {
            'contact' => 'Cliente',
            'seller' => $message->seller?->name ?? 'Equipe',
            'bot' => 'Resposta automática',
            default => '',
        };

        return ['text' => $text, 'who' => $who];
    }
}
