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
        Schema::create('biens', function (Blueprint $table) {
            $table->id();
            $table->string('code_inventaire', 50)->unique();
            $table->string('designation', 100);
            $table->date('date_acquisition');
            $table->enum('nature', ['mobilier', 'informatique', 'vehicule', 'materiel']);
            $table->string('service_usager', 100);
            $table->foreignId('localisation_id')->constrained('localisations')->onDelete('restrict');
            $table->decimal('valeur_acquisition', 10, 2);
            $table->enum('etat', ['neuf', 'bon', 'moyen', 'mauvais', 'reforme']);
            $table->string('qr_code_path', 100)->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('code_inventaire');
            $table->index('localisation_id');
            $table->index('nature');
            $table->index('service_usager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biens');
    }
};
