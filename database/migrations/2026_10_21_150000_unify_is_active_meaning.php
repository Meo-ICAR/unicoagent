<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uniforma il significato di is_active: 1 = attiva, 0 = non attiva (e non più selezionabile).
 * Cambia solo il commento della colonna, tipo e default restano quelli che sono. Vale per le tabelle di unicoagent
 * e per companies di unicosuite (connessione `core`), dove il default resta quello del core.
 */
return new class extends Migration
{
    private const COMMENTS = [
        'companies' => 'Società attiva (1) o non attiva (0); se non attiva non è più selezionabile',
        'finanziamento_documents' => 'Voce di catalogo attiva (1) o non attiva (0); se non attiva non è più selezionabile',
        'flow_checks' => 'Controllo attivo (1) o non attivo (0); se non attivo non è più selezionabile',
        'flows' => 'Percorso di dialogo attivo (1) o non attivo (0); se non attivo non è più selezionabile',
        'products' => 'Prodotto attivo (1) o non attivo (0); se non attivo non è più selezionabile',
    ];

    public function up(): void
    {
        foreach (self::COMMENTS as $table => $comment) {
            $this->comment(null, $table, $comment);
        }

        if ((string) config('database.connections.core.database') !== DB::connection()->getDatabaseName()) {
            $this->comment('core', 'companies', self::COMMENTS['companies']);
        }
    }

    public function down(): void
    {
        // Solo commenti: nulla da ripristinare.
    }

    private function comment(?string $connection, string $table, string $comment): void
    {
        $schema = Schema::connection($connection);
        if (! $schema->hasColumn($table, 'is_active')) {
            return;
        }

        $db = DB::connection($connection);
        $column = $db->selectOne(
            'select column_type as column_type, is_nullable as is_nullable, column_default as column_default from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?',
            [$db->getDatabaseName(), $table, 'is_active'],
        );

        $definition = $column->column_type
            .($column->is_nullable === 'YES' ? ' NULL' : ' NOT NULL')
            .($column->column_default !== null ? ' DEFAULT '.$db->getPdo()->quote((string) $column->column_default) : '');

        $db->statement("ALTER TABLE `{$table}` MODIFY `is_active` {$definition} COMMENT ".$db->getPdo()->quote($comment));
    }
};
