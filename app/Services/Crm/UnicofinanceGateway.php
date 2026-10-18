<?php

namespace App\Services\Crm;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\LoanRequest;
use App\Services\Crm\Capabilities\FillsForms;
use App\Services\Crm\Capabilities\ProvidesTemplates;
use App\Services\Crm\Capabilities\RequestsSignature;
use App\Services\Crm\Capabilities\SendsDocuments;
use App\Services\Crm\Capabilities\SendsNotes;
use App\Services\Crm\Capabilities\UpdatesRequest;
use App\Services\Crm\Unicofinance\UnicofinanceClient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Unico\Core\Pdf\ModulePrinter;
use Unico\Core\Pdf\PdfFormException;

/**
 * Consegna la richiesta a unicofinance (API /api/agente/v1): prima cliente e pratica (submit, sincrono), poi i documenti
 * (sendDocuments, da un job in coda). Gli invii sono idempotenti per codice della richiesta: ripeterli non duplica nulla.
 * Nei log solo il tipo di errore e i codici: mai dati personali.
 */
class UnicofinanceGateway implements CrmGateway, FillsForms, ProvidesTemplates, RequestsSignature, SendsDocuments, SendsNotes, UpdatesRequest
{
    private UnicofinanceClient $client;

    public function __construct(private Company $company)
    {
        $this->client = new UnicofinanceClient($company);
    }

    public function submit(LoanRequest $loan, array $personal): int
    {
        if (! $this->client->isConfigured()) {
            Log::warning('unicofinance: indirizzo, token o segreto mancanti', ['loan' => $loan->code]);

            return 0;
        }

        $payload = SubmissionPayload::build($loan, $personal);

        if (blank($payload['cliente']['codice_fiscale'] ?? null) || blank($payload['agente']['partita_iva'] ?? null)) {
            Log::warning('unicofinance: codice fiscale del cliente o partita IVA dell\'agente mancanti', ['loan' => $loan->code]);

            return 422;
        }

        try {
            $response = $this->client->json('POST', '/api/agente/v1/richieste', $payload);
        } catch (ConnectionException) {
            Log::error('unicofinance: servizio non raggiungibile', ['loan' => $loan->code]);

            return 0;
        }

        if (! $response->successful()) {
            Log::warning('unicofinance: richiesta rifiutata', ['loan' => $loan->code, 'status' => $response->status(), 'codice' => $response->json('codice')]);

            return $response->status() ?: 502;
        }

        $loan->update(['crm_lead_id' => $response->json('codice_pratica')]);

        return 200;
    }

    /** Consegna i file non ancora inviati (esclusi quelli rifiutati); i già consegnati si saltano. */
    public function sendDocuments(LoanRequest $loan): int
    {
        if (! $this->client->isConfigured()) {
            return 0;
        }

        $disk = Storage::disk('local');
        $worst = 200;

        $pending = $loan->attachments()->with('praticaDocument')->whereNull('crm_sent_at')->orderBy('id')->get();

        foreach ($pending as $attachment) {
            if ($attachment->praticaDocument?->status === 'rejected' || ! $disk->exists($attachment->path)) {
                continue;
            }

            $status = $this->sendOne($loan, $attachment, $disk->get($attachment->path));

            if ($status === 200) {
                $attachment->forceFill(['crm_sent_at' => now()])->save();
            } else {
                $worst = $status;
            }
        }

        return $worst;
    }

    /**
     * Aggiorna la pratica già consegnata (`PATCH …/richieste/{codice}`): i consensi sempre, cliente e pratica solo finché in
     * unicofinance è da perfezionare (altrimenti 409, che non va ritentato).
     */
    public function updateRequest(LoanRequest $loan, array $changes): int
    {
        if ($loan->crm_lead_id === null) {
            return 0;
        }

        if (! $this->client->isConfigured()) {
            // Configurazione mancante: non è un errore di rete, ritentare non serve.
            Log::warning('unicofinance: indirizzo, token o segreto mancanti', ['loan' => $loan->code]);

            return 422;
        }

        $response = $this->call('PATCH', $this->base($loan), $loan, $changes);

        return $response === null ? 0 : ($response->successful() ? 200 : $response->status());
    }

    /** Nota per l'istruttore (`POST …/richieste/{codice}/note`), idempotente per `$originId`. */
    public function sendNote(LoanRequest $loan, string $text, string $author, ?string $originId = null): int
    {
        if ($loan->crm_lead_id === null) {
            return 0;
        }

        if (! $this->client->isConfigured()) {
            // Configurazione mancante: non è un errore di rete, ritentare non serve.
            Log::warning('unicofinance: indirizzo, token o segreto mancanti', ['loan' => $loan->code]);

            return 422;
        }

        $response = $this->call('POST', $this->base($loan).'/note', $loan, array_filter([
            'testo' => $text,
            'autore' => $author,
            'inviata_il' => now()->toIso8601String(),
            'id_origine' => $originId,
        ], fn ($value): bool => $value !== null));

        return $response === null ? 0 : ($response->successful() ? 200 : $response->status());
    }

    /**
     * Moduli che unicofinance mette a disposizione per questa pratica. Il «codice» è l'id del modulo in unicofinance.
     *
     * @return list<array{code: string, name: string}>
     */
    public function templates(LoanRequest $loan): array
    {
        $response = $this->call('GET', $this->base($loan).'/moduli', $loan);

        return $response?->successful()
            ? collect($response->json('moduli', []))->map(fn (array $m): array => ['code' => (string) $m['id'], 'name' => (string) $m['nome']])->values()->all()
            : [];
    }

    public function downloadTemplate(LoanRequest $loan, string $code): ?string
    {
        $response = $this->call('GET', $this->base($loan)."/moduli/{$code}/template", $loan);

        return $response?->successful() ? $response->body() : null;
    }

    /** Il modulo (id in unicofinance) compilato da unico-core con i dati della pratica, pronto da stampare. */
    public function fillForm(LoanRequest $loan, string $template): ?string
    {
        try {
            return app(ModulePrinter::class)->printByReference($loan->crm_lead_id ?: $loan->code, $template);
        } catch (PdfFormException $exception) {
            Log::warning('stampa modulo non disponibile', ['loan' => $loan->code, 'errore' => $exception::class]);

            return null;
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * Compila il modulo ($document = codice del modulo) e chiede la firma con OTP al cliente e all'agente.
     * Il «reference» restituito è l'id del documento in unicofinance: serve a signatureStatus().
     */
    public function requestSignature(LoanRequest $loan, string $document): array
    {
        $id = $this->fill($loan, $document);

        if ($id === null) {
            return ['status' => 'errore', 'reference' => null];
        }

        $response = $this->call('POST', $this->base($loan)."/documenti/{$id}/firma", $loan, []);

        return $response?->successful()
            ? ['status' => $this->signatureState($response->json('stato')), 'reference' => (string) $id]
            : ['status' => 'errore', 'reference' => null];
    }

    public function signatureStatus(LoanRequest $loan, string $reference): array
    {
        $response = $this->call('GET', $this->base($loan)."/documenti/{$reference}/firma", $loan);

        return $response?->successful()
            ? ['status' => $this->signatureState($response->json('stato')), 'reference' => $reference]
            : ['status' => 'errore', 'reference' => $reference];
    }

    private function base(LoanRequest $loan): string
    {
        return "/api/agente/v1/richieste/{$loan->code}";
    }

    private function fill(LoanRequest $loan, string $module): ?string
    {
        $response = $this->call('POST', $this->base($loan)."/moduli/{$module}/compilato", $loan, []);

        return $response?->successful() ? (string) $response->json('id') : null;
    }

    /** Chiamata firmata; null se il servizio non è configurato o non risponde. Nei log solo codici. */
    private function call(string $method, string $path, LoanRequest $loan, ?array $body = null): ?Response
    {
        if (! $this->client->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client->json($method, $path, $body);
        } catch (ConnectionException) {
            Log::error('unicofinance: servizio non raggiungibile', ['loan' => $loan->code]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('unicofinance: chiamata rifiutata', ['loan' => $loan->code, 'status' => $response->status(), 'codice' => $response->json('codice')]);
        }

        return $response;
    }

    private function signatureState(?string $state): string
    {
        return match ($state) {
            'pending', 'sent' => 'inviata',
            'signed' => 'firmata',
            'declined' => 'rifiutata',
            'expired' => 'scaduta',
            default => 'errore',
        };
    }

    private function sendOne(LoanRequest $loan, Attachment $attachment, string $contents): int
    {
        $fields = array_filter([
            'tipo' => $attachment->praticaDocument?->code ?? $attachment->kind,
            'id_origine' => (string) $attachment->id,
            'analisi' => json_encode($this->analysis($attachment)),
        ]);

        try {
            $response = $this->client->upload(
                "/api/agente/v1/richieste/{$loan->code}/documenti",
                $fields,
                "{$attachment->kind}-{$attachment->id}.".(pathinfo($attachment->path, PATHINFO_EXTENSION) ?: 'bin'),
                $contents,
            );
        } catch (ConnectionException) {
            Log::error('unicofinance: servizio non raggiungibile (documento)', ['loan' => $loan->code, 'attachment' => $attachment->id]);

            return 0;
        }

        if (! $response->successful()) {
            Log::warning('unicofinance: documento rifiutato', ['loan' => $loan->code, 'attachment' => $attachment->id, 'status' => $response->status(), 'codice' => $response->json('codice')]);

            return $response->status() ?: 502;
        }

        return 200;
    }

    /**
     * Esito dell'analisi già fatta qui, per non rifarla a monte. Di default solo esito, tipo e discrepanze: i dati letti dal
     * documento si mandano soltanto se l'azienda sceglie `analysis = full`.
     *
     * @return array<string,mixed>
     */
    private function analysis(Attachment $attachment): array
    {
        $analysis = (array) $attachment->analysis;

        return array_filter([
            'esito' => $attachment->status,
            'tipo' => $attachment->kind,
            'discrepanze' => $analysis['discrepancies'] ?? null,
            'campi' => $this->client->config('analysis') === 'full' ? ($analysis['fields'] ?? null) : null,
            'modello' => $attachment->ai_model,
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }
}
