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
        Schema::table('users', function (Blueprint $table) {
            // Vérifier si les colonnes n'existent pas déjà
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'agent'])->default('agent')->after('password');
            }
            if (!Schema::hasColumn('users', 'telephone')) {
                $table->string('telephone')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'service')) {
                $table->string('service')->nullable()->after('telephone');
            }
            if (!Schema::hasColumn('users', 'actif')) {
                $table->boolean('actif')->default(true)->after('service');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('users', 'telephone')) {
                $table->dropColumn('telephone');
            }
            if (Schema::hasColumn('users', 'service')) {
                $table->dropColumn('service');
            }
            if (Schema::hasColumn('users', 'actif')) {
                $table->dropColumn('actif');
            }
        });
    }
};
