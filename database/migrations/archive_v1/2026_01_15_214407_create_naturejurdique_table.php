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
        if (!Schema::hasTable('naturejurdique')) {
            Schema::create('naturejurdique', function (Blueprint $table) {
            $table->integer('idNatJur')->autoIncrement();
            $table->string('NatJur');
            $table->string('CodeNatJur')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('naturejurdique');
    }
};
