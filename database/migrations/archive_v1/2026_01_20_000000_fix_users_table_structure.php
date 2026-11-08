<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cette migration corrige la structure de la table users si elle a été créée
     * avec l'ancienne structure (name/email) au lieu de la nouvelle (users/mdp)
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('idUser');
                $table->string('users')->unique();
                $table->string('mdp');
                $table->string('role', 50);
            });

            return;
        }

        $columnNames = Schema::getColumnListing('users');

        if (count(array_intersect(['idUser', 'users', 'mdp', 'role'], $columnNames)) === 4) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->upgradeLegacySqliteTable($columnNames);

            return;
        }

        // Si la colonne 'users' n'existe pas mais 'name' existe, on doit migrer
        if (! in_array('users', $columnNames) && in_array('name', $columnNames)) {
            // Ajouter la colonne 'users' si elle n'existe pas
            if (! in_array('users', $columnNames)) {
                DB::statement('ALTER TABLE `users` ADD COLUMN `users` VARCHAR(255) NULL AFTER `id`');
            }

            // Copier les données de 'name' vers 'users'
            DB::statement('UPDATE `users` SET `users` = `name` WHERE `users` IS NULL');

            // Rendre la colonne NOT NULL
            DB::statement('ALTER TABLE `users` MODIFY COLUMN `users` VARCHAR(255) NOT NULL');

            // Ajouter l'index unique
            try {
                DB::statement('ALTER TABLE `users` ADD UNIQUE INDEX `users_users_unique` (`users`)');
            } catch (Exception $e) {
                // L'index existe peut-être déjà, on ignore
            }
        }

        // Vérifier si la colonne 'mdp' existe, sinon créer depuis 'password'
        if (! in_array('mdp', $columnNames) && in_array('password', $columnNames)) {
            // Ajouter la colonne 'mdp' si elle n'existe pas
            DB::statement('ALTER TABLE `users` ADD COLUMN `mdp` VARCHAR(255) NULL AFTER `users`');

            // Copier les données de 'password' vers 'mdp'
            DB::statement('UPDATE `users` SET `mdp` = `password` WHERE `mdp` IS NULL');

            // Rendre la colonne NOT NULL
            DB::statement('ALTER TABLE `users` MODIFY COLUMN `mdp` VARCHAR(255) NOT NULL');
        }

        // Vérifier si la colonne 'role' existe
        if (! in_array('role', $columnNames)) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'agent' AFTER `mdp`");
        }

        // Vérifier si la clé primaire est 'idUser' ou 'id'
        $primaryKey = DB::select("SHOW KEYS FROM `users` WHERE Key_name = 'PRIMARY'");
        if (! empty($primaryKey) && $primaryKey[0]->Column_name === 'id' && ! in_array('idUser', $columnNames)) {
            // Renommer la colonne 'id' en 'idUser'
            DB::statement('ALTER TABLE `users` CHANGE COLUMN `id` `idUser` INT NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ne rien faire en reverse pour éviter de perdre des données
    }

    /**
     * Rend la migration de compatibilité exécutable dans les environnements
     * SQLite utilisés par les tests et les installations légères.
     *
     * @param  list<string>  $columnNames
     */
    private function upgradeLegacySqliteTable(array $columnNames): void
    {
        Schema::table('users', function (Blueprint $table) use ($columnNames): void {
            if (! in_array('users', $columnNames, true)) {
                $table->string('users')->nullable();
            }
            if (! in_array('mdp', $columnNames, true)) {
                $table->string('mdp')->nullable();
            }
            if (! in_array('role', $columnNames, true)) {
                $table->string('role', 50)->default('agent');
            }
        });

        if (! in_array('users', $columnNames, true) && in_array('name', $columnNames, true)) {
            DB::table('users')->whereNull('users')->update(['users' => DB::raw('name')]);
        }
        if (! in_array('mdp', $columnNames, true) && in_array('password', $columnNames, true)) {
            DB::table('users')->whereNull('mdp')->update(['mdp' => DB::raw('password')]);
        }
        if (! in_array('idUser', $columnNames, true) && in_array('id', $columnNames, true)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('id', 'idUser');
            });
        }
    }
};
