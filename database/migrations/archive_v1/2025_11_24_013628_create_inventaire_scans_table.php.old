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
        Schema::create('inventaire_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->onDelete('cascade');
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->onDelete('cascade');
            $table->foreignId('bien_id')->constrained('biens')->onDelete('restrict');
            $table->dateTime('date_scan');
            $table->enum('statut_scan', ['present', 'deplace', 'absent', 'deteriore']);
            $table->foreignId('localisation_reelle_id')->nullable()->constrained('localisations')->onDelete('set null');
            $table->enum('etat_constate', ['neuf', 'bon', 'moyen', 'mauvais']);
            $table->text('commentaire')->nullable();
            $table->string('photo_path', 100)->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            // Index et contraintes
            $table->index('inventaire_id');
            $table->index('bien_id');
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
