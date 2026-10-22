<?php

namespace App\Services\Loans\Mediafacile;

use App\Services\Loans\QuoteUnavailable;
use Unico\Core\Clients\MediafacileSimulationService;
use Unico\Core\Clients\SimulationUnavailable;

/**
 * Chiamata al servizio di simulazione Mediafacile, che sta in unico-core (MediafacileSimulationService):
 * qui gli errori diventano QuoteUnavailable, che la conversazione sa gestire con il ripiego sull'email.
 */
class MediafacileClient
{
    /** @param  array<string,string>  $params  senza Passkey */
    public function request(string $url, string $passkey, array $params): string
    {
        try {
            return (new MediafacileSimulationService)->request($url, $passkey, $params);
        } catch (SimulationUnavailable $e) {
            throw new QuoteUnavailable($e->getMessage(), 0, $e);
        }
    }

    /** @return list<array{valid: bool, erogato: float}> una voce per ogni elemento che contiene Importo_erogato */
    public static function parse(string $raw): array
    {
        try {
            return MediafacileSimulationService::parse($raw);
        } catch (SimulationUnavailable $e) {
            throw new QuoteUnavailable($e->getMessage(), 0, $e);
        }
    }

    /** @return list<array{valid: bool, erogato: float, bank: ?string, commission: ?float}> */
    public static function offers(string $raw): array
    {
        try {
            return MediafacileSimulationService::offers($raw);
        } catch (SimulationUnavailable $e) {
            throw new QuoteUnavailable($e->getMessage(), 0, $e);
        }
    }
}
