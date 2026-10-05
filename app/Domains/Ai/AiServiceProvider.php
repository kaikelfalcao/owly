<?php

namespace App\Domains\Ai;

use App\Domains\Ai\Services\AiPanel;
use App\Domains\Conversations\Contracts\ConversationPanels;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function boot(ConversationPanels $panels): void
    {
        $panels->register('ai', fn (int $organizationId, int $conversationId) => $this->app->make(AiPanel::class)->props($organizationId, $conversationId));
    }
}
