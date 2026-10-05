<?php

namespace App\Platform\Audit;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * As consultas da auditoria só leem e incluem. Os eventos `updating` e
 * `deleting` do model barram um registro por vez; isto barra o lote
 * (`AuditEntry::where(...)->delete()`), que passa por fora dos eventos.
 *
 * @extends Builder<AuditEntry>
 */
class AppendOnlyBuilder extends Builder
{
    public function update(array $values): never
    {
        throw new LogicException('A auditoria não pode ser alterada.');
    }

    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw new LogicException('A auditoria não pode ser alterada.');
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('A auditoria não pode ser alterada.');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('A auditoria não pode ser alterada.');
    }

    public function delete(): never
    {
        throw new LogicException('A auditoria não pode ser apagada.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('A auditoria não pode ser apagada.');
    }

    public function truncate(): never
    {
        throw new LogicException('A auditoria não pode ser apagada.');
    }
}
