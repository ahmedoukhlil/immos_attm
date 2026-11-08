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
        if (!Schema::hasTable('emplacement')) {
            Schema::create('emplacement', function (Blueprint $table) {
            $table->integer('idEmplacement')->autoIncrement();
            $table->string('Emplacement');
            $table->string('CodeEmplacement')->nullable();
            $table->integer('idAffectation');
            $table->integer('idLocalisation');
            
            $table->foreign('idAffectation')->references('idAffectation')->on('affectation');
            $table->foreign('idLocalisation')->references('idLocalisation')->on('localisation');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emplacement');
    }
};
