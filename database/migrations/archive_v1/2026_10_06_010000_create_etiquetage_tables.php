<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('designation', 'est_compose')) {
            Schema::table('designation', function (Blueprint $table): void {
                $table->boolean('est_compose')->default(false)->after('idCat');
            });
        }

        if (! Schema::hasTable('designation_composants')) {
            Schema::create('designation_composants', function (Blueprint $table): void {
                $table->id();
                $table->integer('designation_id');
                $table->string('nom');
                $table->string('code', 20);
                $table->unsignedSmallInteger('quantite')->default(1);
                $table->boolean('obligatoire')->default(true);
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->timestamps();

                $table->foreign('designation_id')->references('id')->on('designation')->cascadeOnDelete();
                $table->unique(['designation_id', 'code']);
            });
        }

        if (! Schema::hasTable('etiquette_impressions')) {
            Schema::create('etiquette_impressions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('generation_uuid')->index();
                $table->integer('gesimmo_num_ordre');
                $table->foreignId('designation_composant_id')->nullable()->constrained('designation_composants')->nullOnDelete();
                $table->integer('emplacement_id');
                $table->integer('user_id');
                $table->string('code_etiquette');
                $table->string('nom_composant')->nullable();
                $table->string('mode', 30);
                $table->unsignedSmallInteger('exemplaires')->default(1);
                $table->string('statut', 30)->default('generee');
                $table->timestamp('generee_at');
                $table->timestamp('imprimee_at')->nullable();
                $table->timestamps();

                $table->foreign('gesimmo_num_ordre')->references('NumOrdre')->on('gesimmo')->cascadeOnDelete();
                $table->foreign('emplacement_id')->references('idEmplacement')->on('emplacement')->cascadeOnDelete();
                $table->foreign('user_id')->references('idUser')->on('users')->restrictOnDelete();
                $table->index(['gesimmo_num_ordre', 'code_etiquette', 'statut'], 'etiquette_impression_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('etiquette_impressions');
        Schema::dropIfExists('designation_composants');

        if (Schema::hasColumn('designation', 'est_compose')) {
            Schema::table('designation', function (Blueprint $table): void {
                $table->dropColumn('est_compose');
            });
        }
    }
};
