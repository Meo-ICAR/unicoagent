<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
