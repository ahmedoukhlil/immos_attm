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

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->dropRoleConstraints();

        DB::statement(
            "ALTER TABLE `users` ADD CONSTRAINT `users_role_check` "
            ."CHECK (`role` IN ('admin', 'gestionnaire', 'agent'))"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')->where('role', 'gestionnaire')->update(['role' => 'agent']);

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->dropRoleConstraints();

        DB::statement(
            "ALTER TABLE `users` ADD CONSTRAINT `users_role_check` "
            ."CHECK (`role` IN ('admin', 'agent'))"
        );
    }

    /**
     * Supprime toute contrainte CHECK qui porte sur users.role, y compris les
     * noms générés automatiquement lors d'un import MySQL (ex. users_chk_1).
     */
    private function dropRoleConstraints(): void
    {
        $constraints = DB::table('information_schema.TABLE_CONSTRAINTS as tc')
            ->join('information_schema.CHECK_CONSTRAINTS as cc', function ($join): void {
                $join->on('cc.CONSTRAINT_SCHEMA', '=', 'tc.CONSTRAINT_SCHEMA')
                    ->on('cc.CONSTRAINT_NAME', '=', 'tc.CONSTRAINT_NAME');
            })
            ->whereRaw('tc.CONSTRAINT_SCHEMA = DATABASE()')
            ->where('tc.TABLE_NAME', 'users')
            ->where('tc.CONSTRAINT_TYPE', 'CHECK')
            ->get(['tc.CONSTRAINT_NAME', 'cc.CHECK_CLAUSE'])
            ->filter(fn (object $constraint): bool => str_contains(
                strtolower((string) $constraint->CHECK_CLAUSE),
                'role'
            ));

        foreach ($constraints as $constraint) {
            $name = str_replace('`', '``', (string) $constraint->CONSTRAINT_NAME);

            try {
                DB::statement("ALTER TABLE `users` DROP CHECK `{$name}`");
            } catch (Throwable) {
                DB::statement("ALTER TABLE `users` DROP CONSTRAINT `{$name}`");
            }
        }
    }
};
