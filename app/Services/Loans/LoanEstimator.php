<?php

namespace App\Services\Loans;

use App\Models\LoanRequest;

/** Calcola l'importo minimo e massimo ottenibile per una richiesta. */
interface LoanEstimator
{
    /**
     * Importi in euro. `details` c'è solo per il servizio Mediafacile: realizzo medio ponderato, massimo e minimo con la banca
     * (le offerte in errore sono scartate).
     *
     * @return array{min: int, max: int, details?: array{count: int, average: float, weighted: bool, max: array{amount: float, bank: ?string}, min: array{amount: float, bank: ?string}}}
     */
    public function estimate(LoanRequest $loan): array;
}
