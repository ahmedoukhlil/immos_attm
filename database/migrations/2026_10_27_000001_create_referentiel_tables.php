<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schéma v2 : référentiels calqués sur le fichier « Inventaire Immo ».
 *
 * Localisation > Site (colonnes « Site » et « Responsable »), Catégorie avec
 * son imputation comptable (feuille « Code compta »), Désignation et État.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localisations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('localisation', 150);
            $table->string('code', 30)->nullable()->unique();
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('localisation_id');
            $table->string('site', 150);
            $table->string('code', 30)->nullable()->unique();
            $table->string('responsable', 150)->nullable();

            $table->foreign('localisation_id')->references('id')->on('localisations')->restrictOnDelete();
            $table->unique(['localisation_id', 'site']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('categorie', 150);
            $table->string('code', 10)->unique();
            $table->string('imputation_comptable', 8)->nullable()->unique();
        });

        Schema::create('designations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('designation', 150);
            $table->string('code', 20)->nullable();
            $table->unsignedInteger('categorie_id');
            $table->boolean('est_compose')->default(false);

            $table->foreign('categorie_id')->references('id')->on('categories')->restrictOnDelete();
            $table->index('designation');
        });

        Schema::create('designation_composants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('designation_id');
            $table->string('nom', 100);
            $table->string('code', 20);
            $table->unsignedSmallInteger('quantite')->default(1);
            $table->boolean('obligatoire')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->foreign('designation_id')->references('id')->on('designations')->cascadeOnDelete();
            $table->unique(['designation_id', 'code']);
        });

        Schema::create('etats', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('etat', 50);
            $table->string('code', 10)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etats');
        Schema::dropIfExists('designation_composants');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('localisations');
    }
};
