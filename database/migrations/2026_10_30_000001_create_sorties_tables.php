<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sorties d'immobilisations (réforme, mise au rebut, cession, perte, vol…).
 * Une décision regroupe les biens d'un même motif ; elle n'agit sur le
 * registre qu'une fois validée. Le bien sorti reste en base avec sa décision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorties', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->string('motif', 30);
            $table->date('date_decision')->nullable();
            $table->unsignedBigInteger('inventaire_id')->nullable();
            $table->text('observations')->nullable();
            $table->string('statut', 20)->default('brouillon');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('validee_par')->nullable();
            $table->dateTime('validee_at')->nullable();
            $table->timestamps();

            $table->foreign('inventaire_id')->references('id')->on('inventaires')->nullOnDelete();
            $table->index(['statut', 'motif']);
        });

        Schema::create('sortie_immobilisations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sortie_id')->constrained('sorties')->cascadeOnDelete();
            $table->unsignedInteger('immobilisation_id');
            // Photographie du bien au moment de la proposition, pour le procès-verbal.
            $table->string('code', 60)->nullable();
            $table->string('designation', 255)->nullable();
            $table->string('categorie', 255)->nullable();
            $table->string('site', 150)->nullable();
            $table->string('localisation', 150)->nullable();
            $table->string('etat', 100)->nullable();
            $table->string('constat', 30)->nullable();
            $table->timestamps();

            $table->foreign('immobilisation_id')->references('id')->on('immobilisations')->restrictOnDelete();
            $table->unique(['sortie_id', 'immobilisation_id']);
        });

        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->unsignedBigInteger('sortie_id')->nullable()->after('autres_informations');
            $table->date('date_sortie')->nullable()->after('sortie_id');

            $table->foreign('sortie_id')->references('id')->on('sorties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->dropForeign(['sortie_id']);
            $table->dropColumn(['sortie_id', 'date_sortie']);
        });

        Schema::dropIfExists('sortie_immobilisations');
        Schema::dropIfExists('sorties');
    }
};
