<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allinea i tipi delle tabelle locali a quelli di unicosuite (il database condiviso su cui l'app potrà passare):
 * - failed_jobs.connection e failed_jobs.queue: da varchar(255) a text, senza più l'indice composto;
 * - fornitoris e clientis: da id char(36) (UUID del vecchio sistema) a id bigint come nel core.
 * Gli UUID non si convertono in numeri: le due tabelle si ricreano con la struttura di unicosuite, che ha `legacy_id`
 * per ritrovare l'UUID del dump storico. I dati locali sono di sviluppo e l'app legge produttori e istituti dal core.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->failedJobsToText();
        $this->recreateWithBigintIds('fornitoris');
        $this->recreateWithBigintIds('clientis');
    }

    public function down(): void
    {
        // Gli id numerici non tornano UUID: l'UUID storico sta in `legacy_id`.
    }

    private function failedJobsToText(): void
    {
        if (! Schema::hasTable('failed_jobs') || Schema::getColumnType('failed_jobs', 'queue') === 'text') {
            return;
        }

        if (collect(Schema::getIndexes('failed_jobs'))->contains('name', 'failed_jobs_connection_queue_failed_at_index')) {
            DB::statement('ALTER TABLE `failed_jobs` DROP INDEX `failed_jobs_connection_queue_failed_at_index`');
        }

        DB::statement("ALTER TABLE `failed_jobs`
            MODIFY `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Connessione di coda su cui il job è stato eseguito',
            MODIFY `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome della coda del job fallito'");
    }

    private function recreateWithBigintIds(string $table): void
    {
        $core = (string) config('database.connections.core.database');

        if (! Schema::hasTable($table)
            || Schema::getColumnType($table, 'id') !== 'char'
            || $core === '' || $core === DB::connection()->getDatabaseName()
        ) {
            return;
        }

        Schema::drop($table);
        DB::statement("CREATE TABLE `{$table}` LIKE `{$core}`.`{$table}`");
    }
};
