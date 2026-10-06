<?php

namespace App\Domains\Ai;

use App\Domains\Ai\Listeners\ReassignQuestions;
use App\Domains\Ai\Services\AiPanel;
use App\Domains\Conversations\Contracts\ConversationPanels;
use App\Domains\Conversations\Events\ConversationsRecut;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function boot(ConversationPanels $panels): void
    {
        Event::listen(ConversationsRecut::class, ReassignQuestions::class);

        $panels->register('ai', fn (int $organizationId, int $conversationId) => $this->app->make(AiPanel::class)->props($organizationId, $conversationId));
    }
}
