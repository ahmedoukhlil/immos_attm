<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaires', function (Blueprint $table) {
            $table->id();
            $table->year('annee')->unique();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['en_preparation', 'en_cours', 'termine', 'cloture'])->default('en_preparation');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->index('annee');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaires');
    }
};
