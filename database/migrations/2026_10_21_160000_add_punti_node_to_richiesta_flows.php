<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aggiunge ai percorsi «richiesta» la domanda «Punti da caricare» (valore assoluto), dopo «Quote già cedute»,
 * per la cessione del quinto: serve al simulatore come `Provvigione`. Non tocca i percorsi senza quel nodo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $flows = DB::table('flows')->where('code', 'richiesta')->get();

        foreach ($flows as $flow) {
            $cedute = DB::table('flow_nodes')->where('flow_id', $flow->id)->where('code', 'quote_cedute')->first();

            if ($cedute === null || DB::table('flow_nodes')->where('flow_id', $flow->id)->where('code', 'punti')->exists()) {
                continue;
            }

            $now = now();
            DB::table('flow_nodes')->where('flow_id', $flow->id)->where('sort_order', '>', $cedute->sort_order)->increment('sort_order');
            $nodeId = DB::table('flow_nodes')->insertGetId([
                'flow_id' => $flow->id, 'code' => 'punti', 'type' => 'text', 'label' => 'Punti da caricare',
                'prompt' => 'Quanti punti vuoi caricare? Indica il valore assoluto, ad esempio 3 o 2,5.',
                'sort_order' => $cedute->sort_order + 1, 'skippable' => false, 'can_modify' => false, 'save' => true, 'jump_by' => 'answer',
                'params' => json_encode(['rules' => ['required', 'regex:/^\d{1,2}([.,]\d{1,2})?$/'], 'strip_spaces' => true, 'error' => 'Indica i punti come numero, ad esempio 3 o 2,5.']),
                'company_id' => $flow->company_id, 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('flow_node_jumps')->insert([
                'flow_node_id' => $nodeId, 'when_value' => '*', 'go_to' => 'riepilogo', 'sort_order' => 0,
                'company_id' => $flow->company_id, 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('flow_node_jumps')->where('flow_node_id', $cedute->id)->where('when_value', '*')->update(['go_to' => 'punti', 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('flow_nodes')->where('code', 'punti')->get() as $node) {
            $flowId = $node->flow_id;
            DB::table('flow_nodes')->where('flow_id', $flowId)->where('code', 'quote_cedute')->get()
                ->each(fn ($cedute) => DB::table('flow_node_jumps')->where('flow_node_id', $cedute->id)->where('when_value', '*')->update(['go_to' => 'riepilogo']));
            DB::table('flow_nodes')->where('id', $node->id)->delete();
        }
    }
};
