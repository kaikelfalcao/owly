<?php

namespace App\Domains\Accounts;

use App\Domains\Accounts\Data\WorkingCalendar;
use App\Domains\Accounts\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as Auth;
use RuntimeException;

/**
 * A empresa de quem está usando agora. Toda consulta de negócio filtra por
 * ela; os outros domínios pedem o id aqui e não olham o usuário.
 */
class CurrentOrganization
{
    public function __construct(private readonly Auth $auth) {}

    public function id(): int
    {
        return $this->get()->id;
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
        /** @var User|null $user */
        $user = $this->auth->guard()->user();

        return $user?->organization
            ?? throw new RuntimeException('Nenhuma empresa no contexto: a rota precisa de login.');
    }
}
