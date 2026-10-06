<?php

use App\Domains\Accounts\AccountsServiceProvider;
use App\Domains\Ai\AiServiceProvider;
use App\Domains\Conversations\ConversationsServiceProvider;
use App\Domains\Imports\ImportsServiceProvider;
use App\Domains\Insights\InsightsServiceProvider;
use App\Platform\PlatformServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    // Conta antes da Plataforma: a empresa entra no contexto antes de a
    // auditoria do login ser gravada.
    AccountsServiceProvider::class,
    PlatformServiceProvider::class,
    ConversationsServiceProvider::class,
    ImportsServiceProvider::class,
    AiServiceProvider::class,
    InsightsServiceProvider::class,
];
