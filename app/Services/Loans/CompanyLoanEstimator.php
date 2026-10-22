<?php

namespace App\Services\Loans;

use App\Models\Company;
use App\Models\LoanRequest;

/**
 * Sceglie la stima per l'azienda dell'agente: il servizio di simulazione Mediafacile se l'azienda ha l'URL del
 * preventivatore e la passkey (come Races) o se QUOTE_DRIVER=mediafacile per tutti; altrimenti la simulazione di base.
 */
class CompanyLoanEstimator implements LoanEstimator
{
    public function __construct(private MediafacileLoanEstimator $mediafacile, private RandomLoanEstimator $random) {}

    public function estimate(LoanRequest $loan): array
    {
        return $this->usesMediafacile($loan) ? $this->mediafacile->estimate($loan) : $this->random->estimate($loan);
    }

    private function usesMediafacile(LoanRequest $loan): bool
    {
        if (config('finanziamento.quote.driver') === 'mediafacile') {
            return true;
        }

        $company = Company::forWhatsApp($loan->agent_wa_number);

        return $company?->hasQuoteCrm() === true && filled($company->preventivatore_passkey);
    }
}
