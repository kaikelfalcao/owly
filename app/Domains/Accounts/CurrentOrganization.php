<?php

namespace App\Domains\Accounts;

use App\Domains\Accounts\Data\WorkingCalendar;
use App\Domains\Accounts\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;

/**
 * A empresa de quem está usando agora. Toda consulta de negócio filtra por
 * ela; os outros domínios pedem o id aqui e não olham o usuário.
 *
 * Na requisição, a empresa vem do usuário logado. Em job, comando e teste,
 * quem abre o contexto é `runAs`. Sem nenhum dos dois, as consultas dos
 * modelos com BelongsToOrganization falham em vez de devolver tudo.
 */
class CurrentOrganization
{
    private ?int $forced = null;

    private ?Organization $forcedModel = null;

    private bool $across = false;

    public function __construct(private readonly Auth $auth) {}

    public function id(): int
    {
        return $this->forced ?? $this->get()->id;
    }

    /** Se há empresa no contexto, sem lançar exceção. */
    public function has(): bool
    {
        return $this->forced !== null || $this->user()?->organization_id !== null;
    }

    /** Dentro de `across`: as consultas não filtram por empresa. */
    public function isAcross(): bool
    {
        return $this->across;
    }

    /**
     * Roda o trecho como se a empresa fosse esta. Usado por jobs, comandos e
     * testes; o contexto anterior volta no fim, mesmo com exceção.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(int $organizationId, Closure $callback): mixed
    {
        [$forced, $model, $across] = [$this->forced, $this->forcedModel, $this->across];
        [$hadContext, $context] = [Context::has('organization_id'), Context::get('organization_id')];

        $this->forced = $organizationId;
        $this->forcedModel = null;
        $this->across = false;
        Context::add('organization_id', $organizationId);

        try {
            return $callback();
        } finally {
            [$this->forced, $this->forcedModel, $this->across] = [$forced, $model, $across];
            $hadContext ? Context::add('organization_id', $context) : Context::forget('organization_id');
        }
    }

    /**
     * Roda o trecho vendo todas as empresas. Só para comandos e administração;
     * o motivo vai para o log.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function across(string $reason, Closure $callback): mixed
    {
        $previous = $this->across;
        $this->across = true;
        Log::info('consulta entre empresas', ['reason' => $reason]);

        try {
            return $callback();
        } finally {
            $this->across = $previous;
        }
    }

    /**
     * Para serviços que recebem o id da empresa: com outra empresa no
     * contexto, é erro de programação; sem contexto, abre um só para a chamada.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function ensure(int $organizationId, Closure $callback): mixed
    {
        if ($this->across) {
            return $callback();
        }

        if (! $this->has()) {
            return $this->runAs($organizationId, $callback);
        }

        if ($this->id() !== $organizationId) {
            throw new LogicException('A empresa pedida não é a do contexto.');
        }

        return $callback();
    }

    public function calendar(): WorkingCalendar
    {
        $organization = $this->get();

        return new WorkingCalendar(
            organizationId: $organization->id,
            timezone: $organization->timezone,
            hours: $organization->hours(),
            holidays: array_column($organization->holidays ?? [], 'date'),
            nationalHolidays: $organization->national_holidays ?? true,
            configured: $organization->business_hours !== null,
        );
    }

    public function get(): Organization
    {
        if ($this->forced !== null) {
            return $this->forcedModel ??= Organization::findOrFail($this->forced);
        }

        return $this->user()?->organization
            ?? throw new RuntimeException('Nenhuma empresa no contexto: a rota precisa de login, e job ou comando precisa de runAs.');
    }

    private function user(): ?User
    {
        /** @var User|null */
        return $this->auth->guard()->user();
    }
}
