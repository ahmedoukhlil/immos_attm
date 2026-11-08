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
        if (! Schema::hasTable('inventaire_localisations')) {
            Schema::create('inventaire_localisations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventaire_id')->constrained('inventaires')->cascadeOnDelete();
                $table->integer('localisation_id');
                $table->dateTime('date_debut_scan')->nullable();
                $table->dateTime('date_fin_scan')->nullable();
                $table->enum('statut', ['en_attente', 'en_cours', 'termine'])->default('en_attente');
                $table->integer('nombre_biens_attendus')->default(0);
                $table->integer('nombre_biens_scannes')->default(0);
                $table->integer('user_id')->nullable();
                $table->timestamps();

                $table->foreign('localisation_id')->references('idLocalisation')->on('localisation')->restrictOnDelete();
                $table->foreign('user_id')->references('idUser')->on('users')->nullOnDelete();
                $table->unique(['inventaire_id', 'localisation_id']);
                $table->index('statut');
                $table->index('user_id');
                $table->index('localisation_id');
            });
        }

        if (Schema::hasTable('inventaire_scans')) {
            return;
        }

        Schema::create('inventaire_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->onDelete('cascade');
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->onDelete('cascade');
            $table->integer('bien_id')->comment('Référence à biens.id');
            $table->dateTime('date_scan');
            $table->enum('statut_scan', ['present', 'deplace', 'absent', 'deteriore']);
            $table->integer('localisation_reelle_id')->nullable()->comment('Référence à localisation.idLocalisation');
            $table->enum('etat_constate', ['bon_etat', 'neuf', 'defectueux']);
            $table->text('commentaire')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->integer('user_id')->nullable()->comment('Référence à users.idUser');
            $table->timestamps();

            // Index et contraintes
            $table->foreign('bien_id')->references('NumOrdre')->on('gesimmo')->restrictOnDelete();
            $table->foreign('localisation_reelle_id')->references('idLocalisation')->on('localisation')->nullOnDelete();
            $table->foreign('user_id')->references('idUser')->on('users')->nullOnDelete();
            $table->index('inventaire_id');
            $table->index('inventaire_localisation_id');
            $table->index('bien_id');
            $table->index('localisation_reelle_id');
            $table->index('user_id');
            $table->index('statut_scan');
            $table->index('date_scan');

            // Un bien ne peut être scanné qu'une seule fois par inventaire
            $table->unique(['inventaire_id', 'bien_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaire_scans');
    }
};
