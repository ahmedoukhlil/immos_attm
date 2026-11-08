<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gesimmo', function (Blueprint $table) {
            $table->decimal('valeur_acquisition', 15, 2)->nullable()->after('Observations');
            $table->date('date_mise_en_service')->nullable()->after('valeur_acquisition');
        });
    }

    public function down(): void
    {
        Schema::table('gesimmo', function (Blueprint $table) {
            $table->dropColumn(['valeur_acquisition', 'date_mise_en_service']);
        });
    }
};
