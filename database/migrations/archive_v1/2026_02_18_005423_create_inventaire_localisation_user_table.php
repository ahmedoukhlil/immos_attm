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
        Schema::create('inventaire_localisation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_localisation_id')->constrained('inventaire_localisations')->onDelete('cascade');
            $table->integer('user_id')->comment('Référence à users.idUser');
            $table->timestamps();

            $table->foreign('user_id')->references('idUser')->on('users')->cascadeOnDelete();
            $table->unique(['inventaire_localisation_id', 'user_id'], 'inv_loc_user_unique');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaire_localisation_user');
    }
};
