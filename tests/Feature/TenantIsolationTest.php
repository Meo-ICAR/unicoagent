<?php

namespace Tests\Feature;

use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\LoanRequests\Pages\ListLoanRequests;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Fornitore;
use App\Models\LoanRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $races;

    private Company $other;

    private LoanRequest $racesLoan;

    private LoanRequest $otherLoan;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');

        $this->races = Company::create(['name' => 'Races Finance']);
        $this->other = Company::create(['name' => 'GBD Energy']);
        $this->racesLoan = $this->loan('FIN-2026-0001', $this->races, '393331112222');
        $this->otherLoan = $this->loan('FIN-2026-0002', $this->other, '393339999999');
        foreach ([[$this->races, '393331112222'], [$this->other, '393339999999']] as [$company, $number]) {
            Conversation::create(['wa_number' => $number, 'flow' => 'richiesta', 'node' => 'prodotto', 'company_id' => $company->id, 'data' => [], 'history' => []]);
            Fornitore::create(['name' => 'Agente '.$company->name, 'tel' => $number, 'is_active' => true, 'company_id' => $company->id]);
        }
    }

    private function loan(string $code, Company $company, string $agent): LoanRequest
    {
        return LoanRequest::create(['code' => $code, 'agent_wa_number' => $agent, 'product' => 'personale', 'status' => 'richiesta', 'answers' => [], 'company_id' => $company->id]);
    }

    private function racesAdmin(): User
    {
        return tap(User::factory()->create(['company_id' => $this->races->id]), fn (User $user) => $this->actingAs($user));
    }

    public function test_l_admin_di_una_company_vede_solo_i_suoi_dati(): void
    {
        $this->racesAdmin();

        $this->assertSame(['FIN-2026-0001'], LoanRequest::pluck('code')->all());
        $this->assertSame(1, Conversation::count());
        $this->assertSame(['Agente Races Finance'], Fornitore::pluck('name')->all());
        $this->assertSame(['Races Finance'], Company::pluck('name')->all());
        $this->assertSame(1, User::count());

        Livewire::test(ListLoanRequests::class)->assertCanSeeTableRecords([$this->racesLoan])->assertCanNotSeeTableRecords([$this->otherLoan]);
        Livewire::test(ListConversations::class)->assertCountTableRecords(1);
    }

    public function test_i_record_di_un_altra_company_non_si_aprono_ne_si_cancellano(): void
    {
        $this->racesAdmin();

        $this->get("/admin/loan-requests/{$this->otherLoan->id}")->assertNotFound();
        $this->get("/admin/loan-requests/{$this->racesLoan->id}")->assertOk();

        Livewire::test(ListLoanRequests::class)
            ->selectTableRecords([$this->racesLoan->id, $this->otherLoan->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertNull(LoanRequest::withoutGlobalScopes()->find($this->racesLoan->id));
        $this->assertNotNull(LoanRequest::withoutGlobalScopes()->find($this->otherLoan->id));
    }

    public function test_i_record_creati_da_un_admin_di_company_restano_nella_sua_company(): void
    {
        $this->racesAdmin();

        $loan = $this->loan('FIN-2026-0003', $this->other, '393331112222');

        $this->assertSame($this->races->id, $loan->company_id);
    }

    public function test_i_cataloghi_comuni_e_la_creazione_di_aziende_sono_del_super_admin(): void
    {
        $this->racesAdmin();
        foreach (['products', 'flow-checks', 'finanziamento-documents'] as $slug) {
            $this->get("/admin/{$slug}")->assertForbidden();
        }
        $this->get('/admin/companies/create')->assertForbidden();
    }

    public function test_senza_company_si_e_super_admin_e_si_vede_tutto(): void
    {
        $this->actingAs(User::factory()->create(['company_id' => null]));

        $this->assertSame(2, LoanRequest::count());
        $this->assertSame(2, Conversation::count());
        $this->assertSame(2, Company::count());
        $this->get('/admin/products')->assertOk();
    }

    public function test_senza_utente_il_bot_vede_tutto(): void
    {
        $this->assertSame(2, LoanRequest::count());
        $this->assertSame(2, Fornitore::count());
    }
}
