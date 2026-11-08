<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schéma v2 : une ligne par immobilisation, avec les colonnes du fichier
 * « Inventaire Immo » (descriptif, codes des inventaires précédents,
 * code proposé, autres informations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immobilisations', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('categorie_id');
            $table->unsignedInteger('designation_id');
            $table->unsignedInteger('etat_id');
            $table->string('descriptif', 255)->nullable();
            $table->string('code_dernier_inventaire', 40)->nullable()->index();
            $table->string('code_premier_inventaire', 40)->nullable()->index();
            $table->string('code_propose', 40)->nullable()->unique();
            $table->text('autres_informations')->nullable();
            // Rend l'import Excel rejouable : une ligne source déjà importée est ignorée.
            $table->string('import_ref', 120)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('sites')->restrictOnDelete();
            $table->foreign('categorie_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('designation_id')->references('id')->on('designations')->restrictOnDelete();
            $table->foreign('etat_id')->references('id')->on('etats')->restrictOnDelete();
            $table->index(['deleted_at', 'id'], 'immobilisations_visibility_order_index');
        });

        Schema::create('codes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('immobilisation_id');
            $table->text('barcode')->nullable();

            $table->foreign('immobilisation_id')->references('id')->on('immobilisations')->cascadeOnDelete();
        });

        Schema::create('historique_transferts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('immobilisation_id');
            $table->unsignedInteger('ancien_site_id')->nullable();
            $table->string('ancien_site_libelle', 150)->nullable();
            $table->string('ancien_localisation_libelle', 150)->nullable();
            $table->unsignedInteger('nouveau_site_id');
            $table->string('nouveau_site_libelle', 150);
            $table->string('nouveau_localisation_libelle', 150);
            $table->unsignedInteger('transfert_par');
            $table->dateTime('date_transfert');
            $table->text('raison')->nullable();
            $table->string('groupe_transfert_id', 50)->nullable();
            $table->timestamps();

            $table->foreign('immobilisation_id')->references('id')->on('immobilisations')->cascadeOnDelete();
            $table->foreign('ancien_site_id')->references('id')->on('sites')->nullOnDelete();
            $table->foreign('nouveau_site_id')->references('id')->on('sites')->restrictOnDelete();
            $table->foreign('transfert_par')->references('idUser')->on('users')->restrictOnDelete();
            $table->index('date_transfert');
            $table->index('groupe_transfert_id');
        });

        Schema::create('etiquette_impressions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('generation_uuid')->index();
            $table->unsignedInteger('immobilisation_id');
            $table->foreignId('designation_composant_id')->nullable()->constrained('designation_composants')->nullOnDelete();
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('user_id');
            $table->string('code_etiquette', 100);
            $table->string('nom_composant', 100)->nullable();
            $table->string('mode', 30);
            $table->unsignedSmallInteger('exemplaires')->default(1);
            $table->string('statut', 30)->default('generee');
            $table->timestamp('generee_at');
            $table->timestamp('imprimee_at')->nullable();
            $table->timestamps();

            $table->foreign('immobilisation_id')->references('id')->on('immobilisations')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('idUser')->on('users')->restrictOnDelete();
            $table->index(['immobilisation_id', 'code_etiquette', 'statut'], 'etiquette_impression_lookup');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 50);
            $table->string('auditable_type', 150);
            $table->string('auditable_id', 100);
            $table->unsignedInteger('actor_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('etiquette_impressions');
        Schema::dropIfExists('historique_transferts');
        Schema::dropIfExists('codes');
        Schema::dropIfExists('immobilisations');
    }
};
