<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->string('code_immo', 100)->nullable()->after('numero_attm')->index();
        });
    }

    public function down(): void
    {
        Schema::table('immobilisations', function (Blueprint $table): void {
            $table->dropIndex(['code_immo']);
            $table->dropColumn('code_immo');
        });
    }
};
