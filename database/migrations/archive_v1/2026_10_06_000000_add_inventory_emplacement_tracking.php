<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaire_emplacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->cascadeOnDelete();
            $table->integer('emplacement_id');
            $table->enum('statut', ['en_attente', 'en_cours', 'termine'])->default('en_attente');
            $table->dateTime('date_debut_scan')->nullable();
            $table->dateTime('date_fin_scan')->nullable();
            $table->unsignedInteger('nombre_biens_attendus')->default(0);
            $table->unsignedInteger('nombre_biens_scannes')->default(0);
            $table->timestamps();

            $table->foreign('emplacement_id')->references('idEmplacement')->on('emplacement')->restrictOnDelete();
            $table->unique(['inventaire_localisation_id', 'emplacement_id'], 'inv_loc_emplacement_unique');
            $table->index(['statut', 'inventaire_localisation_id']);
        });

        Schema::table('inventaire_scans', function (Blueprint $table): void {
            $table->integer('emplacement_reel_id')->nullable()->after('localisation_reelle_id');
            $table->foreign('emplacement_reel_id')->references('idEmplacement')->on('emplacement')->nullOnDelete();
            $table->index('emplacement_reel_id');
        });

        $inventaireLocalisations = DB::table('inventaire_localisations')->get();

        foreach ($inventaireLocalisations as $inventaireLocalisation) {
            $emplacements = DB::table('emplacement')
                ->where('idLocalisation', $inventaireLocalisation->localisation_id)
                ->get(['idEmplacement']);

            foreach ($emplacements as $emplacement) {
                $biensAttendus = DB::table('gesimmo')
                    ->where('idEmplacement', $emplacement->idEmplacement)
                    ->count();

                $biensScannes = DB::table('inventaire_scans')
                    ->join('gesimmo', 'gesimmo.NumOrdre', '=', 'inventaire_scans.bien_id')
                    ->where('inventaire_scans.inventaire_localisation_id', $inventaireLocalisation->id)
                    ->where('gesimmo.idEmplacement', $emplacement->idEmplacement)
                    ->distinct()
                    ->count('inventaire_scans.bien_id');

                DB::table('inventaire_emplacements')->insert([
                    'inventaire_localisation_id' => $inventaireLocalisation->id,
                    'emplacement_id' => $emplacement->idEmplacement,
                    'statut' => $inventaireLocalisation->statut,
                    'date_debut_scan' => $inventaireLocalisation->date_debut_scan,
                    'date_fin_scan' => $inventaireLocalisation->date_fin_scan,
                    'nombre_biens_attendus' => $biensAttendus,
                    'nombre_biens_scannes' => $biensScannes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('inventaire_scans')
            ->join('gesimmo', 'gesimmo.NumOrdre', '=', 'inventaire_scans.bien_id')
            ->where('inventaire_scans.statut_scan', 'present')
            ->select([
                'inventaire_scans.id as scan_id',
                'gesimmo.idEmplacement as emplacement_id',
            ])
            ->orderBy('inventaire_scans.id')
            ->chunk(500, function ($scans): void {
                foreach ($scans->groupBy('emplacement_id') as $emplacementId => $group) {
                    DB::table('inventaire_scans')
                        ->whereIn('id', $group->pluck('scan_id'))
                        ->update(['emplacement_reel_id' => $emplacementId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inventaire_scans', function (Blueprint $table): void {
            $table->dropForeign(['emplacement_reel_id']);
            $table->dropIndex(['emplacement_reel_id']);
            $table->dropColumn('emplacement_reel_id');
        });

        Schema::dropIfExists('inventaire_emplacements');
    }
};
