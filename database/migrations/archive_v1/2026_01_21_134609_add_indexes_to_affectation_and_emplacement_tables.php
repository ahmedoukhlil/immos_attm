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
        if (! Schema::hasIndex('affectation', 'affectation_idlocalisation_index')) {
            Schema::table('affectation', function (Blueprint $table): void {
                $table->index('idLocalisation', 'affectation_idlocalisation_index');
            });
        }

        if (! Schema::hasIndex('emplacement', 'emplacement_idlocalisation_index')) {
            Schema::table('emplacement', function (Blueprint $table): void {
                $table->index('idLocalisation', 'emplacement_idlocalisation_index');
            });
        }

        if (! Schema::hasIndex('emplacement', 'emplacement_idaffectation_index')) {
            Schema::table('emplacement', function (Blueprint $table): void {
                $table->index('idAffectation', 'emplacement_idaffectation_index');
            });
        }

        if (! Schema::hasIndex('emplacement', 'emplacement_localisation_affectation_index')) {
            Schema::table('emplacement', function (Blueprint $table): void {
                $table->index(['idLocalisation', 'idAffectation'], 'emplacement_localisation_affectation_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('affectation', 'affectation_idlocalisation_index')) {
            Schema::table('affectation', function (Blueprint $table): void {
                $table->dropIndex('affectation_idlocalisation_index');
            });
        }

        foreach ([
            'emplacement_idlocalisation_index',
            'emplacement_idaffectation_index',
            'emplacement_localisation_affectation_index',
        ] as $index) {
            if (Schema::hasIndex('emplacement', $index)) {
                Schema::table('emplacement', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }
    }
};
