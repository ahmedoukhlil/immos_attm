<?php

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Seeder;

/**
 * Catégories et imputations comptables de la feuille « Code compta »
 * du fichier « Inventaire Immo ».
 */
class CategorieSeeder extends Seeder
{
    private array $categories = [
        ['code' => 'MEXP', 'categorie' => 'Matériel d\'exploitation', 'imputation_comptable' => '21400000'],
        ['code' => 'MTRP', 'categorie' => 'Matériels de transport', 'imputation_comptable' => '21500000'],
        ['code' => 'MINF', 'categorie' => 'Matériel informatique', 'imputation_comptable' => '21600000'],
        ['code' => 'MMOB', 'categorie' => 'Mobilier de bureau', 'imputation_comptable' => '21800000'],
    ];

    public function run(): void
    {
        foreach ($this->categories as $categorie) {
            Categorie::updateOrCreate(['code' => $categorie['code']], $categorie);
        }

        $this->command?->info('✅ Catégories : '.count($this->categories));
    }
}
