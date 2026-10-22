<?php

namespace Tests\Feature\Finanziamento;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\Fornitore;

class MenuPerCompanyTest extends ConversationTestCase
{
    private function gbd(): Company
    {
        $company = Company::create(['name' => 'GBD', 'menu' => ['options' => ['menu_richiedi' => 'Nuova offerta', 'menu_perfeziona' => 'Sottoscrivi contratto']]]);
        Fornitore::create(['name' => 'Inst', 'tel' => $this->agent, 'is_active' => true, 'company_id' => $company->id]);

        return $company;
    }

    public function test_la_company_senza_override_usa_il_menu_predefinito(): void
    {
        $this->assertSame(config('finanziamento.menu.options'), Company::create(['name' => 'X'])->menuConfig()['options']);
    }

    public function test_il_menu_dell_installatore_e_quello_della_sua_company(): void
    {
        $this->gbd();

        $options = collect($this->say('ciao'))->flatMap(fn ($r) => $r->options)->all();

        $this->assertContains('Nuova offerta', $options);
        $this->assertNotContains('Modifica Preventivo', $options);
    }

    public function test_si_sceglie_dal_menu_anche_scrivendo_il_titolo_della_company(): void
    {
        $this->gbd();

        $this->say('ciao', 'Nuova offerta');

        $this->assertSame('richiesta', Conversation::latest('id')->first()?->flow);
    }

    public function test_mediatore_demo_avvia_anche_il_percorso_della_fornitura(): void
    {
        $demo = Company::create([
            'name' => 'Mediatore DEMO',
            'type' => 'FINANCE',
            'menu' => ['options' => [
                'menu_richiedi' => 'Richiedi Finanziamento',
                'fornitura' => 'Nuova offerta',
            ]],
        ]);
        $flow = Flow::create([
            'company_id' => $demo->id, 'code' => 'fornitura', 'name' => 'Nuova offerta',
            'start' => 'profilo', 'restart' => 'profilo', 'is_active' => true, 'is_test' => false,
        ]);
        FlowNode::create([
            'flow_id' => $flow->id, 'company_id' => $demo->id, 'code' => 'profilo',
            'type' => 'choice', 'prompt' => 'Che tipo di utenza è?',
        ]);

        $this->say('ciao', 'Nuova offerta');

        $this->assertSame('fornitura', Conversation::latest('id')->first()?->flow);
        $this->assertSame($demo->id, Conversation::latest('id')->first()?->company_id);
    }
}
