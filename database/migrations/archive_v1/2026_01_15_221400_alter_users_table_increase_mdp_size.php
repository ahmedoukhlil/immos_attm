<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Augmenter la taille de la colonne mdp pour pouvoir stocker des hash bcrypt (60+ caractères)
        // Utiliser DB::statement pour éviter les problèmes avec change() si la colonne existe déjà
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `users` MODIFY COLUMN `mdp` VARCHAR(255) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revenir à une taille plus petite (attention: peut causer des erreurs si des hash existent)
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `users` MODIFY COLUMN `mdp` VARCHAR(100) NOT NULL');
        }
    }
};
