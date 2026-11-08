<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $invalidRoles = DB::table('users')
            ->whereNotIn('role', ['admin', 'agent'])
            ->distinct()
            ->pluck('role');

        if ($invalidRoles->isNotEmpty()) {
            throw new RuntimeException(
                'Des rôles historiques doivent être reclassés manuellement en admin ou agent avant cette migration : '
                .$invalidRoles->implode(', ')
            );
        }

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE `users` ADD CONSTRAINT `users_role_check` CHECK (`role` IN ('admin', 'agent'))");
            } catch (Throwable) {
                // La contrainte peut déjà exister sur une installation mise à niveau.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE `users` DROP CHECK `users_role_check`');
            } catch (Throwable) {
                // Compatibilité avec les versions MySQL/MariaDB plus anciennes.
            }
        }
    }
};
