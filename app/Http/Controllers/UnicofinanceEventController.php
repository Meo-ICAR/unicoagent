<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LoanRequest;
use App\Models\PraticaDocument;
use App\Services\Documents\AgentNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Avvisi da unicofinance: l'istruttoria ha respinto un documento (`documento.da_rifare`) o ne chiede uno nuovo
 * (`documento.richiesto`). Il documento della pratica passa a «Rifiutato» o «Integrazione richiesta» e l'agente lo sa
 * su WhatsApp, come quando decide l'operatore qui. Firma: HMAC-SHA256 di «timestamp.POST.percorso.sha256-del-corpo»
 * con il segreto (`crm_config.secret`) di un'azienda che usa il driver `unicofinance`. Ogni `event_id` vale una volta.
 */
class UnicofinanceEventController extends Controller
{
    /** Secondi di scarto ammessi tra il timestamp firmato e l'ora del server. */
    private const TOLERANCE = 300;

    private const EVENTS = ['documento.da_rifare' => 'rejected', 'documento.richiesto' => 'integrazione_richiesta'];

    public function __invoke(Request $request, AgentNotifier $notifier): JsonResponse
    {
        $company = $this->signer($request);

        if ($company === null) {
            return response()->json(['codice' => 'firma_non_valida'], 401);
        }

        $request->validate(['event_id' => ['required', 'string', 'max:191'], 'evento' => ['required', 'string']]);
        $status = self::EVENTS[$request->input('evento')] ?? null;

        // Un evento che qui non serve si conferma senza chiedere i dati dei documenti: unicofinance non deve ritentarlo.
        if ($status === null) {
            return response()->json(['esito' => 'ignorato']);
        }

        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:191'],
            'riferimento' => ['required', 'string'],
            'documento' => ['required', 'array'],
            'documento.tipo' => ['nullable', 'string'],
            'documento.alias' => ['nullable', 'array'],
            'documento.alias.*' => ['string'],
            'documento.nome' => ['nullable', 'string'],
            'documento.motivo' => ['nullable', 'string'],
        ]);

        $loan = LoanRequest::where('code', $data['riferimento'])->first();

        if ($loan === null || $loan->is_test || $this->companyOf($loan) !== $company->id) {
            return response()->json(['codice' => 'pratica_sconosciuta'], 404);
        }

        $key = "unicofinance-evento:{$company->id}:".hash('sha256', $data['event_id']);

        if (! Cache::add($key, true, now()->addDays(30))) {
            return response()->json(['esito' => 'duplicate']);
        }

        try {
            $slot = DB::transaction(fn (): PraticaDocument => $this->apply($loan, $status, $data['documento']));
        } catch (Throwable $e) {
            // Senza esito l'evento va ritentato: unicofinance lo rimanda.
            Cache::forget($key);

            throw $e;
        }

        $notifier->notify($slot);

        return response()->json(['esito' => 'ok', 'documento' => $slot->code]);
    }

    /** @param  array<string, mixed>  $document */
    private function apply(LoanRequest $loan, string $status, array $document): PraticaDocument
    {
        $codes = array_values(array_filter([...Arr::wrap($document['alias'] ?? []), $document['tipo'] ?? null]));
        $name = $document['nome'] ?? $codes[0] ?? 'Documento';

        // Prima gli alias (i codici di qui), poi il tipo di unicofinance.
        $slot = null;
        foreach ($codes as $code) {
            $slot ??= $loan->praticaDocuments()->where('code', $code)->first();
        }
        $slot ??= $loan->integrativeSlot($codes[0] ?? null, $name);

        $note = $status === 'rejected'
            ? 'Respinto in istruttoria (unicofinance)'.(filled($document['motivo'] ?? null) ? ': '.$document['motivo'] : '.')
            : 'Richiesto dall\'istruttoria (unicofinance).';

        // Nessuna nota di ritorno a unicofinance: l'avviso viene da lì.
        $status === 'rejected' ? $slot->reject($note, null) : $slot->requestIntegration($note, null, tellCrm: false);

        return $slot->fresh();
    }

    /** L'azienda della pratica: quella salvata sulla pratica, altrimenti quella dell'agente. */
    private function companyOf(LoanRequest $loan): ?int
    {
        return $loan->company_id !== null ? (int) $loan->company_id : Company::forWhatsApp($loan->agent_wa_number)?->id;
    }

    /** L'azienda il cui segreto firma la richiesta, se il timestamp è recente. */
    private function signer(Request $request): ?Company
    {
        $timestamp = (string) $request->header('X-Timestamp');
        $signature = (string) $request->header('X-Signature');

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::TOLERANCE || $signature === '') {
            return null;
        }

        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?: '/');
        $payload = implode('.', [$timestamp, 'POST', $path, hash('sha256', $request->getContent())]);

        return Company::where('crm_driver', 'unicofinance')->get()->first(function (Company $company) use ($payload, $signature): bool {
            $secret = (string) ($company->crm_config['secret'] ?? '');

            return $secret !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
        });
    }
}
