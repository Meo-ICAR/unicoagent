<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * unicoloan è dismesso e le sue API per gli agenti sono passate a unicofinance: le aziende che usavano il driver «unicoloan»
 * passano a «unicofinance». La configurazione resta (stesse chiavi), ma indirizzo, token e segreto vanno aggiornati con
 * quelli di unicofinance.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->where('crm_driver', 'unicoloan')->update(['crm_driver' => 'unicofinance']);
    }

    public function down(): void
    {
        DB::table('companies')->where('crm_driver', 'unicofinance')->update(['crm_driver' => 'unicoloan']);
    }
};
