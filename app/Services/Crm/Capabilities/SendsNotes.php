<?php

namespace App\Services\Crm\Capabilities;

use App\Models\LoanRequest;

/** Il CRM riceve note per l'istruttore sulla pratica già consegnata. */
interface SendsNotes
{
    /**
     * @param  string  $author  agente | cliente | assistente | istruttore
     * @param  string|null  $originId  identificativo che rende l'invio idempotente
     * @return int codice HTTP: 200 = consegnata
     */
    public function sendNote(LoanRequest $loan, string $text, string $author, ?string $originId = null): int;
}
