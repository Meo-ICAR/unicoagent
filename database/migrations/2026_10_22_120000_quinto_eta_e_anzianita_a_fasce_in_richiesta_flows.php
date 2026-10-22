<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il simulatore fa due chiamate (migliore e peggiore) sulle fasce di età e di anzianità: il quinto torna a chiedere
 * «Fascia d'età» e «Anzianità lavorativa» a fasce, non le date esatte. Restano tipo di rapporto, rata e punti.
 * Percorso del quinto: durata → età → sesso → tipo di rapporto → anzianità (non ai pensionati) → rata → punti.
 * Come config/finanziamento.php, per i percorsi che hanno la domanda «data_nascita».
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('flows')->where('code', 'richiesta')->get() as $flow) {
            $node = fn (string $code) => DB::table('flow_nodes')->where('flow_id', $flow->id)->where('code', $code)->first();

            if ($node('data_nascita') === null) {
                continue;
            }

            DB::table('flow_nodes')->where('flow_id', $flow->id)->whereIn('code', ['data_nascita', 'data_assunzione'])->delete();

            $this->jump($flow, $node('durata')->id, 'quinto', 'eta');
            $this->jump($flow, $node('tipo_rapporto')->id, '*', 'anzianita');
            $this->jump($flow, $node('tipo_rapporto_altro')->id, '*', 'anzianita');

            $anzianitaId = $node('anzianita')->id;
            DB::table('flow_node_jumps')->where('flow_node_id', $anzianitaId)->delete();
            DB::table('flow_nodes')->where('id', $anzianitaId)->update(['jump_by' => 'prodotto']);
            $this->jump($flow, $anzianitaId, 'quinto', 'importo_rata');
            $this->jump($flow, $anzianitaId, '*', 'reddito');

            // La rata segue l'anzianità.
            $anzianita = $node('anzianita')->sort_order;
            DB::table('flow_nodes')->where('flow_id', $flow->id)->where('sort_order', '>', $anzianita)->where('code', '!=', 'importo_rata')->increment('sort_order');
            DB::table('flow_nodes')->where('id', $node('importo_rata')->id)->update(['sort_order' => $anzianita + 1]);
        }
    }

    public function down(): void
    {
        // Le domande a date esatte non si ricreano: vedi 2026_10_22_100000, che le crea e che ha il suo down().
    }

    private function jump(object $flow, int $nodeId, string $when, string $goTo): void
    {
        DB::table('flow_node_jumps')->updateOrInsert(
            ['flow_node_id' => $nodeId, 'when_value' => $when],
            ['go_to' => $goTo, 'sort_order' => $when === '*' ? 99 : 0, 'company_id' => $flow->company_id, 'created_at' => now(), 'updated_at' => now()],
        );
    }
};
