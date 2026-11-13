<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('immobilisations', 'photo_path')) {
            Schema::table('immobilisations', function (Blueprint $table): void {
                $table->string('photo_path', 255)->nullable()->after('code_immo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('immobilisations', 'photo_path')) {
            Schema::table('immobilisations', function (Blueprint $table): void {
                $table->dropColumn('photo_path');
            });
        }
    }
};
