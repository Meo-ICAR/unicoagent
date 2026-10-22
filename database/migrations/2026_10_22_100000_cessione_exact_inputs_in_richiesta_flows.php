<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Percorso «richiesta» per la cessione del quinto: il simulatore Mediafacile vuole dati esatti, non fasce.
 * Il quinto salta l'importo (è il risultato) e chiede data di nascita, data di assunzione e rata da simulare;
 * il prestito personale chiede i punti da caricare. Come config/finanziamento.php, per i percorsi già nel database
 * (solo quelli con la domanda «Quote già cedute», cioè il credito al consumo).
 */
return new class extends Migration
{
    private const TEXT_NODES = [
        'data_nascita' => [
            'label' => 'Data di nascita', 'prompt' => 'Qual è la data di nascita del cliente? (gg/mm/aaaa)', 'after' => 'eta', 'next' => 'sesso',
            'params' => ['rules' => ['required', 'date_format:d/m/Y', 'before:-18 years', 'after:1900-01-01'], 'strip_spaces' => true, 'error' => 'Data non valida o cliente minorenne: scrivila come gg/mm/aaaa, ad esempio 15/03/1970.'],
        ],
        'data_assunzione' => [
            'label' => 'Data di assunzione', 'prompt' => 'Da quando lavora presso l\'attuale datore? (gg/mm/aaaa)', 'after' => 'contratto', 'next' => 'importo_rata',
            'params' => ['rules' => ['required', 'date_format:d/m/Y', 'before_or_equal:today', 'after:1950-01-01'], 'strip_spaces' => true, 'error' => 'Data non valida: scrivila come gg/mm/aaaa, ad esempio 01/09/2010.'],
        ],
        'importo_rata' => [
            'label' => 'Rata da simulare', 'prompt' => 'Quale rata mensile vuoi simulare? Indica l\'importo in euro, ad esempio 300 o 300,50.', 'after' => 'data_assunzione', 'next' => 'dimensione_azienda',
            'params' => ['rules' => ['required', 'regex:/^\d{2,4}([.,]\d{1,2})?$/'], 'strip_spaces' => true, 'error' => 'Indica la rata in euro come numero, ad esempio 300 o 300,50.'],
        ],
    ];

    public function up(): void
    {
        // Solo le domande a scelta possono essere modificabili (FlowValidator): le domande di testo restano com'erano.
        DB::table('flow_nodes')->whereIn('code', [...array_keys(self::TEXT_NODES), 'punti'])->where('type', 'text')->update(['can_modify' => false]);

        $flows = DB::table('flows')->where('code', 'richiesta')->get();

        foreach ($flows as $flow) {
            $node = fn (string $code) => DB::table('flow_nodes')->where('flow_id', $flow->id)->where('code', $code)->first();

            if ($node('quote_cedute') === null || $node('data_nascita') !== null || $node('tipo_rapporto') !== null) {
                continue;
            }

            foreach (self::TEXT_NODES as $code => $def) {
                $id = DB::table('flow_nodes')->insertGetId([
                    'flow_id' => $flow->id, 'code' => $code, 'type' => 'text', 'label' => $def['label'], 'prompt' => $def['prompt'],
                    'sort_order' => $this->makeRoomAfter($flow->id, $def['after']), 'skippable' => false, 'can_modify' => false, 'save' => true, 'jump_by' => 'answer',
                    'params' => json_encode($def['params']), 'company_id' => $flow->company_id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->jump($flow, $id, '*', $def['next']);
            }

            $this->jump($flow, $node('prodotto')->id, 'quinto', 'durata');
            $this->jump($flow, $node('durata')->id, 'quinto', 'data_nascita');
            $this->jump($flow, $node('crif')->id, 'personale', 'punti');

            // Dopo il contratto il quinto chiede la data di assunzione esatta, gli altri prodotti l'anzianità a fasce.
            $contratto = $node('contratto');
            DB::table('flow_node_jumps')->where('flow_node_id', $contratto->id)->delete();
            DB::table('flow_nodes')->where('id', $contratto->id)->update(['jump_by' => 'prodotto']);
            $this->jump($flow, $contratto->id, 'quinto', 'data_assunzione');
            $this->jump($flow, $contratto->id, '*', 'anzianita');

            // I punti devono essere maggiori di zero (il servizio rifiuta 0).
            if ($punti = $node('punti')) {
                $params = json_decode((string) $punti->params, true) ?: [];
                $params['rules'] = ['required', 'regex:/^(?!0+([.,]0+)?$)\d{1,2}([.,]\d{1,2})?$/'];
                $params['error'] = 'Indica i punti come numero maggiore di zero, ad esempio 3 o 2,5.';
                DB::table('flow_nodes')->where('id', $punti->id)->update(['params' => json_encode($params)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('flows')->where('code', 'richiesta')->get() as $flow) {
            $node = fn (string $code) => DB::table('flow_nodes')->where('flow_id', $flow->id)->where('code', $code)->first();

            if ($node('data_nascita') === null) {
                continue;
            }

            $this->jump($flow, $node('prodotto')->id, 'quinto', 'importo');
            $this->jump($flow, $node('durata')->id, 'quinto', 'eta');
            DB::table('flow_node_jumps')->where('flow_node_id', $node('crif')->id)->where('when_value', 'personale')->delete();

            $contratto = $node('contratto');
            DB::table('flow_node_jumps')->where('flow_node_id', $contratto->id)->delete();
            DB::table('flow_nodes')->where('id', $contratto->id)->update(['jump_by' => 'answer']);
            $this->jump($flow, $contratto->id, '*', 'anzianita');

            DB::table('flow_nodes')->where('flow_id', $flow->id)->whereIn('code', array_keys(self::TEXT_NODES))->delete();
        }
    }

    /** Libera il posto subito dopo il nodo indicato, facendo scorrere i successivi. */
    private function makeRoomAfter(int $flowId, string $code): int
    {
        $position = (int) DB::table('flow_nodes')->where('flow_id', $flowId)->where('code', $code)->value('sort_order');
        DB::table('flow_nodes')->where('flow_id', $flowId)->where('sort_order', '>', $position)->increment('sort_order');

        return $position + 1;
    }

    private function jump(object $flow, int $nodeId, string $when, string $goTo): void
    {
        DB::table('flow_node_jumps')->updateOrInsert(
            ['flow_node_id' => $nodeId, 'when_value' => $when],
            ['go_to' => $goTo, 'sort_order' => $when === '*' ? 99 : 0, 'company_id' => $flow->company_id, 'created_at' => now(), 'updated_at' => now()],
        );
    }
};
