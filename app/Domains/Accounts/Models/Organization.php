<?php

namespace App\Domains\Accounts\Models;

use App\Models\User;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A empresa que usa a Owly. Todo dado de negócio pertence a uma.
 *
 * @property int $id
 * @property string $name
 * @property string $timezone
 * @property array<string, array{0: string, 1: string}|null>|null $business_hours
 * @property bool $national_holidays
 * @property list<array{date: string, name: string}>|null $holidays
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'timezone', 'business_hours', 'national_holidays', 'holidays'])]
#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Horário comercial de quem ainda não configurou: o de uma loja de rua. */
    public const DEFAULT_HOURS = [
        'mon' => ['08:00', '18:00'],
        'tue' => ['08:00', '18:00'],
        'wed' => ['08:00', '18:00'],
        'thu' => ['08:00', '18:00'],
        'fri' => ['08:00', '18:00'],
        'sat' => ['08:00', '12:00'],
        'sun' => null,
    ];

    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'national_holidays' => 'boolean',
            'holidays' => 'array',
        ];
    }

    /**
     * O horário de atendimento, dia a dia, já com o padrão.
     *
     * @return array<string, array{0: string, 1: string}|null>
     */
    public function hours(): array
    {
        return array_replace(self::DEFAULT_HOURS, $this->business_hours ?? []);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
