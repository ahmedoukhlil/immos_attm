<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'gesimmo',
            ['deleted_at', 'NumOrdre'],
            'gesimmo_visibility_order_index',
        );
        $this->addIndexIfMissing(
            'gesimmo',
            ['DateAcquisition', 'deleted_at', 'NumOrdre'],
            'gesimmo_acquisition_visibility_order_index',
        );
    }

    public function down(): void
    {
        $this->dropIndexIfPresent('gesimmo', 'gesimmo_visibility_order_index');
        $this->dropIndexIfPresent('gesimmo', 'gesimmo_acquisition_visibility_order_index');
    }

    /** @param list<string> $columns */
    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)
            || ! collect($columns)->every(fn (string $column): bool => Schema::hasColumn($tableName, $column))
            || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfPresent(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
