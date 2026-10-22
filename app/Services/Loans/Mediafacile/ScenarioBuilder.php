<?php

namespace App\Services\Loans\Mediafacile;

use App\Models\Company;
use App\Models\Fornitore;
use App\Models\LoanRequest;
use App\Models\QuoteBandBound;
use App\Models\QuoteContractType;
use App\Models\QuoteDuration;
use App\Models\QuoteEmploymentMap;
use App\Models\QuoteEmploymentType;
use App\Services\Loans\QuoteUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Dalle risposte della richiesta ai parametri del servizio di simulazione, per due scenari:
 * `best` (età minima della fascia, anzianità di servizio massima) e `worst` (età massima, anzianità minima).
 * Il massimo e il minimo si prendono dalle due simulazioni.
 * Per cessione e delega include `Provvigione` (i punti da caricare) se l'agente li ha indicati. Non include la Passkey. Lancia QuoteUnavailable se la richiesta non si può simulare.
 */
class ScenarioBuilder
{
    private const INCOME_NODES = ['pensionato' => 'pensione_netta', 'autonomo' => 'reddito_autonomo'];

    /** @return array{best: array<string,string>, worst: array<string,string>} */
    public function build(LoanRequest $loan): array
    {
        $answers = $loan->answers ?? [];
        $contract = QuoteContractType::where('product', $loan->product)->first()
            ?? throw new QuoteUnavailable("Il prodotto {$loan->product} non si simula.");

        $employment = $this->employmentType($answers, $contract->value);
        $months = $this->duration($contract->value, $answers['durata'] ?? null);
        $now = now()->toImmutable();

        $ids = $this->ids($loan);

        return [
            'best' => $ids + $this->scenario($answers, $contract->value, $employment, $months, $now, best: true),
            'worst' => $ids + $this->scenario($answers, $contract->value, $employment, $months, $now, best: false),
        ];
    }

    /**
     * ID_listino (dell'azienda) e ID_agente (del produttore, altrimenti dell'azienda): li vuole il servizio.
     * Se non sono configurati si omettono e il servizio risponde con il campo mancante.
     *
     * @return array<string,string>
     */
    private function ids(LoanRequest $loan): array
    {
        $company = Company::forWhatsApp($loan->agent_wa_number);
        $agent = filled($loan->agent_wa_number) ? Fornitore::findByWhatsApp($loan->agent_wa_number) : null;

        return array_filter([
            'ID_listino' => (string) $company?->quote_id_listino,
            'ID_agente' => (string) ($agent?->codice ?: $company?->quote_id_agente),
        ], fn (string $id) => $id !== '');
    }

    /** @return array<string,string> */
    private function scenario(array $answers, string $contract, string $employment, int $months, CarbonImmutable $now, bool $best): array
    {
        $birth = $this->birthFromBand($answers, $now, $best);

        $hired = $this->hiredFromBand($answers, $birth, $now, $best);
        $rata = $this->exactAmount($answers['importo_rata'] ?? null);
        $income = $rata !== null ? $rata * 5 : $this->bound('reddito', $answers[self::INCOME_NODES[$answers['lavoro'] ?? ''] ?? 'reddito'] ?? null, low: ! $best);

        $common = [
            'Data_nascita' => $this->date($birth),
            'Data_assunzione' => $this->date($hired),
        ];

        $params = $common + $this->points($answers) + [
            'Data_decorrenza' => $this->date($now->addMonthsNoOverflow(2)),
            'Sesso' => $this->sex($answers),
            'Tipo_contratto' => $contract,
            'Tipo_rapporto' => $employment,
            'Durata' => (string) $months,
            'Importo_rata' => $this->money($income / 5),
            'Rinnovo' => 'NO',
        ];

        // Il prestito vuole anche l'importo richiesto e il reddito (la rata è un quinto del reddito, in attesa della domanda dedicata).
        if ($contract === 'Prestito') {
            $params += [
                'Importo_richiesto' => $this->money($this->bound('importo', $answers['importo'] ?? null, low: ! $best)),
                'Reddito_richiedenti' => $this->money($income),
            ];
        }

        return $params;
    }

    private function birthFromBand(array $answers, CarbonImmutable $now, bool $best): CarbonImmutable
    {
        $age = $this->bound('eta', $answers['eta'] ?? null, low: $best);

        return $now->setDate($now->year - $age, 1, 1)->startOfDay();
    }

    private function hiredFromBand(array $answers, CarbonImmutable $birth, CarbonImmutable $now, bool $best): CarbonImmutable
    {
        $seniorityCode = $answers['anzianita'] ?? $answers['anni_attivita'] ?? null;
        $seniority = $seniorityCode !== null
            ? $this->bound('anzianita', $seniorityCode, low: ! $best)
            : (int) config('finanziamento.quote.default_seniority_years', 20);

        return max($now->setDate($now->year - $seniority, 1, 1)->startOfDay(), $birth->addYears(18));
    }

    /** Importo in euro scritto dall'agente («300» o «300,50»); null se manca o non è maggiore di zero. */
    private function exactAmount(mixed $value): ?float
    {
        $amount = str_replace(',', '.', trim((string) $value));

        return is_numeric($amount) && (float) $amount > 0 ? (float) $amount : null;
    }

    /** I punti da caricare (valore assoluto) scelti dall'agente; se non c'è la risposta la provvigione non si invia. */
    private function points(array $answers): array
    {
        $points = str_replace(',', '.', trim((string) ($answers['punti'] ?? '')));

        return is_numeric($points) && (float) $points > 0 ? ['Provvigione' => $this->money((float) $points)] : [];
    }

    private function employmentType(array $answers, string $contract): string
    {
        $type = $this->chosenType($answers) ?? QuoteEmploymentMap::resolve($answers)?->tipo_rapporto
            ?? throw new QuoteUnavailable('Situazione lavorativa non riconosciuta.');

        $allowed = QuoteEmploymentType::where('value', $type)->first()?->contracts;
        if ($allowed !== null && ! in_array($contract, $allowed, true)) {
            throw new QuoteUnavailable("{$type} non è ammesso con {$contract}.");
        }

        return $type;
    }

    /** Il tipo di rapporto scelto dall'agente (cessione), nella forma che vuole il servizio: «privato_spa» → «Privato SPA». */
    private function chosenType(array $answers): ?string
    {
        $code = ($answers['tipo_rapporto'] ?? null) === 'altro' ? ($answers['tipo_rapporto_altro'] ?? null) : ($answers['tipo_rapporto'] ?? null);

        return $code === null ? null : QuoteEmploymentType::all()->first(fn (QuoteEmploymentType $type) => Str::slug($type->value, '_') === $code)?->value;
    }

    private function duration(string $contract, ?string $code): int
    {
        if ($code === null || ! preg_match('/^m(\d+)$/', $code, $m)) {
            throw new QuoteUnavailable('Durata mancante.');
        }

        $wanted = (int) $m[1];
        $closest = QuoteDuration::where('contract', $contract)->orderBy('months')->pluck('months')
            ->sortBy(fn ($months) => abs($months - $wanted))->first();

        return $closest ?? throw new QuoteUnavailable("Nessuna durata ammessa per {$contract}.");
    }

    /** Estremo basso o alto della fascia (anni o euro). */
    private function bound(string $dimension, ?string $code, bool $low): int
    {
        $band = $code !== null ? QuoteBandBound::where('dimension', $dimension)->where('code', $code)->first() : null;

        return (int) ($band?->{$low ? 'low' : 'high'} ?? throw new QuoteUnavailable("Fascia {$dimension} mancante o sconosciuta."));
    }

    private function sex(array $answers): string
    {
        return ($answers['sesso'] ?? null) === 'sesso_f' ? 'F' : (($answers['sesso'] ?? null) === 'sesso_m' ? 'M' : (string) config('finanziamento.quote.default_sex', 'M'));
    }

    private function date(CarbonImmutable $date): string
    {
        return $date->format('m-d-Y');
    }

    private function money(float|int $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
