<?php

namespace App\Domains\Accounts\Concerns;

use App\Domains\Accounts\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Acrescenta `organization_id = empresa do contexto` em toda consulta. Sem
 * empresa no contexto, `CurrentOrganization::id()` lança exceção: a consulta
 * falha em vez de devolver dados de todas as empresas.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentOrganization::class);

        if ($current->isAcross()) {
            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $current->id());
    }
}
