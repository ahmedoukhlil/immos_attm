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
        if (! Schema::hasTable('gesimmo')) {
            Schema::create('gesimmo', function (Blueprint $table) {
                $table->integer('NumOrdre')->autoIncrement();
                $table->integer('idDesignation');
                $table->integer('idCategorie');
                $table->integer('idEtat');
                $table->integer('idEmplacement');
                $table->integer('idNatJur');
                $table->integer('idSF');
                $table->unsignedSmallInteger('DateAcquisition')->nullable();
                $table->text('Observations')->nullable();

                $table->foreign('idDesignation')->references('id')->on('designation');
                $table->foreign('idCategorie')->references('idCategorie')->on('categorie');
                $table->foreign('idEtat')->references('idEtat')->on('etat');
                $table->foreign('idEmplacement')->references('idEmplacement')->on('emplacement');
                $table->foreign('idNatJur')->references('idNatJur')->on('naturejurdique');
                $table->foreign('idSF')->references('idSF')->on('sourcefinancement');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gesimmo');
    }
};
