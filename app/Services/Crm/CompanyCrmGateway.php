<?php

namespace App\Services\Crm;

use App\Models\Company;
use App\Models\LoanRequest;
use Illuminate\Support\Facades\Log;

/**
 * Il CrmGateway dell'applicazione: sceglie il driver in base all'azienda della pratica (una sola, un CRM per azienda).
 * Il driver scelto dall'azienda vince su `finanziamento.crm.driver`; solo senza driver, con `simulated` (sviluppo e test), risponde la simulazione.
 */
class CompanyCrmGateway implements CrmGateway
{
    public function __construct(private CrmRegistry $registry) {}

    public function submit(LoanRequest $loan, array $personal): int
    {
        $company = Company::forWhatsApp($loan->agent_wa_number);
        $driver = $company?->crmDriver();

        if ($driver === null && config('finanziamento.crm.driver') === 'simulated') {
            return (new SimulatedCrmGateway)->submit($loan, $personal);
        }

        if ($company === null || $driver === null) {
            Log::warning('Invio CRM: nessun driver per l\'azienda', ['loan' => $loan->code]);

            return 0;
        }

        if (! $this->registry->has($driver)) {
            Log::error('Invio CRM: driver sconosciuto', ['loan' => $loan->code, 'driver' => $driver]);

            return 0;
        }

        return $this->registry->make($driver, $company)->submit($loan, $personal);
    }
}
