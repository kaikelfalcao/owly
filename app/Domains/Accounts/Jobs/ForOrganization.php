<?php

namespace App\Domains\Accounts\Jobs;

use App\Domains\Accounts\CurrentOrganization;
use Closure;

/**
 * Middleware de job: roda o `handle` com a empresa do job no contexto. O job
 * guarda o id da empresa (nunca o modelo, que seria buscado antes deste
 * middleware rodar, já sem contexto).
 */
class ForOrganization
{
    public function __construct(private readonly int $organizationId) {}

    public function handle(object $job, Closure $next): mixed
    {
        return app(CurrentOrganization::class)->runAs($this->organizationId, fn () => $next($job));
    }
}
