<?php

namespace Database\Seeders;

use App\Models\Etat;
use Illuminate\Database\Seeder;

/**
 * États utilisés dans la colonne « État de l'immo » du fichier « Inventaire Immo ».
 */
class EtatSeeder extends Seeder
{
    private array $etats = [
        ['code' => 'NEUF', 'etat' => 'Neuf'],
        ['code' => 'BON', 'etat' => 'Bon'],
        ['code' => 'MOY', 'etat' => 'Moyen'],
        ['code' => 'MAUV', 'etat' => 'Mauvais'],
        ['code' => 'HS', 'etat' => 'Hors service'],
    ];

    public function run(): void
    {
        foreach ($this->etats as $etat) {
            Etat::updateOrCreate(['code' => $etat['code']], $etat);
        }

        $this->command?->info('✅ États : '.count($this->etats));
    }
}
