<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'inventaire_scans',
            ['inventaire_id', 'statut_scan'],
            'inv_scans_inventory_status_index',
        );
        $this->addIndexIfMissing(
            'inventaire_scans',
            ['inventaire_localisation_id', 'statut_scan'],
            'inv_scans_location_status_index',
        );
        $this->addIndexIfMissing(
            'inventaire_scans',
            ['inventaire_id', 'inventaire_localisation_id'],
            'inv_scans_inventory_location_index',
        );
        $this->addIndexIfMissing(
            'inventaire_localisations',
            ['inventaire_id', 'statut'],
            'inv_locations_inventory_status_index',
        );
        $this->addIndexIfMissing(
            'inventaire_emplacements',
            ['inventaire_localisation_id', 'statut'],
            'inv_places_location_status_index',
        );
    }

    public function down(): void
    {
        $this->dropIndexIfPresent('inventaire_scans', 'inv_scans_inventory_status_index');
        $this->dropIndexIfPresent('inventaire_scans', 'inv_scans_location_status_index');
        $this->dropIndexIfPresent('inventaire_scans', 'inv_scans_inventory_location_index');
        $this->dropIndexIfPresent('inventaire_localisations', 'inv_locations_inventory_status_index');
        $this->dropIndexIfPresent('inventaire_emplacements', 'inv_places_location_status_index');
    }

    /** @param list<string> $columns */
    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
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
