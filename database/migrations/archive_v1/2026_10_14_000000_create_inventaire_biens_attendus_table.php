<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaire_biens_attendus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->cascadeOnDelete();
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->cascadeOnDelete();
            $table->foreignId('inventaire_emplacement_id')->nullable()->constrained('inventaire_emplacements')->nullOnDelete();
            $table->integer('bien_id');
            $table->integer('num_ordre');
            $table->string('code_inventaire', 255)->nullable();
            $table->string('designation', 255)->nullable();
            $table->string('categorie', 255)->nullable();
            $table->integer('emplacement_id');
            $table->string('emplacement_code', 255)->nullable();
            $table->string('emplacement_nom', 255)->nullable();
            $table->integer('localisation_id');
            $table->string('localisation_code', 255)->nullable();
            $table->string('localisation_nom', 255)->nullable();
            $table->string('affectation', 255)->nullable();
            $table->decimal('valeur_acquisition', 15, 2)->nullable();
            $table->string('date_acquisition', 20)->nullable();
            $table->timestamps();

            $table->unique(['inventaire_id', 'bien_id'], 'inv_bien_attendu_unique');
            $table->index(['inventaire_id', 'emplacement_id'], 'inv_bien_attendu_emplacement');
            $table->index(['inventaire_id', 'localisation_id'], 'inv_bien_attendu_localisation');
        });

        // Reconstituer un instantané initial pour les inventaires déjà existants.
        DB::table('inventaires')->orderBy('id')->chunkById(100, function ($inventaires): void {
            foreach ($inventaires as $inventaire) {
                $lignes = DB::table('inventaire_localisations as il')
                    ->join('localisation as l', 'l.idLocalisation', '=', 'il.localisation_id')
                    ->join('emplacement as e', 'e.idLocalisation', '=', 'l.idLocalisation')
                    ->join('gesimmo as g', 'g.idEmplacement', '=', 'e.idEmplacement')
                    ->leftJoin('inventaire_emplacements as ie', function ($join): void {
                        $join->on('ie.inventaire_localisation_id', '=', 'il.id')
                            ->on('ie.emplacement_id', '=', 'e.idEmplacement');
                    })
                    ->leftJoin('designation as d', 'd.id', '=', 'g.idDesignation')
                    ->leftJoin('categorie as c', 'c.idCategorie', '=', 'g.idCategorie')
                    ->leftJoin('affectation as a', 'a.idAffectation', '=', 'e.idAffectation')
                    ->where('il.inventaire_id', $inventaire->id)
                    ->select([
                        'il.id as inventaire_localisation_id',
                        'ie.id as inventaire_emplacement_id',
                        'g.NumOrdre',
                        'g.valeur_acquisition',
                        'g.DateAcquisition',
                        'd.designation',
                        'c.Categorie',
                        'e.idEmplacement',
                        'e.CodeEmplacement',
                        'e.Emplacement',
                        'l.idLocalisation',
                        'l.CodeLocalisation',
                        'l.Localisation',
                        'a.Affectation',
                    ])
                    ->get();

                $now = now();
                $insertions = $lignes->map(fn ($ligne): array => [
                    'inventaire_id' => $inventaire->id,
                    'inventaire_localisation_id' => $ligne->inventaire_localisation_id,
                    'inventaire_emplacement_id' => $ligne->inventaire_emplacement_id,
                    'bien_id' => $ligne->NumOrdre,
                    'num_ordre' => $ligne->NumOrdre,
                    'code_inventaire' => 'GS'.$ligne->NumOrdre,
                    'designation' => $ligne->designation,
                    'categorie' => $ligne->Categorie,
                    'emplacement_id' => $ligne->idEmplacement,
                    'emplacement_code' => $ligne->CodeEmplacement,
                    'emplacement_nom' => $ligne->Emplacement,
                    'localisation_id' => $ligne->idLocalisation,
                    'localisation_code' => $ligne->CodeLocalisation,
                    'localisation_nom' => $ligne->Localisation,
                    'affectation' => $ligne->Affectation,
                    'valeur_acquisition' => $ligne->valeur_acquisition,
                    'date_acquisition' => $ligne->DateAcquisition,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                foreach (array_chunk($insertions, 500) as $chunk) {
                    DB::table('inventaire_biens_attendus')->insertOrIgnore($chunk);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaire_biens_attendus');
    }
};
