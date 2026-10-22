<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ID_listino e ID_agente che il servizio di simulazione Mediafacile vuole in ogni richiesta, per azienda.
 * L'ID_agente del singolo produttore, se c'è, sta in fornitoris.codice. Le colonne si aggiungono anche a companies
 * di unicosuite (connessione `core`), per quando l'app passerà lì.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns(null);

        if ((string) config('database.connections.core.database') !== DB::connection()->getDatabaseName()) {
            $this->addColumns('core');
        }
    }

    public function down(): void
    {
        foreach ([null, 'core'] as $connection) {
            if (Schema::connection($connection)->hasColumn('companies', 'quote_id_listino')) {
                Schema::connection($connection)->table('companies', fn (Blueprint $table) => $table->dropColumn(['quote_id_listino', 'quote_id_agente']));
            }
        }
    }

    private function addColumns(?string $connection): void
    {
        $schema = Schema::connection($connection);

        if ($schema->hasColumn('companies', 'quote_id_listino')) {
            return;
        }

        $schema->table('companies', function (Blueprint $table) {
            $table->string('quote_id_listino', 20)->nullable()->comment('ID_listino da inviare al servizio di simulazione Mediafacile');
            $table->string('quote_id_agente', 20)->nullable()->comment('ID_agente di default per il servizio di simulazione Mediafacile (se il produttore non ha il suo in fornitoris.codice)');
        });
    }
};
