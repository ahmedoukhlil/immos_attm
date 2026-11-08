<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gesimmo')) {
            return;
        }

        if (! Schema::hasColumn('gesimmo', 'deleted_at')) {
            Schema::table('gesimmo', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        // Les anciennes installations ont créé l'année d'acquisition comme DATE.
        // L'application manipule depuis toujours une année entière (ex. 2024).
        if (Schema::hasColumn('gesimmo', 'DateAcquisition')
            && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            && Schema::getColumnType('gesimmo', 'DateAcquisition') === 'date') {
            if (! Schema::hasColumn('gesimmo', 'DateAcquisition_annee')) {
                DB::statement('ALTER TABLE gesimmo ADD COLUMN DateAcquisition_annee SMALLINT UNSIGNED NULL AFTER DateAcquisition');
            }

            DB::statement('UPDATE gesimmo SET DateAcquisition_annee = NULLIF(YEAR(DateAcquisition), 0)');
            DB::statement('ALTER TABLE gesimmo DROP COLUMN DateAcquisition');
        }

        if (! Schema::hasColumn('gesimmo', 'DateAcquisition')
            && Schema::hasColumn('gesimmo', 'DateAcquisition_annee')
            && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE gesimmo CHANGE DateAcquisition_annee DateAcquisition SMALLINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gesimmo') && Schema::hasColumn('gesimmo', 'deleted_at')) {
            Schema::table('gesimmo', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
