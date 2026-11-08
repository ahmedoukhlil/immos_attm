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

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('ALTER TABLE `users` DROP CHECK `users_role_check`');
            } catch (Throwable) {
                // Certaines bases importées ne contiennent pas encore cette contrainte.
            }

            DB::statement("ALTER TABLE `users` ADD CONSTRAINT `users_role_check` CHECK (`role` IN ('admin', 'gestionnaire', 'agent'))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')->where('role', 'gestionnaire')->update(['role' => 'agent']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('ALTER TABLE `users` DROP CHECK `users_role_check`');
            } catch (Throwable) {
                // Compatibilité avec une base où la contrainte n'existe pas.
            }

            DB::statement("ALTER TABLE `users` ADD CONSTRAINT `users_role_check` CHECK (`role` IN ('admin', 'agent'))");
        }
    }
};
