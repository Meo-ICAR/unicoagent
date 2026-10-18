<?php

namespace App\Services\Crm\Capabilities;

use App\Models\LoanRequest;

/** Il CRM accetta aggiornamenti di una pratica già consegnata (consensi, dati corretti). */
interface UpdatesRequest
{
    /**
     * @param  array<string,mixed>  $changes  sezioni del formato unico da cambiare (cliente, pratica, consenso)
     * @return int codice HTTP: 200 = aggiornata
     */
    public function updateRequest(LoanRequest $loan, array $changes): int;
}
