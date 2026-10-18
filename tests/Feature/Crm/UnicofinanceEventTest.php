<?php

namespace Tests\Feature\Crm;

use App\Jobs\SyncCrmRequest;
use App\Models\Company;
use App\Models\LoanRequest;
use App\Models\PraticaDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Avvisi di unicofinance sui documenti respinti o richiesti in istruttoria. */
class UnicofinanceEventTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/unicofinance/eventi';

    private LoanRequest $loan;

    private PraticaDocument $identity;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SyncCrmRequest::class]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        Company::create(['name' => 'H', 'crm_driver' => 'unicofinance', 'crm_config' => ['url' => 'https://unicofinance.example.com', 'token' => 'TOK', 'secret' => 'SEG']]);
        $this->loan = LoanRequest::create([
            'code' => 'FIN-2026-0400', 'agent_wa_number' => '393331112222', 'product' => 'personale', 'status' => 'perfezionata',
            'answers' => ['prodotto' => 'personale'], 'perfected_at' => now(), 'crm_lead_id' => 'WA-FIN-2026-0400',
        ]);
        $this->identity = $this->loan->praticaDocuments()->create(['code' => 'documento_identita', 'name' => 'Documento d\'identità', 'requirement' => 'obbligatorio', 'status' => 'ok']);
    }

    /** @param  array<string, mixed>  $override */
    private function event(array $override = []): array
    {
        return array_replace([
            'event_id' => 'documento.da_rifare:10:respinto:1700000000',
            'evento' => 'documento.da_rifare',
            'riferimento' => 'FIN-2026-0400',
            'documento' => ['id' => 10, 'tipo' => 'carta-identita', 'alias' => ['documento_identita'], 'nome' => 'Carta d\'identità', 'stato' => 'respinto', 'motivo' => 'Scaduta'],
        ], $override);
    }

    /** @param  array<string, mixed>  $payload */
    private function send(array $payload, string $secret = 'SEG', ?int $timestamp = null): TestResponse
    {
        $body = json_encode($payload);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', implode('.', [$timestamp, 'POST', self::PATH, hash('sha256', $body)]), $secret);

        return $this->call('POST', self::PATH, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_TIMESTAMP' => (string) $timestamp, 'HTTP_X_SIGNATURE' => $signature], $body);
    }

    public function test_a_rejected_document_is_rejected_here_and_the_agent_is_told(): void
    {
        $this->send($this->event())->assertOk()->assertJsonPath('esito', 'ok')->assertJsonPath('documento', 'documento_identita');

        $this->identity->refresh();
        $this->assertSame('rejected', $this->identity->status);
        $this->assertSame('Respinto in istruttoria (unicofinance): Scaduta', $this->identity->lastAnnotation());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'graph.facebook.com') && $request['to'] === '393331112222' && str_contains(json_encode($request->data()), 'Scaduta'));
        Bus::assertNotDispatched(SyncCrmRequest::class);
    }

    public function test_a_document_requested_by_unicofinance_becomes_an_integration(): void
    {
        $this->send($this->event(['event_id' => 'r:1', 'evento' => 'documento.richiesto', 'documento' => ['tipo' => 'estratto-conto', 'alias' => [], 'nome' => 'Estratto conto', 'motivo' => null]]))->assertOk();

        $slot = $this->loan->praticaDocuments()->where('code', 'estratto-conto')->sole();
        $this->assertSame('integrazione_richiesta', $slot->status);
        $this->assertSame('integrativo', $slot->requirement);
        $this->assertSame('Estratto conto', $slot->name);
        Bus::assertNotDispatched(SyncCrmRequest::class);
    }

    public function test_the_same_event_counts_once(): void
    {
        $this->send($this->event())->assertJsonPath('esito', 'ok');
        $this->send($this->event())->assertOk()->assertJsonPath('esito', 'duplicate');

        Http::assertSentCount(1);
    }

    public function test_a_wrong_or_old_signature_is_refused(): void
    {
        $this->send($this->event(), 'ALTRO')->assertUnauthorized();
        $this->send($this->event(), 'SEG', time() - 3600)->assertUnauthorized();

        $this->assertSame('ok', $this->identity->refresh()->status);
    }

    public function test_an_unknown_practice_or_event_changes_nothing(): void
    {
        $this->send($this->event(['riferimento' => 'FIN-ALTRA']))->assertNotFound();
        $this->send($this->event(['event_id' => 'x:2', 'evento' => 'pratica.chiusa']))->assertOk()->assertJsonPath('esito', 'ignorato');

        $this->assertSame('ok', $this->identity->refresh()->status);
    }

    public function test_a_practice_of_another_company_is_not_touched(): void
    {
        $other = Company::create(['name' => 'Altra', 'crm_driver' => 'unicofinance', 'crm_config' => ['url' => 'https://altra.example.com', 'token' => 'T2', 'secret' => 'ALTRO']]);
        $this->loan->update(['company_id' => Company::query()->where('name', 'H')->value('id')]);

        $this->send($this->event(), 'ALTRO')->assertNotFound();
        $this->assertSame('ok', $this->identity->refresh()->status);
        $this->assertNotNull($other->id);
    }

    public function test_an_unknown_event_is_ignored_even_without_document(): void
    {
        $this->send(['event_id' => 'x:3', 'evento' => 'pratica.chiusa'])->assertOk()->assertJsonPath('esito', 'ignorato');
    }

    public function test_the_alias_comes_before_the_type_and_a_new_document_keeps_the_alias(): void
    {
        $this->loan->praticaDocuments()->create(['code' => 'carta-identita', 'name' => 'Altro', 'requirement' => 'facoltativo', 'status' => 'ok']);

        $this->send($this->event())->assertOk()->assertJsonPath('documento', 'documento_identita');

        $this->send($this->event(['event_id' => 'r:9', 'evento' => 'documento.richiesto', 'documento' => ['tipo' => 'cud-zz', 'alias' => ['cud'], 'nome' => 'CUD']]))
            ->assertOk()->assertJsonPath('documento', 'cud');
    }
}
