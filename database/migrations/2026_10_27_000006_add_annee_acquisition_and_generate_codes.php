<?php

use App\Models\Immobilisation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Année d'acquisition et code d'inventaire généré :
 * imputation (3 chiffres) / code désignation / numéro d'article / année, ex. 218/BUR/001/2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('annee_acquisition')->nullable()->after('numero_article');
        });

        $anneeParDefaut = (int) now()->year;
        $designations = DB::table('designations')->get(['id', 'designation', 'code'])->keyBy('id');
        $imputations = DB::table('categories')->pluck('imputation_comptable', 'id');
        $lignes = DB::table('immobilisations')
            ->orderBy('id')
            ->get(['id', 'designation_id', 'categorie_id', 'numero_article', 'code_propose', 'autres_informations']);

        // Les codes sont régénérés : on libère d'abord la contrainte d'unicité.
        DB::table('immobilisations')->update(['code_propose' => null]);

        foreach ($lignes as $ligne) {
            $informations = $ligne->autres_informations;
            $annee = $anneeParDefaut;

            // L'import précédent rangeait l'année lue dans « Code proposé » en observation.
            if ($informations && preg_match("/Année d'acquisition : (\\d{4})/u", $informations, $m)) {
                $annee = (int) $m[1];
                $informations = trim(preg_replace("/\\s*\\|?\\s*Année d'acquisition : \\d{4}\\s*\\|?\\s*/u", ' | ', $informations), ' |') ?: null;
            }

            $designation = $designations->get($ligne->designation_id);
            $code = Immobilisation::formaterCode(
                $imputations->get($ligne->categorie_id),
                $designation?->code,
                $designation?->designation,
                $ligne->numero_article,
                $annee,
            );

            // Un ancien code différent de la règle reste consultable.
            if ($ligne->code_propose && $ligne->code_propose !== $code) {
                $informations = trim(($informations ? $informations.' | ' : '').'Ancien code proposé : '.$ligne->code_propose);
            }

            DB::table('immobilisations')->where('id', $ligne->id)->update([
                'annee_acquisition' => $annee,
                'code_propose' => $code,
                'autres_informations' => $informations,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->dropColumn('annee_acquisition');
        });
    }
};
