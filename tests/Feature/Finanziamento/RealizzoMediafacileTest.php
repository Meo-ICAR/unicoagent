<?php

namespace Tests\Feature\Finanziamento;

use App\Models\Company;
use App\Models\Fornitore;
use Illuminate\Support\Facades\Http;

class RealizzoMediafacileTest extends ConversationTestCase
{
    private const FLOW = ['#menu_richiedi', '#quinto', '#m60', '#eta_40', '#sesso_m', '#pubblico', '#anz_10', '300', '2,5', '#conferma'];

    private function record(string $bank, string $erogato, string $provvigione, int $errore): string
    {
        $fields = ['Importo_capitale' => '1800000', 'Importo_provvigione' => $provvigione, 'Importo_erogato' => $erogato, 'Errore' => $errore, 'Denominazione_istituto' => $bank];

        return '<record>'.collect($fields)->map(fn ($value, $name) => "<field name=\"{$name}\"><data>{$value}</data></field>")->implode('').'</record>';
    }

    /** La risposta è quella vera del servizio: un fmresultset di FileMaker con un record per offerta. */
    private function response(string ...$records): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><fmresultset xmlns="http://www.filemaker.com/xml/fmresultset" version="1.0"><error code="0"></error>'
            .'<resultset count="'.count($records).'">'.implode('', $records).'</resultset></fmresultset>';
    }

    public function test_il_preventivo_riporta_medio_ponderato_massimo_e_minimo_con_la_banca_scartando_gli_errori(): void
    {
        Company::create(['name' => 'Races Finance', 'url_preventivatore' => 'https://races.example.com/ws/ws_simulazione.php', 'preventivatore_passkey' => 'KEY', 'quote_id_listino' => '0001', 'quote_id_agente' => '0007']);
        Fornitore::create(['name' => 'Agenzia Bianchi', 'tel' => '+39 333 111 2222', 'is_active' => true]);
        // Due chiamate: la migliore (età minima, anzianità massima) e la peggiore (età massima, anzianità minima).
        Http::fake(['races.example.com/*' => Http::sequence()
            ->push($this->response($this->record('Banca Alfa', '16.200,00', '43000', 2), $this->record('Banca Beta', '15.100,00', '30000', 2)))
            ->push($this->response($this->record('Banca Gamma', '14.200,00', '15000', 2), $this->record('Banca Errore', '99.000,00', '90000', 1), $this->record('Banca Delta', '15.800,00', '20000', 2)))]);

        $body = $this->bodies($this->say(...self::FLOW));

        // Il massimo e il minimo sono presi da tutte le offerte valide delle due simulazioni.
        $this->assertStringContainsString('da *14.200 €* a *16.200 €*', $body);
        $this->assertStringContainsString('Realizzo medio ponderato: *15.543 €*', $body);
        $this->assertStringContainsString('Massimo: *16.200 €* — Banca Alfa', $body);
        $this->assertStringContainsString('Minimo: *14.200 €* — Banca Gamma', $body);
        $this->assertStringNotContainsString('99.000', $body);
        Http::assertSentCount(2);
        $requests = collect(Http::recorded())->map(fn (array $pair): array => $pair[0]->data());
        $this->assertEquals([
            'passkey' => 'KEY', 'ID_listino' => '0001', 'ID_agente' => '0007', 'Data_nascita' => '01-01-1996', 'Data_assunzione' => '01-01-2016',
            'Data_decorrenza' => now()->addMonthsNoOverflow(2)->format('m-d-Y'), 'Provvigione' => '2,50', 'Sesso' => 'M', 'Tipo_contratto' => 'Cessione',
            'Tipo_rapporto' => 'Pubblico', 'Durata' => '60', 'Importo_rata' => '300,00', 'Rinnovo' => 'NO',
        ], $requests[0]);
        $this->assertSame(['01-01-1986', '01-01-2023'], [$requests[1]['Data_nascita'], $requests[1]['Data_assunzione']]);
    }
}
