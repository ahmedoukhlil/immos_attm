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
        if (!Schema::hasTable('etat')) {
            Schema::create('etat', function (Blueprint $table) {
            $table->integer('idEtat')->autoIncrement();
            $table->string('Etat');
            $table->string('CodeEtat')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etat');
    }
};
