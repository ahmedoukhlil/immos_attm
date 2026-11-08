<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Référentiels de base. Les immobilisations sont chargées ensuite avec
     * « php artisan immos:import-excel ».
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            EtatSeeder::class,
            CategorieSeeder::class,
            LocalisationSeeder::class,
        ]);
    }
}
