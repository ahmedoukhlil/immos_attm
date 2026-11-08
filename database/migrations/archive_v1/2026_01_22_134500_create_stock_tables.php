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
        // 1. Table stock_magasins
        Schema::create('stock_magasins', function (Blueprint $table) {
            $table->id();
            $table->string('magasin')->comment('Nom du magasin');
            $table->string('localisation')->comment('Localisation du magasin');
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        // 2. Table stock_categories
        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        // 3. Table stock_fournisseurs
        Schema::create('stock_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        // 4. Table stock_demandeurs
        Schema::create('stock_demandeurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('poste_service')->comment('Poste/Service/Direction');
            $table->timestamps();
        });

        // 5. Table stock_produits
        Schema::create('stock_produits', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->foreignId('categorie_id')->constrained('stock_categories')->onDelete('restrict');
            $table->foreignId('magasin_id')->constrained('stock_magasins')->onDelete('restrict');
            $table->integer('stock_initial')->default(0);
            $table->integer('stock_actuel')->default(0);
            $table->integer('seuil_alerte')->default(10);
            $table->text('descriptif')->nullable();
            $table->string('stockage')->nullable()->comment('Emplacement précis dans le magasin');
            $table->text('observations')->nullable();
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index('categorie_id', 'idx_produit_categorie');
            $table->index('magasin_id', 'idx_produit_magasin');
        });

        // 6. Table stock_entrees
        Schema::create('stock_entrees', function (Blueprint $table) {
            $table->id();
            $table->date('date_entree');
            $table->string('reference_commande')->nullable();
            $table->foreignId('produit_id')->constrained('stock_produits')->onDelete('restrict');
            $table->foreignId('fournisseur_id')->constrained('stock_fournisseurs')->onDelete('restrict');
            $table->integer('quantite');
            $table->text('observations')->nullable();
            $table->integer('created_by')->comment('ID utilisateur qui a créé l\'entrée');
            $table->timestamps();

            // Clé étrangère vers users.idUser
            $table->foreign('created_by')->references('idUser')->on('users')->onDelete('restrict');

            // Index pour optimiser les requêtes
            $table->index('produit_id', 'idx_entree_produit');
            $table->index('fournisseur_id', 'idx_entree_fournisseur');
            $table->index('created_by', 'idx_entree_created_by');
            $table->index('date_entree', 'idx_entree_date');
        });

        // 7. Table stock_sorties
        Schema::create('stock_sorties', function (Blueprint $table) {
            $table->id();
            $table->date('date_sortie');
            $table->foreignId('produit_id')->constrained('stock_produits')->onDelete('restrict');
            $table->foreignId('demandeur_id')->constrained('stock_demandeurs')->onDelete('restrict');
            $table->integer('quantite');
            $table->text('observations')->nullable();
            $table->integer('created_by')->comment('ID utilisateur qui a créé la sortie');
            $table->timestamps();

            // Clé étrangère vers users.idUser
            $table->foreign('created_by')->references('idUser')->on('users')->onDelete('restrict');

            // Index pour optimiser les requêtes
            $table->index('produit_id', 'idx_sortie_produit');
            $table->index('demandeur_id', 'idx_sortie_demandeur');
            $table->index('created_by', 'idx_sortie_created_by');
            $table->index('date_sortie', 'idx_sortie_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les tables dans l'ordre inverse (à cause des contraintes FK)
        Schema::dropIfExists('stock_sorties');
        Schema::dropIfExists('stock_entrees');
        Schema::dropIfExists('stock_produits');
        Schema::dropIfExists('stock_demandeurs');
        Schema::dropIfExists('stock_fournisseurs');
        Schema::dropIfExists('stock_categories');
        Schema::dropIfExists('stock_magasins');
    }
};
