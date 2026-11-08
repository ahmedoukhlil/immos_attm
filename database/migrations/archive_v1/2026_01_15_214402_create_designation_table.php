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
        if (!Schema::hasTable('designation')) {
            Schema::create('designation', function (Blueprint $table) {
                $table->integer('id')->autoIncrement();
                $table->string('designation');
                $table->string('CodeDesignation')->nullable();
                $table->integer('idCat');
                
                $table->foreign('idCat')->references('idCategorie')->on('categorie');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designation');
    }
};
