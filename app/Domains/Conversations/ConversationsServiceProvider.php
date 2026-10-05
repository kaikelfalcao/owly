<?php

namespace App\Domains\Conversations;

use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Services\EloquentConversationStore;
use Illuminate\Support\ServiceProvider;

class ConversationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConversationStore::class, EloquentConversationStore::class);
    }
}
