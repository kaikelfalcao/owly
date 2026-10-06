<?php

namespace App\Domains\Conversations;

use App\Domains\Conversations\Console\Recut;
use App\Domains\Conversations\Contracts\ConversationFacts;
use App\Domains\Conversations\Contracts\ConversationIntegrity;
use App\Domains\Conversations\Contracts\ConversationPanels;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Contracts\ConversationTranscript;
use App\Domains\Conversations\Services\EloquentConversationFacts;
use App\Domains\Conversations\Services\EloquentConversationIntegrity;
use App\Domains\Conversations\Services\EloquentConversationStore;
use App\Domains\Conversations\Services\EloquentConversationTranscript;
use App\Domains\Conversations\Services\PanelRegistry;
use Illuminate\Support\ServiceProvider;

class ConversationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConversationStore::class, EloquentConversationStore::class);
        $this->app->bind(ConversationTranscript::class, EloquentConversationTranscript::class);
        $this->app->singleton(ConversationPanels::class, PanelRegistry::class);
        $this->app->bind(ConversationFacts::class, EloquentConversationFacts::class);
        $this->app->bind(ConversationIntegrity::class, EloquentConversationIntegrity::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([Recut::class]);
        }
    }
}
