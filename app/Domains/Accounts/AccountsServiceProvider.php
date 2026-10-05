<?php

namespace App\Domains\Accounts;

use App\Domains\Accounts\Console\CreateOwner;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
    }

    public function boot(): void
    {
        // Logs, rastros e auditoria passam a saber de qual empresa é a ação.
        Event::listen([Authenticated::class, Login::class], function (Authenticated|Login $event): void {
            Context::add('organization_id', $event->user->organization_id ?? null);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->user !== null) {
                Context::add('organization_id', $event->user->organization_id ?? null);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([CreateOwner::class]);
        }
    }
}
