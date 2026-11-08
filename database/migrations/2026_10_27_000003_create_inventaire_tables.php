<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schéma v2 : campagnes d'inventaire (localisations confiées aux agents,
 * sites scannés, référentiel figé des immobilisations attendues, scans, exports).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaires', function (Blueprint $table): void {
            $table->id();
            $table->year('annee')->unique();
            $table->date('date_debut');
            $table->dateTime('started_at')->nullable();
            $table->date('date_fin')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->enum('statut', ['en_preparation', 'en_cours', 'termine', 'cloture'])->default('en_preparation');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('closed_by')->nullable();
            $table->text('observation')->nullable();
            $table->timestamp('referentiel_frozen_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('idUser')->on('users')->restrictOnDelete();
            $table->foreign('closed_by')->references('idUser')->on('users')->nullOnDelete();
            $table->index('statut');
        });

        Schema::create('inventaire_localisations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->cascadeOnDelete();
            $table->unsignedInteger('localisation_id');
            $table->dateTime('date_debut_scan')->nullable();
            $table->dateTime('date_fin_scan')->nullable();
            $table->enum('statut', ['en_attente', 'en_cours', 'termine'])->default('en_attente');
            $table->unsignedInteger('nombre_biens_attendus')->default(0);
            $table->unsignedInteger('nombre_biens_scannes')->default(0);
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('localisation_id')->references('id')->on('localisations')->restrictOnDelete();
            $table->foreign('user_id')->references('idUser')->on('users')->nullOnDelete();
            $table->unique(['inventaire_id', 'localisation_id']);
            $table->index(['inventaire_id', 'statut'], 'inv_locations_inventory_status_index');
            $table->index('user_id');
        });

        Schema::create('inventaire_localisation_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('idUser')->on('users')->cascadeOnDelete();
            $table->unique(['inventaire_localisation_id', 'user_id'], 'inv_loc_user_unique');
            $table->index('user_id');
        });

        Schema::create('inventaire_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->cascadeOnDelete();
            $table->unsignedInteger('site_id');
            $table->enum('statut', ['en_attente', 'en_cours', 'termine'])->default('en_attente');
            $table->dateTime('date_debut_scan')->nullable();
            $table->dateTime('date_fin_scan')->nullable();
            $table->string('finalization_key', 150)->nullable()->unique();
            $table->unsignedInteger('nombre_biens_attendus')->default(0);
            $table->unsignedInteger('nombre_biens_scannes')->default(0);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->restrictOnDelete();
            $table->unique(['inventaire_localisation_id', 'site_id'], 'inv_loc_site_unique');
            $table->index(['inventaire_localisation_id', 'statut'], 'inv_sites_location_status_index');
        });

        Schema::create('inventaire_immobilisations_attendues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inventaire_id');
            $table->unsignedBigInteger('inventaire_localisation_id');
            $table->unsignedBigInteger('inventaire_site_id')->nullable();
            $table->unsignedInteger('immobilisation_id');
            $table->string('code_propose', 40)->nullable();
            $table->string('designation', 150)->nullable();
            $table->string('categorie', 150)->nullable();
            $table->unsignedInteger('site_id');
            $table->string('site_code', 30)->nullable();
            $table->string('site_nom', 150)->nullable();
            $table->unsignedInteger('localisation_id');
            $table->string('localisation_code', 30)->nullable();
            $table->string('localisation_nom', 150)->nullable();
            $table->timestamps();

            // Noms explicites : ceux générés par Laravel dépassent 64 caractères sous MySQL.
            $table->foreign('inventaire_id', 'inv_immo_attendue_inventaire_fk')
                ->references('id')->on('inventaires')->cascadeOnDelete();
            $table->foreign('inventaire_localisation_id', 'inv_immo_attendue_inv_loc_fk')
                ->references('id')->on('inventaire_localisations')->cascadeOnDelete();
            $table->foreign('inventaire_site_id', 'inv_immo_attendue_inv_site_fk')
                ->references('id')->on('inventaire_sites')->nullOnDelete();
            $table->unique(['inventaire_id', 'immobilisation_id'], 'inv_immo_attendue_unique');
            $table->index(['inventaire_id', 'site_id'], 'inv_immo_attendue_site');
            $table->index(['inventaire_id', 'localisation_id'], 'inv_immo_attendue_localisation');
        });

        Schema::create('inventaire_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->cascadeOnDelete();
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->cascadeOnDelete();
            $table->unsignedInteger('immobilisation_id');
            $table->dateTime('date_scan');
            $table->enum('statut_scan', ['present', 'deplace', 'absent', 'deteriore']);
            $table->unsignedInteger('localisation_reelle_id')->nullable();
            $table->unsignedInteger('site_reel_id')->nullable();
            $table->enum('etat_constate', ['bon_etat', 'neuf', 'defectueux'])->default('bon_etat');
            $table->text('commentaire')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('immobilisation_id')->references('id')->on('immobilisations')->restrictOnDelete();
            $table->foreign('localisation_reelle_id')->references('id')->on('localisations')->nullOnDelete();
            $table->foreign('site_reel_id')->references('id')->on('sites')->nullOnDelete();
            $table->foreign('user_id')->references('idUser')->on('users')->nullOnDelete();
            $table->unique(['inventaire_id', 'immobilisation_id']);
            $table->index(['inventaire_id', 'statut_scan'], 'inv_scans_inventory_status_index');
            $table->index(['inventaire_localisation_id', 'statut_scan'], 'inv_scans_location_status_index');
            $table->index(['inventaire_id', 'inventaire_localisation_id'], 'inv_scans_inventory_location_index');
            $table->index('date_scan');
        });

        Schema::create('inventaire_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->cascadeOnDelete();
            $table->unsignedInteger('requested_by');
            $table->string('format', 10);
            $table->boolean('automatic')->default(false);
            $table->string('final_key', 150)->nullable()->unique('inv_exports_final_unique');
            $table->string('status', 20)->default('pending');
            $table->string('disk', 30)->default('local');
            $table->string('path', 500)->nullable();
            $table->string('filename', 255)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['inventaire_id', 'requested_by', 'status'], 'inv_exports_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaire_exports');
        Schema::dropIfExists('inventaire_scans');
        Schema::dropIfExists('inventaire_immobilisations_attendues');
        Schema::dropIfExists('inventaire_sites');
        Schema::dropIfExists('inventaire_localisation_user');
        Schema::dropIfExists('inventaire_localisations');
        Schema::dropIfExists('inventaires');
    }
};
