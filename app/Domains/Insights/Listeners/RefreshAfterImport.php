<?php

namespace App\Domains\Insights\Listeners;

use App\Domains\Imports\Events\ImportFinished;
use App\Domains\Insights\Jobs\RefreshInsights;

class RefreshAfterImport
{
    public function handle(ImportFinished $event): void
    {
        RefreshInsights::dispatch($event->organizationId, $event->importId);
    }
}
