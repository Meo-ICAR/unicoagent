<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\TenantUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multitenant: il record appartiene a una company. Un utente del pannello legato a una company vede e crea solo
 * record della sua company; chi non ha una company è super admin. Senza utente (webhook, code, console) nessun filtro.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        if (! static::isScopedByCompany()) {
            return;
        }

        static::addGlobalScope(new CompanyScope);

        static::saving(function (Model $model): void {
            $companyId = TenantUser::companyId();

            if ($companyId !== null) {
                $model->setAttribute(static::companyColumn(), $companyId);
            }
        });
    }

    /** I modelli che si raggiungono solo dal record padre (nodi, opzioni, salti) non hanno un filtro proprio. */
    protected static function isScopedByCompany(): bool
    {
        return true;
    }

    /** Colonna con la company (le tabelle che hanno già un `company_id` con altro significato usano un altro nome). */
    public static function companyColumn(): string
    {
        return 'company_id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, static::companyColumn());
    }
}
