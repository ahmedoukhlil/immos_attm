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
        Schema::create('inventaire_localisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->onDelete('cascade');
            $table->foreignId('localisation_id')->constrained('localisations')->onDelete('restrict');
            $table->dateTime('date_debut_scan')->nullable();
            $table->dateTime('date_fin_scan')->nullable();
            $table->enum('statut', ['en_attente', 'en_cours', 'termine'])->default('en_attente');
            $table->integer('nombre_biens_attendus')->default(0);
            $table->integer('nombre_biens_scannes')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Index et contraintes
            $table->unique(['inventaire_id', 'localisation_id']);
            $table->index('statut');
            $table->index('user_id');
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
