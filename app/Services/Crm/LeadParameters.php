<?php

namespace App\Services\Crm;

use App\Models\Fornitore;
use App\Models\LoanRequest;
use App\Models\QuoteBandBound;
use App\Models\QuoteEmploymentMap;
use Carbon\Carbon;
use Unico\Core\Support\ResidenceResolver;

/**
 * Parametri del servizio di caricamento lead (specifica 1.6) dai dati della pratica perfezionata.
 * Non include la Passkey né il file: nessun documento va al CRM. I valori non ricavabili si omettono.
 */
final class LeadParameters
{
    /** @return array<string,string> */
    public static function build(LoanRequest $loan, array $personal): array
    {
        $answers = $loan->answers ?? [];
        $band = QuoteBandBound::where('dimension', 'importo')->where('code', $answers['importo'] ?? '')->first();
        $residence = ResidenceResolver::resolve((string) ($personal['residenza'] ?? ''));

        return array_filter([
            'cognome' => $personal['cognome'] ?? null,
            'nome' => $personal['nome'] ?? null,
            'data_nascita' => self::birthDate($personal['data_nascita'] ?? null),
            'tipologia' => self::tipologiaScelta($answers) ?? QuoteEmploymentMap::resolve($answers)?->lead_tipologia,
            'importo_richiesto' => $band ? number_format($band->high, 2, ',', '') : null,
            'residenza_citta' => $residence['city'],
            'residenza_provincia' => $residence['province'],
            'cellulare' => $personal['telefono'] ?? null,
            'email' => $personal['email'] ?? null,
            'fonte' => (string) config('finanziamento.lead.fonte', 'unicoagent'),
            'annotazioni' => self::notes($loan, $answers, $band),
            'codice_fiscale' => $personal['codice_fiscale'] ?? null,
            'codice_fiscale_agente' => Fornitore::findByWhatsApp((string) $loan->agent_wa_number)?->cf,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** Il tipo di rapporto scelto per la cessione, come lo vuole la tabella 3.1 del servizio lead («Privato SPA» → «Privato»). */
    private static function tipologiaScelta(array $answers): ?string
    {
        $code = ($answers['tipo_rapporto'] ?? null) === 'altro' ? ($answers['tipo_rapporto_altro'] ?? null) : ($answers['tipo_rapporto'] ?? null);

        return match ($code) {
            null => null,
            'privato_spa' => 'Privato',
            'pensionato_inpdap' => 'Pensionato altri enti',
            default => self::lead($code),
        };
    }

    private static function lead(string $code): string
    {
        return [
            'statale' => 'Statale', 'pubblico' => 'Pubblico', 'parapubblico' => 'Parapubblico', 'privato_altra_forma' => 'Privato altra forma',
            'privato_small_business' => 'Privato small business', 'pensionato_inps' => 'Pensionato INPS', 'pensionato_altri_enti' => 'Pensionato altri enti',
            'medico' => 'Medico', 'postale' => 'Postale', 'ferroviere' => 'Ferroviere',
        ][$code] ?? ucfirst(str_replace('_', ' ', $code));
    }

    /** «gg/mm/aaaa» → «mm-gg-aaaa»; null se non è una data valida. */
    private static function birthDate(?string $date): ?string
    {
        if (! $date || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->format('m-d-Y');
    }

    private static function notes(LoanRequest $loan, array $answers, ?QuoteBandBound $band): string
    {
        $producer = Fornitore::findByWhatsApp((string) $loan->agent_wa_number)?->sigla;

        return implode(' · ', array_filter([
            "Pratica {$loan->code}",
            LoanRequest::productLabels($loan->company_id)[$loan->product] ?? $loan->product,
            preg_match('/^m(\d+)$/', (string) ($answers['durata'] ?? ''), $m) ? "{$m[1]} mesi" : null,
            $band?->label ? "importo {$band->label}" : null,
            $producer ? "produttore {$producer}" : null,
        ]));
    }
}
