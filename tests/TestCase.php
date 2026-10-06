<?php

namespace Tests;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Accounts\Models\Organization;
use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roda o trecho com a empresa no contexto, como um job faria. Para preparar
     * ou conferir dados fora de uma requisição.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function inOrganization(Organization|int $organization, Closure $callback): mixed
    {
        $id = $organization instanceof Organization ? $organization->id : $organization;

        return app(CurrentOrganization::class)->runAs($id, $callback);
    }

    /**
     * Confere dados de todas as empresas de uma vez (ex.: que cada uma ficou com
     * a sua cópia).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function acrossOrganizations(Closure $callback): mixed
    {
        return app(CurrentOrganization::class)->across('teste', $callback);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
