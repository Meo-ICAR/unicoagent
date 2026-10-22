<?php

namespace App\Models\Scopes;

use App\Support\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Un utente del pannello legato a una company vede solo i record di quella company. */
class CompanyScope implements Scope
{
    public function __construct(private ?string $column = null) {}

    public function apply(Builder $builder, Model $model): void
    {
        $companyId = TenantUser::companyId();

        if ($companyId !== null) {
            $builder->where($model->qualifyColumn($this->column ?? $model::companyColumn()), $companyId);
        }
    }
}
