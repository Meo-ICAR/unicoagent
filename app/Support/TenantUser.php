<?php

namespace App\Support;

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * La company dell'utente del pannello che sta navigando: chi non ha una company è super admin e vede tutto.
 * Senza utente (webhook WhatsApp, code, console) non c'è nessuna restrizione.
 */
class TenantUser
{
    /** @var array<int|string, int|null> */
    private static array $companyByUser = [];

    /** La company a cui è limitato l'utente; null per il super admin e per chi non è autenticato. */
    public static function companyId(): ?int
    {
        $guard = Auth::guard();

        if ($guard->hasUser()) {
            $companyId = $guard->user()->company_id;

            return $companyId === null ? null : (int) $companyId;
        }

        // Utente non ancora caricato (la sua lettura non deve passare dal filtro): lo si legge dalla sessione, senza modelli.
        $id = $guard instanceof SessionGuard && app()->bound('session') && session()->isStarted() ? session($guard->getName()) : null;
        if ($id === null) {
            return null;
        }

        $companyId = self::$companyByUser[$id] ??= DB::table('users')->where('id', $id)->value('company_id');

        return $companyId === null ? null : (int) $companyId;
    }

    /** Un utente autenticato senza company vede i dati di tutte le aziende. */
    public static function isSuperAdmin(): bool
    {
        return Auth::check() && self::companyId() === null;
    }

    public static function isRestricted(): bool
    {
        return self::companyId() !== null;
    }
}
