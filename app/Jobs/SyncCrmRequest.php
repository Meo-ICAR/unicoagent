<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\LoanRequest;
use App\Services\Crm\Capabilities\SendsNotes;
use App\Services\Crm\Capabilities\UpdatesRequest;
use App\Services\Crm\CrmRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Porta al CRM ciò che cambia dopo la consegna: i consensi (informativa verificata o rifiutata) o una nota per l'istruttore.
 * Solo per pratiche già consegnate e CRM che lo sanno fare (UpdatesRequest, SendsNotes). Errori di rete e 5xx ritentano;
 * un rifiuto (4xx) è definitivo.
 */
class SyncCrmRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * @param  string  $kind  consenso | nota
     * @param  array{testo?: string, autore?: string, id_origine?: string|null}  $note
     */
    public function __construct(public int $loanId, public string $kind, public array $note = []) {}

    public static function consent(LoanRequest $loan): void
    {
        if ($loan->crm_lead_id !== null && ! $loan->is_test) {
            self::dispatch($loan->id, 'consenso');
        }
    }

    public static function note(LoanRequest $loan, string $text, string $author, ?string $originId = null): void
    {
        if ($loan->crm_lead_id !== null && ! $loan->is_test) {
            self::dispatch($loan->id, 'nota', ['testo' => $text, 'autore' => $author, 'id_origine' => $originId]);
        }
    }

    public function handle(CrmRegistry $registry): void
    {
        $loan = LoanRequest::find($this->loanId);

        if (! $loan || $loan->is_test || $loan->crm_lead_id === null) {
            return;
        }

        $company = Company::forWhatsApp($loan->agent_wa_number);
        $driver = $company?->crmDriver();
        $capability = $this->kind === 'nota' ? SendsNotes::class : UpdatesRequest::class;

        if ($company === null || $driver === null || ! $registry->supports($driver, $capability)) {
            return;
        }

        $gateway = $registry->make($driver, $company);

        $status = $this->kind === 'nota'
            ? $gateway->sendNote($loan, (string) ($this->note['testo'] ?? ''), (string) ($this->note['autore'] ?? 'agente'), $this->note['id_origine'] ?? null)
            : $gateway->updateRequest($loan, ['consenso' => [
                'privacy_ricevuta_il' => $loan->privacy_received_at?->toIso8601String(),
                'privacy_verificata_il' => $loan->privacy_verified_at?->toIso8601String(),
                'contatto_diretto' => (bool) $loan->direct_contact,
            ]]);

        if ($status === 0 || $status >= 500 || in_array($status, [408, 425, 429], true)) {
            // Nessun dato personale nel messaggio: solo il codice.
            throw new RuntimeException("Aggiornamento del CRM non riuscito (codice {$status}).");
        }
    }
}
