<?php

namespace App\Domains\Insights;

use App\Domains\Conversations\Contracts\ConversationPanels;
use App\Domains\Conversations\Events\ConversationsRecut;
use App\Domains\Imports\Events\ImportFinished;
use App\Domains\Insights\Console\RefreshCommand;
use App\Domains\Insights\Listeners\RefreshAfterImport;
use App\Domains\Insights\Listeners\RefreshRecutContacts;
use App\Domains\Insights\Services\OpportunityPanel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InsightsServiceProvider extends ServiceProvider
{
    public function boot(ConversationPanels $panels): void
    {
        Event::listen(ImportFinished::class, RefreshAfterImport::class);
        Event::listen(ConversationsRecut::class, RefreshRecutContacts::class);

        $panels->register('opportunities', fn (int $organizationId, int $conversationId) => $this->app->make(OpportunityPanel::class)->props($organizationId, $conversationId));

        if ($this->app->runningInConsole()) {
            $this->commands([RefreshCommand::class]);
        }
    }
}
