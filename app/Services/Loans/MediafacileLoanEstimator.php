<?php

namespace App\Services\Loans;

use App\Models\Company;
use App\Models\LoanRequest;
use App\Models\QuoteContractType;
use App\Models\QuoteSimulation;
use App\Services\Loans\Mediafacile\MediafacileClient;
use App\Services\Loans\Mediafacile\ScenarioBuilder;
use Unico\Core\Clients\MediafacileSimulationService;

/**
 * Importo erogato minimo e massimo dal servizio di simulazione Mediafacile: due chiamate,
 * una con le condizioni migliori (massimo) e una con le peggiori (minimo). Registra ogni simulazione.
 * Solo per i prodotti del catalogo (quinto, personale): gli altri usano ancora la simulazione casuale.
 */
class MediafacileLoanEstimator implements LoanEstimator
{
    public function __construct(private ScenarioBuilder $scenarios, private MediafacileClient $client, private RandomLoanEstimator $random) {}

    public function estimate(LoanRequest $loan): array
    {
        // I prodotti che il servizio non gestisce (finalizzato, mutuo...) restano alla simulazione, in attesa di altri driver.
        if (! QuoteContractType::where('product', $loan->product)->exists()) {
            return $this->random->estimate($loan);
        }

        $company = Company::forWhatsApp($loan->agent_wa_number);
        if (! $company?->hasQuoteCrm() || blank($company->preventivatore_passkey)) {
            throw new QuoteUnavailable('Preventivatore non configurato.');
        }

        // Due simulazioni (migliore e peggiore): massimo, minimo e media si prendono da tutte le offerte valide di entrambe.
        $allOffers = [];
        foreach ($this->scenarios->build($loan) as $name => $params) {
            $raw = $this->client->request($company->url_preventivatore, $company->preventivatore_passkey, $params);
            $offers = MediafacileClient::offers($raw);
            $valid = collect($offers)->where('valid', true);

            QuoteSimulation::create([
                'loan_request_id' => $loan->id, 'scenario' => $name, 'request' => $params, 'response' => $raw,
                'offers_count' => count($offers), 'erogato_min' => $valid->min('erogato'), 'erogato_max' => $valid->max('erogato'),
            ]);

            if ($valid->isEmpty()) {
                throw new QuoteUnavailable("Nessuna offerta valida nello scenario {$name}.");
            }
            $allOffers = [...$allOffers, ...$offers];
        }

        $details = MediafacileSimulationService::summary($allOffers)
            ?? throw new QuoteUnavailable('Nessuna offerta valida.');

        return ['min' => (int) round($details['min']['amount']), 'max' => (int) round($details['max']['amount']), 'details' => $details];
    }
}
