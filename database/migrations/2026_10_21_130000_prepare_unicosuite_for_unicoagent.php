<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Predispone il database condiviso (connessione `core`, unicosuite) ad accogliere unicoagent in futuro:
 * unicosuite ha già le tabelle e i campi di unicoagent, manca solo la chat WhatsApp. Non cambia la connessione
 * dell'app. Se `core` coincide con il database dell'app (test) la tabella c'è già e non si fa nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $core = Schema::connection('core');

        if ($core->hasTable('conversation_messages')) {
            return;
        }

        $core->create('conversation_messages', function (Blueprint $table) {
            $table->comment('Messaggi della chat WhatsApp di ogni conversazione (testo cifrato)');
            $table->id()->comment('Identificativo del messaggio');
            $table->foreignId('conversation_id')->nullable()->comment('Conversazione a cui appartiene il messaggio')->constrained()->cascadeOnDelete();
            $table->string('wa_number')->index()->comment('Numero WhatsApp dell\'agente con cui si scambia il messaggio');
            $table->string('direction', 3)->comment('Verso: in = scritto dall\'agente, out = inviato dal bot');
            $table->text('body')->comment('Testo del messaggio, cifrato');
            $table->timestamp('created_at')->nullable()->comment('Data e ora del messaggio');
            $table->timestamp('updated_at')->nullable()->comment('Ultima modifica del record');
        });
    }

    public function down(): void
    {
        if (DB::connection('core')->getDatabaseName() !== DB::connection()->getDatabaseName()) {
            Schema::connection('core')->dropIfExists('conversation_messages');
        }
    }
};
