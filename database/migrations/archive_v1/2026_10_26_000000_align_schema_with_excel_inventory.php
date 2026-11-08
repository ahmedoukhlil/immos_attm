<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne le schéma sur le fichier « Inventaire Immo » :
 * imputation comptable par catégorie, descriptif et anciens codes d'inventaire
 * par immobilisation, responsable par emplacement et états Moyen / Mauvais.
 *
 * Les longueurs sont explicites car Schema::defaultStringLength(100) est actif.
 */
return new class extends Migration
{
    /**
     * Comptes du plan comptable fournis dans la feuille « Code compta ».
     */
    private const COMPTES_PAR_CATEGORIE = [
        'MEXP' => '21400000',
        'MTRP' => '21500000',
        'MINF' => '21600000',
        'MMOB' => '21800000',
    ];

    public function up(): void
    {
        Schema::table('categorie', function (Blueprint $table): void {
            $table->string('compte_comptable', 8)->nullable()->after('CodeCategorie');
        });

        Schema::table('gesimmo', function (Blueprint $table): void {
            $table->string('descriptif', 255)->nullable()->after('Observations');
            $table->string('code_inventaire', 40)->nullable()->unique()->after('descriptif');
            $table->string('code_premier_inventaire', 40)->nullable()->index()->after('code_inventaire');
            $table->string('code_dernier_inventaire', 40)->nullable()->index()->after('code_premier_inventaire');
            // Rend l'import Excel rejouable : une ligne source déjà importée est ignorée.
            $table->string('import_ref', 120)->nullable()->unique()->after('code_dernier_inventaire');
        });

        Schema::table('emplacement', function (Blueprint $table): void {
            $table->string('responsable', 150)->nullable()->after('CodeEmplacement');
        });

        foreach (self::COMPTES_PAR_CATEGORIE as $code => $compte) {
            DB::table('categorie')
                ->where('CodeCategorie', $code)
                ->whereNull('compte_comptable')
                ->update(['compte_comptable' => $compte]);
        }

        foreach (['MOY' => 'Moyen', 'MAUV' => 'Mauvais'] as $code => $libelle) {
            if (! DB::table('etat')->where('CodeEtat', $code)->exists()) {
                DB::table('etat')->insert(['CodeEtat' => $code, 'Etat' => $libelle]);
            }
        }
    }

    public function down(): void
    {
        // Les états ajoutés ne sont retirés que s'ils ne sont référencés par aucun bien.
        DB::table('etat')
            ->whereIn('CodeEtat', ['MOY', 'MAUV'])
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('gesimmo')
                ->whereColumn('gesimmo.idEtat', 'etat.idEtat'))
            ->delete();

        Schema::table('emplacement', function (Blueprint $table): void {
            $table->dropColumn('responsable');
        });

        Schema::table('gesimmo', function (Blueprint $table): void {
            $table->dropUnique(['code_inventaire']);
            $table->dropIndex(['code_premier_inventaire']);
            $table->dropIndex(['code_dernier_inventaire']);
            $table->dropUnique(['import_ref']);
            $table->dropColumn([
                'descriptif',
                'code_inventaire',
                'code_premier_inventaire',
                'code_dernier_inventaire',
                'import_ref',
            ]);
        });

        Schema::table('categorie', function (Blueprint $table): void {
            $table->dropColumn('compte_comptable');
        });
    }
};
