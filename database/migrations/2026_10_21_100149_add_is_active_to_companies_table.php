<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('companies', 'is_active')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('type')->comment('Azienda attiva (1) o disattivata (0)');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'is_active')) {
            Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('is_active'));
        }
    }
};
