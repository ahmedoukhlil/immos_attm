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
        if (!Schema::hasTable('entree')) {
            Schema::create('entree', function (Blueprint $table) {
            $table->integer('idEntree')->autoIncrement();
            $table->integer('idProduit');
            $table->integer('idEmplacement');
            $table->date('DateEntree');
            $table->decimal('Quantite', 10, 2);
            
            $table->foreign('idProduit')->references('idProduit')->on('produits');
            $table->foreign('idEmplacement')->references('idEmplacement')->on('emplacement');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entree');
    }
};
