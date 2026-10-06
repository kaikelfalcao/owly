<?php

namespace App\Domains\Accounts\Concerns;

use App\Domains\Accounts\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Modelo de negócio: toda consulta filtra pela empresa do contexto e todo
 * registro novo nasce nela (docs/arquitetura.md, "Empresa desde o primeiro dia").
 *
 * `Model::insert()` em lote não passa pelo `creating`: quem usa monta as
 * linhas com `organization_id`.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            $current = app(CurrentOrganization::class);

            if ($current->isAcross() || ! $current->has()) {
                if ($model->getAttribute('organization_id') === null) {
                    throw new LogicException('Registro sem empresa: abra o contexto com runAs ou informe organization_id.');
                }

                return;
            }

            $model->setAttribute('organization_id', $model->getAttribute('organization_id') ?? $current->id());

            if ((int) $model->getAttribute('organization_id') !== $current->id()) {
                throw new LogicException('Registro de outra empresa dentro do contexto atual.');
            }
        });
    }
}
