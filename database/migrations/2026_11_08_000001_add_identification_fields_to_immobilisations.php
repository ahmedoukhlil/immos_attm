<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->string('marque', 100)->nullable()->after('descriptif');
            $table->string('modele', 100)->nullable()->after('marque');
            $table->string('numero_chassis', 100)->nullable()->after('modele')->index();
            $table->string('numero_attm', 100)->nullable()->after('numero_chassis')->index();
        });
    }

    public function down(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->dropIndex(['numero_chassis']);
            $table->dropIndex(['numero_attm']);
            $table->dropColumn(['marque', 'modele', 'numero_chassis', 'numero_attm']);
        });
    }
};
