<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventaires', function (Blueprint $table): void {
            $table->timestamp('referentiel_frozen_at')->nullable()->after('observation');
        });

        // La migration précédente a déjà créé l'instantané des inventaires existants,
        // y compris ceux dont le référentiel ne contient aucune ligne.
        DB::table('inventaires')->update(['referentiel_frozen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('inventaires', function (Blueprint $table): void {
            $table->dropColumn('referentiel_frozen_at');
        });
    }
};
