<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numéro d'article par désignation : Ordinateur 1, 2, 3… puis Imprimante 1, 2, 3…
 * L'identifiant (numéro d'ordre) reste la clé de l'immobilisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->unsignedInteger('numero_article')->nullable()->after('designation_id');
        });

        // Numérotation de l'existant dans l'ordre des identifiants, archives comprises.
        $compteurs = [];

        DB::table('immobilisations')
            ->orderBy('id')
            ->select(['id', 'designation_id'])
            ->chunkById(500, function ($biens) use (&$compteurs): void {
                foreach ($biens as $bien) {
                    $compteurs[$bien->designation_id] = ($compteurs[$bien->designation_id] ?? 0) + 1;

                    DB::table('immobilisations')
                        ->where('id', $bien->id)
                        ->update(['numero_article' => $compteurs[$bien->designation_id]]);
                }
            });

        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->unique(['designation_id', 'numero_article'], 'immobilisations_designation_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->dropUnique('immobilisations_designation_numero_unique');
            $table->dropColumn('numero_article');
        });
    }
};
