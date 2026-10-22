<?php

namespace Tests\Feature\Loans;

use App\Models\Company;
use App\Models\LoanRequest;
use App\Services\Loans\CompanyLoanEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyLoanEstimatorTest extends TestCase
{
    use RefreshDatabase;

    private function loan(): LoanRequest
    {
        return LoanRequest::create([
            'code' => 'FIN-2026-0001', 'agent_wa_number' => '393331112222', 'product' => 'quinto', 'status' => 'richiesta',
            'answers' => ['prodotto' => 'quinto', 'importo' => 'imp_20k', 'durata' => 'm60', 'eta' => 'eta_50', 'lavoro' => 'dip_pub', 'contratto' => 'indet', 'anzianita' => 'anz_10', 'reddito' => 'red_2000', 'punti' => '2'],
        ]);
    }

    public function test_un_azienda_con_preventivatore_e_passkey_usa_il_servizio_mediafacile(): void
    {
        config(['finanziamento.quote.driver' => 'random']);
        Company::create(['name' => 'Races', 'url_preventivatore' => 'https://races.example.com/ws_simulazione.php', 'preventivatore_passkey' => 'KEY']);
        Http::fake(['races.example.com/*' => Http::sequence()
            ->push('<Offerte><Offerta><Importo_erogato>15000,00</Importo_erogato><Errore>2</Errore></Offerta></Offerte>')
            ->push('<Offerte><Offerta><Importo_erogato>9000,00</Importo_erogato><Errore>2</Errore></Offerta></Offerte>')]);

        $range = app(CompanyLoanEstimator::class)->estimate($this->loan());

        $this->assertSame(['min' => 9000, 'max' => 15000], ['min' => $range['min'], 'max' => $range['max']]);
        Http::assertSentCount(2);
    }

    public function test_un_azienda_senza_preventivatore_resta_alla_simulazione_di_base(): void
    {
        config(['finanziamento.quote.driver' => 'random']);
        Company::create(['name' => 'Hassisto']);
        Http::fake();

        $range = app(CompanyLoanEstimator::class)->estimate($this->loan());

        $this->assertLessThanOrEqual($range['max'], $range['min']);
        Http::assertNothingSent();
    }
}
