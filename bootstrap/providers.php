<?php

use App\Domains\Accounts\AccountsServiceProvider;
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
];
