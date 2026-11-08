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
        if (!Schema::hasTable('sortie')) {
            Schema::create('sortie', function (Blueprint $table) {
            $table->integer('idSortie')->autoIncrement();
            $table->integer('idProduit');
            $table->decimal('Quantite', 10, 2);
            $table->dateTime('DateSortie');
            $table->string('SrvcDmndr')->nullable();
            $table->text('Observations')->nullable();
            
            $table->foreign('idProduit')->references('idProduit')->on('produits');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sortie');
    }
};
