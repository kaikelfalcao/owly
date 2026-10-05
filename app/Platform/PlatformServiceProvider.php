<?php

namespace App\Platform;

use App\Platform\Audit\Listeners\RecordAuthActivity;
use App\Platform\Telemetry\Telemetry;
use App\Platform\Telemetry\TelemetryFactory;
use App\Platform\Telemetry\TraceQueries;
use App\Platform\Telemetry\TraceQueueJobs;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Liga o que todo domínio usa sem saber: logs com contexto, rastros,
 * métricas e auditoria. Ver docs/observabilidade.md.
 */
class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Telemetry::class, fn ($app) => TelemetryFactory::fromConfig(
            $app['config']->get('owly.telemetry'),
        ));
        $this->app->singleton(TraceQueueJobs::class);
    }

    public function boot(): void
    {
        // Toda linha de log daqui em diante sabe quem estava logado.
        Event::listen(Authenticated::class, function (Authenticated $event): void {
            Context::add('user_id', $event->user->getAuthIdentifier());
        });

        Event::subscribe(RecordAuthActivity::class);

        if ($this->app->make(Telemetry::class)->enabled()) {
            Event::listen(QueryExecuted::class, TraceQueries::class);
            Event::subscribe(TraceQueueJobs::class);
        }
    }
}
