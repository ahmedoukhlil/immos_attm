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
        if (Schema::hasTable('inventaire_localisations')) {
            return;
        }

        Schema::create('inventaire_localisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->onDelete('cascade');
            $table->integer('localisation_id')->comment('Référence à localisation.idLocalisation');
            $table->dateTime('date_debut_scan')->nullable();
            $table->dateTime('date_fin_scan')->nullable();
            $table->enum('statut', ['en_attente', 'en_cours', 'termine'])->default('en_attente');
            $table->integer('nombre_biens_attendus')->default(0);
            $table->integer('nombre_biens_scannes')->default(0);
            $table->integer('user_id')->nullable()->comment('Référence à users.idUser');
            $table->timestamps();

            // Index et contraintes
            $table->unique(['inventaire_id', 'localisation_id']);
            $table->index('statut');
            $table->index('user_id');
            $table->index('localisation_id');

            // Clé étrangère vers localisation.idLocalisation (pas de constraint car table différente)
            // On utilise un index simple car la contrainte foreign key nécessiterait une configuration spéciale
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaire_localisations');
    }
};
