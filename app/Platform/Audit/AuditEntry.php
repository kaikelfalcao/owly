<?php

namespace App\Platform\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Uma linha da auditoria. Gravada só por {@see Audit}; nunca muda nem some,
 * nem um registro por vez nem em lote ({@see AppendOnlyBuilder}).
 *
 * @property int $id
 * @property ?int $organization_id
 * @property ?int $user_id
 * @property string $action
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?string $channel
 * @property ?string $ip
 * @property ?string $user_agent
 * @property ?string $request_id
 * @property ?array<string, scalar|null> $meta
 * @property ?array<string, array{from?: scalar|null, to?: scalar|null, hidden?: bool}> $changes
 * @property CarbonImmutable $created_at
 */
#[UseEloquentBuilder(AppendOnlyBuilder::class)]
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'changes' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A auditoria não pode ser alterada.'));
        static::deleting(fn () => throw new LogicException('A auditoria não pode ser apagada.'));
    }
}
