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
        if (!Schema::hasTable('localisation')) {
            Schema::create('localisation', function (Blueprint $table) {
            $table->integer('idLocalisation')->autoIncrement();
            $table->string('Localisation');
            $table->string('CodeLocalisation')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('localisation');
    }
};
