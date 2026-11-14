<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->string('immatriculation', 100)->nullable()->after('numero_chassis');
            foreach (['site_id', 'categorie_id', 'designation_id', 'etat_id'] as $column) {
                $table->unsignedInteger($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Restaurer NOT NULL nécessiterait de renseigner les lignes incomplètes.
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->dropColumn('immatriculation');
        });
    }
};
