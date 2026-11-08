<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventaire_emplacements', function (Blueprint $table): void {
            $table->string('finalization_key', 150)->nullable()->after('date_fin_scan');
            $table->unique('finalization_key', 'inv_emplacements_finalization_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventaire_emplacements', function (Blueprint $table): void {
            $table->dropUnique('inv_emplacements_finalization_key_unique');
            $table->dropColumn('finalization_key');
        });
    }
};
