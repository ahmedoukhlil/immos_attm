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
        if (!Schema::hasTable('sourcefinancement')) {
            Schema::create('sourcefinancement', function (Blueprint $table) {
            $table->integer('idSF')->autoIncrement();
            $table->string('SourceFin');
            $table->string('CodeSourceFin')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sourcefinancement');
    }
};
