<?php

namespace App\Platform\Notifications;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * O que o sino mostra: quantos avisos não lidos e os últimos dez.
 */
class NotificationFeed
{
    /**
     * @return array{unread: int, items: list<array<string, mixed>>}
     */
    public function for(Authenticatable $user): array
    {
        /** @var User $user */
        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(10)->get()
                ->map(fn (DatabaseNotification $n) => [
                    'id' => $n->id,
                    'kind' => $n->data['kind'] ?? 'info',
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? null,
                    'url' => $n->data['url'] ?? null,
                    'read' => $n->read_at !== null,
                    'at' => $n->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
