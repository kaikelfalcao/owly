<?php

namespace App\Platform\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Uma linha da auditoria. Gravada só por {@see Audit}; nunca muda nem some.
 *
 * @property int $id
 * @property ?int $organization_id
 * @property ?int $user_id
 * @property string $action
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?string $ip
 * @property ?string $request_id
 * @property ?array<string, scalar|null> $meta
 * @property CarbonImmutable $created_at
 */
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A auditoria não pode ser alterada.'));
        static::deleting(fn () => throw new LogicException('A auditoria não pode ser apagada.'));
    }
}
