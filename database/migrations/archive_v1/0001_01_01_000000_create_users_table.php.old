<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Création de la table users avec SQL brut pour contrôler exactement la structure
        // Utilisation de varchar(100) pour email et index unique avec préfixe de 25 caractères
        DB::statement("
            CREATE TABLE `users` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `email_verified_at` TIMESTAMP NULL,
                `password` VARCHAR(100) NOT NULL,
                `remember_token` VARCHAR(100) NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `users_email_unique` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Création de la table password_reset_tokens avec SQL brut
        DB::statement("
            CREATE TABLE `password_reset_tokens` (
                `email` VARCHAR(100) NOT NULL,
                `token` VARCHAR(100) NOT NULL,
                `created_at` TIMESTAMP NULL,
                PRIMARY KEY (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Création de la table sessions avec SQL brut
        DB::statement("
            CREATE TABLE `sessions` (
                `id` VARCHAR(100) NOT NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `payload` LONGTEXT NOT NULL,
                `last_activity` INT NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `sessions_user_id_index` (`user_id`),
                INDEX `sessions_last_activity_index` (`last_activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
