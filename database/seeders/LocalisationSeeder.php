<?php

namespace Database\Seeders;

use App\Models\Localisation;
use App\Services\InventaireExcelImporter;
use Illuminate\Database\Seeder;

/**
 * Localisation par défaut dans laquelle l'import Excel range les sites.
 */
class LocalisationSeeder extends Seeder
{
    public function run(): void
    {
        Localisation::firstOrCreate(
            ['code' => InventaireExcelImporter::CODE_LOCALISATION],
            ['localisation' => 'Siège'],
        );

        $this->command?->info('✅ Localisation « Siège » prête');
    }
}
