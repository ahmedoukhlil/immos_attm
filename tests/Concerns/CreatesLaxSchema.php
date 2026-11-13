<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schéma v2 simplifié pour les tests : mêmes tables et colonnes que les
 * migrations, mais sans clés étrangères et avec des colonnes facultatives,
 * afin que chaque test ne crée que les données qu'il vérifie.
 */
trait CreatesLaxSchema
{
    /**
     * Ordre de suppression : les tables dépendantes d'abord.
     */
    private const TABLES_V2 = [
        'sortie_immobilisations',
        'sorties',
        'audit_logs',
        'etiquette_impressions',
        'historique_transferts',
        'codes',
        'inventaire_scans',
        'inventaire_exports',
        'inventaire_immobilisations_attendues',
        'inventaire_sites',
        'inventaire_localisation_user',
        'inventaire_localisations',
        'inventaires',
        'immobilisations',
        'designation_composants',
        'sites',
        'designations',
        'categories',
        'etats',
        'localisations',
        'personal_access_tokens',
        'users',
    ];

    protected function createLaxSchema(): void
    {
        foreach (self::TABLES_V2 as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('idUser');
            $table->string('users')->unique();
            $table->string('mdp');
            $table->string('role')->default('agent');
            $table->string('remember_token')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('localisations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('localisation')->nullable();
            $table->string('code')->nullable();
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('localisation_id')->nullable();
            $table->string('site')->nullable();
            $table->string('code')->nullable();
            $table->string('responsable')->nullable();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('categorie')->nullable();
            $table->string('code')->nullable();
            $table->string('imputation_comptable')->nullable();
        });

        Schema::create('designations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('designation')->nullable();
            $table->string('code')->nullable();
            $table->integer('categorie_id')->nullable();
            $table->boolean('est_compose')->default(false);
        });

        Schema::create('designation_composants', function (Blueprint $table): void {
            $table->id();
            $table->integer('designation_id');
            $table->string('nom');
            $table->string('code');
            $table->unsignedSmallInteger('quantite')->default(1);
            $table->boolean('obligatoire')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('etats', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('etat')->nullable();
            $table->string('code')->nullable();
        });

        Schema::create('immobilisations', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('site_id')->nullable();
            $table->integer('categorie_id')->nullable();
            $table->integer('designation_id')->nullable();
            $table->unsignedInteger('numero_article')->nullable();
            $table->unsignedSmallInteger('annee_acquisition')->nullable();
            $table->integer('etat_id')->nullable();
            $table->string('descriptif')->nullable();
            $table->string('marque')->nullable();
            $table->string('modele')->nullable();
            $table->string('numero_chassis')->nullable();
            $table->string('numero_attm')->nullable();
            $table->string('code_immo')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('code_dernier_inventaire')->nullable();
            $table->string('code_premier_inventaire')->nullable();
            $table->string('code_propose')->nullable()->unique();
            $table->text('autres_informations')->nullable();
            $table->string('import_ref')->nullable()->unique();
            $table->unsignedBigInteger('sortie_id')->nullable();
            $table->date('date_sortie')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sorties', function (Blueprint $table): void {
            $table->id();
            $table->string('numero')->unique();
            $table->string('motif');
            $table->date('date_decision')->nullable();
            $table->unsignedBigInteger('inventaire_id')->nullable();
            $table->text('observations')->nullable();
            $table->string('statut')->default('brouillon');
            $table->integer('created_by')->nullable();
            $table->integer('validee_par')->nullable();
            $table->dateTime('validee_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sortie_immobilisations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sortie_id');
            $table->integer('immobilisation_id');
            $table->string('code')->nullable();
            $table->string('designation')->nullable();
            $table->string('categorie')->nullable();
            $table->string('site')->nullable();
            $table->string('localisation')->nullable();
            $table->string('etat')->nullable();
            $table->string('constat')->nullable();
            $table->timestamps();
            $table->unique(['sortie_id', 'immobilisation_id']);
        });

        Schema::create('codes', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('immobilisation_id');
            $table->text('barcode')->nullable();
        });

        Schema::create('historique_transferts', function (Blueprint $table): void {
            $table->id();
            $table->integer('immobilisation_id');
            $table->integer('ancien_site_id')->nullable();
            $table->string('ancien_site_libelle')->nullable();
            $table->string('ancien_localisation_libelle')->nullable();
            $table->integer('nouveau_site_id')->nullable();
            $table->string('nouveau_site_libelle')->nullable();
            $table->string('nouveau_localisation_libelle')->nullable();
            $table->integer('transfert_par')->nullable();
            $table->dateTime('date_transfert')->nullable();
            $table->text('raison')->nullable();
            $table->string('groupe_transfert_id')->nullable();
            $table->timestamps();
        });

        Schema::create('etiquette_impressions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('generation_uuid');
            $table->integer('immobilisation_id');
            $table->integer('designation_composant_id')->nullable();
            $table->integer('site_id');
            $table->integer('user_id');
            $table->string('code_etiquette');
            $table->string('nom_composant')->nullable();
            $table->string('mode');
            $table->unsignedSmallInteger('exemplaires')->default(1);
            $table->string('statut')->default('generee');
            $table->timestamp('generee_at');
            $table->timestamp('imprimee_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event');
            $table->string('auditable_type');
            $table->string('auditable_id');
            $table->unsignedInteger('actor_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('inventaires', function (Blueprint $table): void {
            $table->id();
            $table->integer('annee')->unique();
            $table->date('date_debut')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->date('date_fin')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('statut');
            $table->integer('created_by')->nullable();
            $table->integer('closed_by')->nullable();
            $table->text('observation')->nullable();
            $table->dateTime('referentiel_frozen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventaire_localisations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id');
            $table->integer('localisation_id');
            $table->dateTime('date_debut_scan')->nullable();
            $table->dateTime('date_fin_scan')->nullable();
            $table->string('statut')->default('en_attente');
            $table->integer('nombre_biens_attendus')->default(0);
            $table->integer('nombre_biens_scannes')->default(0);
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('inventaire_localisation_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_localisation_id');
            $table->integer('user_id');
            $table->timestamps();
        });

        Schema::create('inventaire_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_localisation_id');
            $table->integer('site_id');
            $table->string('statut')->default('en_attente');
            $table->dateTime('date_debut_scan')->nullable();
            $table->dateTime('date_fin_scan')->nullable();
            $table->string('finalization_key')->nullable()->unique();
            $table->integer('nombre_biens_attendus')->default(0);
            $table->integer('nombre_biens_scannes')->default(0);
            $table->timestamps();
        });

        Schema::create('inventaire_immobilisations_attendues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id');
            $table->foreignId('inventaire_localisation_id');
            $table->foreignId('inventaire_site_id')->nullable();
            $table->integer('immobilisation_id');
            $table->string('code_propose')->nullable();
            $table->string('designation')->nullable();
            $table->string('categorie')->nullable();
            $table->integer('site_id');
            $table->string('site_code')->nullable();
            $table->string('site_nom')->nullable();
            $table->integer('localisation_id');
            $table->string('localisation_code')->nullable();
            $table->string('localisation_nom')->nullable();
            $table->timestamps();
            $table->unique(['inventaire_id', 'immobilisation_id']);
        });

        Schema::create('inventaire_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id');
            $table->foreignId('inventaire_localisation_id');
            $table->integer('immobilisation_id');
            $table->dateTime('date_scan');
            $table->string('statut_scan');
            $table->integer('localisation_reelle_id')->nullable();
            $table->integer('site_reel_id')->nullable();
            $table->string('etat_constate')->default('bon_etat');
            $table->text('commentaire')->nullable();
            $table->string('photo_path')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
            $table->unique(['inventaire_id', 'immobilisation_id']);
        });

        Schema::create('inventaire_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id');
            $table->unsignedInteger('requested_by');
            $table->string('format');
            $table->boolean('automatic')->default(false);
            $table->string('final_key')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
