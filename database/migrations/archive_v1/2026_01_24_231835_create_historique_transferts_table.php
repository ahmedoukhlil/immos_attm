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
        Schema::create('historique_transferts', function (Blueprint $table) {
            $table->id();
            
            // Immobilisation transférée
            $table->integer('NumOrdre');
            
            // Ancien emplacement
            $table->integer('ancien_idEmplacement')->nullable();
            $table->string('ancien_emplacement_libelle')->nullable();
            $table->string('ancien_affectation_libelle')->nullable();
            $table->string('ancien_localisation_libelle')->nullable();
            
            // Nouveau emplacement
            $table->integer('nouveau_idEmplacement');
            $table->string('nouveau_emplacement_libelle');
            $table->string('nouveau_affectation_libelle');
            $table->string('nouveau_localisation_libelle');
            
            // Utilisateur et date
            $table->integer('transfert_par'); // idUser
            $table->dateTime('date_transfert');
            
            // Raison du transfert (optionnel)
            $table->text('raison')->nullable();
            
            // ID de groupe pour regrouper les transferts effectués ensemble
            $table->string('groupe_transfert_id', 50)->nullable();
            
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index('NumOrdre');
            $table->index('date_transfert');
            $table->index('groupe_transfert_id');
            $table->index('transfert_par');
            
            // Clés étrangères
            $table->foreign('NumOrdre')->references('NumOrdre')->on('gesimmo')->onDelete('cascade');
            $table->foreign('ancien_idEmplacement')->references('idEmplacement')->on('emplacement')->onDelete('set null');
            $table->foreign('nouveau_idEmplacement')->references('idEmplacement')->on('emplacement')->onDelete('restrict');
            $table->foreign('transfert_par')->references('idUser')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_transferts');
    }
};
