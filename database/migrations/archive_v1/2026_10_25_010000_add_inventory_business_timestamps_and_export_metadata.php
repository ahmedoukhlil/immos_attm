<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventaires', function (Blueprint $table): void {
            $table->dateTime('started_at')->nullable()->after('date_debut');
            $table->dateTime('finished_at')->nullable()->after('date_fin');
            $table->dateTime('closed_at')->nullable()->after('finished_at');
        });

        DB::table('inventaires')->orderBy('id')->chunkById(200, function ($inventaires): void {
            foreach ($inventaires as $inventaire) {
                DB::table('inventaires')->where('id', $inventaire->id)->update([
                    'started_at' => in_array($inventaire->statut, ['en_cours', 'termine', 'cloture'], true)
                        ? $inventaire->date_debut
                        : null,
                    'finished_at' => in_array($inventaire->statut, ['termine', 'cloture'], true)
                        ? $inventaire->date_fin
                        : null,
                    'closed_at' => $inventaire->statut === 'cloture' ? $inventaire->date_fin : null,
                ]);
            }
        });

        Schema::table('inventaire_exports', function (Blueprint $table): void {
            $table->boolean('automatic')->default(false)->after('format');
            $table->string('final_key')->nullable()->after('automatic');
            $table->string('checksum', 64)->nullable()->after('filename');
            $table->unique('final_key', 'inv_exports_final_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventaire_exports', function (Blueprint $table): void {
            $table->dropUnique('inv_exports_final_unique');
            $table->dropColumn(['automatic', 'final_key', 'checksum']);
        });

        Schema::table('inventaires', function (Blueprint $table): void {
            $table->dropColumn(['started_at', 'finished_at', 'closed_at']);
        });
    }
};
