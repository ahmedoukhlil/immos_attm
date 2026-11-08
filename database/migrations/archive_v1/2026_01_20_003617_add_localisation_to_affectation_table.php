<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne existe déjà
        if (Schema::hasColumn('affectation', 'idLocalisation')) {
            return;
        }
        
        Schema::table('affectation', function (Blueprint $table) {
            $table->integer('idLocalisation')->nullable()->after('CodeAffectation');
            
            // Ajouter la clé étrangère si nécessaire
            // $table->foreign('idLocalisation')->references('idLocalisation')->on('localisation')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affectation', function (Blueprint $table) {
            // Supprimer la clé étrangère si elle existe
            // $table->dropForeign(['idLocalisation']);
            $table->dropColumn('idLocalisation');
        });
    }
};
