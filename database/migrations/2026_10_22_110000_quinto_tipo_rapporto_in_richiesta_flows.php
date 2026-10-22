<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cessione del quinto: il simulatore Mediafacile vuole il tipo di rapporto, non lavoro, contratto e dimensione dell'azienda.
 * Dopo il sesso il quinto chiede «Tipo di rapporto» (con «Altro» per medico, postale, ferroviere), poi la data di assunzione
 * (non ai pensionati), la rata e i punti. Finanziamenti in corso, CRIF e quote cedute non si chiedono più al quinto.
 * Come config/finanziamento.php, per i percorsi con la domanda «data_nascita» (cioè già portati ai dati esatti).
 */
return new class extends Migration
{
    private const OPTIONS = [
        'tipo_rapporto' => [
            'statale' => 'Statale', 'pubblico' => 'Pubblico', 'parapubblico' => 'Parapubblico', 'privato_spa' => 'Privato SPA',
            'privato_altra_forma' => 'Privato Altra forma', 'privato_small_business' => 'Privato Small Business',
            'pensionato_inps' => 'Pensionato INPS', 'pensionato_inpdap' => 'Pensionato INPDAP', 'pensionato_altri_enti' => 'Pensionato altri enti', 'altro' => 'Altro',
        ],
        'tipo_rapporto_altro' => ['medico' => 'Medico', 'postale' => 'Postale', 'ferroviere' => 'Ferroviere'],
    ];

    private const JUMPS = [
        'tipo_rapporto' => [
            'pensionato_inps' => 'importo_rata', 'pensionato_inpdap' => 'importo_rata', 'pensionato_altri_enti' => 'importo_rata',
            'altro' => 'tipo_rapporto_altro', '*' => 'data_assunzione',
        ],
        'tipo_rapporto_altro' => ['*' => 'data_assunzione'],
    ];

    private const NODES = [
        'tipo_rapporto' => ['label' => 'Tipo di rapporto', 'prompt' => 'Qual è il tipo di rapporto di lavoro del cliente?', 'after' => 'sesso'],
        'tipo_rapporto_altro' => ['label' => 'Altro rapporto', 'prompt' => 'Quale tra questi?', 'after' => 'tipo_rapporto'],
    ];

    public function up(): void
    {
        foreach (DB::table('flows')->where('code', 'richiesta')->get() as $flow) {
            $node = fn (string $code) => DB::table('flow_nodes')->where('flow_id', $flow->id)->where('code', $code)->first();

            if ($node('data_nascita') === null || $node('tipo_rapporto') !== null) {
                continue;
            }

            foreach (self::NODES as $code => $def) {
                $position = (int) $node($def['after'])->sort_order;
                DB::table('flow_nodes')->where('flow_id', $flow->id)->where('sort_order', '>', $position)->increment('sort_order');
                $id = DB::table('flow_nodes')->insertGetId([
                    'flow_id' => $flow->id, 'code' => $code, 'type' => 'choice', 'label' => $def['label'], 'prompt' => $def['prompt'],
                    'sort_order' => $position + 1, 'skippable' => false, 'can_modify' => false, 'save' => true, 'jump_by' => 'answer',
                    'company_id' => $flow->company_id, 'created_at' => now(), 'updated_at' => now(),
                ]);

                $order = 0;
                foreach (self::OPTIONS[$code] as $optionCode => $title) {
                    DB::table('flow_node_options')->insert([
                        'flow_node_id' => $id, 'code' => $optionCode, 'title' => $title, 'sort_order' => ++$order,
                        'company_id' => $flow->company_id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                foreach (self::JUMPS[$code] as $when => $goTo) {
                    $this->jump($flow, $id, $when, $goTo);
                }
            }

            $this->setJumps($flow, $node('sesso')->id, 'prodotto', ['quinto' => 'tipo_rapporto', '*' => 'lavoro']);
            $this->setJumps($flow, $node('contratto')->id, 'answer', ['*' => 'anzianita']);
            $this->setJumps($flow, $node('importo_rata')->id, 'answer', ['*' => 'punti']);
        }
    }

    public function down(): void
    {
        foreach (DB::table('flows')->where('code', 'richiesta')->get() as $flow) {
            $node = fn (string $code) => DB::table('flow_nodes')->where('flow_id', $flow->id)->where('code', $code)->first();

            if ($node('tipo_rapporto') === null) {
                continue;
            }

            $this->setJumps($flow, $node('sesso')->id, 'answer', ['*' => 'lavoro']);
            $this->setJumps($flow, $node('contratto')->id, 'prodotto', ['quinto' => 'data_assunzione', '*' => 'anzianita']);
            $this->setJumps($flow, $node('importo_rata')->id, 'answer', ['*' => 'dimensione_azienda']);
            DB::table('flow_nodes')->where('flow_id', $flow->id)->whereIn('code', array_keys(self::NODES))->delete();
        }
    }

    /** @param  array<string, string>  $jumps */
    private function setJumps(object $flow, int $nodeId, string $jumpBy, array $jumps): void
    {
        DB::table('flow_node_jumps')->where('flow_node_id', $nodeId)->delete();
        DB::table('flow_nodes')->where('id', $nodeId)->update(['jump_by' => $jumpBy]);

        foreach ($jumps as $when => $goTo) {
            $this->jump($flow, $nodeId, $when, $goTo);
        }
    }

    private function jump(object $flow, int $nodeId, string $when, string $goTo): void
    {
        DB::table('flow_node_jumps')->updateOrInsert(
            ['flow_node_id' => $nodeId, 'when_value' => $when],
            ['go_to' => $goTo, 'sort_order' => $when === '*' ? 99 : 0, 'company_id' => $flow->company_id, 'created_at' => now(), 'updated_at' => now()],
        );
    }
};
