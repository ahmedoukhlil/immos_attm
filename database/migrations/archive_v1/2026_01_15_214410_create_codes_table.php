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
        if (!Schema::hasTable('codes')) {
            Schema::create('codes', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('idGesimmo');
            $table->text('barcode')->nullable();
            
            $table->foreign('idGesimmo')->references('NumOrdre')->on('gesimmo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codes');
    }
};
