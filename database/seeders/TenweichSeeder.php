<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Affectation;
use App\Models\LocalisationImmo;
use App\Models\Emplacement;
use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Etat;
use App\Models\NatureJuridique;
use App\Models\SourceFinancement;
use App\Models\Gesimmo;

class TenweichSeeder extends Seeder
{
    /**
     * Biens à créer : désignation => [categorie_code, etat_code, quantité]
     */
    private array $biens = [
        'Compresseur froid'          => ['MCEX', 'BON', 3],
        'Climatiseur'                => ['MEXP', 'BON', 3],
        'Bureau'                     => ['MMOB', 'BON', 2],
        'Fauteuil'                   => ['MMOB', 'BON', 2],
        'Coffre fort'                => ['MMOB', 'BON', 1],
        'Moteur surpresseur'         => ['MCEX', 'BON', 4],
        'Surpresseur'                => ['MCEX', 'BON', 2],
        'Groupe électrogène'         => ['MEXP', 'BON', 3],
        'Coffret électrique'         => ['MEXP', 'BON', 4],
        'Réservoir gasoil'           => ['MEXP', 'BON', 1],
        'Citerne'                    => ['MTRP', 'BON', 1],
        'Car Dyna'                   => ['MTRP', 'HU', 1],
        'Hilux'                      => ['MTRP', 'HU', 1],
        'Toyota GX'                  => ['MTRP', 'HU', 1],
        'Benne Mercedes'             => ['MTRP', 'BON', 1],
        'Bull chargeur'              => ['MTRP', 'BON', 1],
        'Citerne CNHTC'              => ['MTRP', 'BON', 1],
        'Citerne International'      => ['MTRP', 'HU', 1],
        'Bus Toyota'                 => ['MTRP', 'HU', 1],
        'Renault Laguna'             => ['MTRP', 'HU', 1],
        'Nissan'                     => ['MTRP', 'HU', 1],
        'Bus Kia'                    => ['MTRP', 'HU', 1],
        'Chaise visiteurs'           => ['MMOB', 'BON', 2],
        'Photocopieur'               => ['MINF', 'BON', 1],
        'Unité informatique'         => ['MINF', 'BON', 1],
        'Bureau avec retour'         => ['MMOB', 'BON', 1],
        'Ecran de surveillance'      => ['MEXP', 'BON', 1],
        'Coffret réseau'             => ['IAAM', 'BON', 3],
        'Caméra de surveillance'     => ['MEXP', 'BON', 37],
        'Table basse'                => ['MMOB', 'BON', 1],
        'Extincteur'                 => ['MEXP', 'BON', 2],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $localisation = LocalisationImmo::firstOrCreate(
                ['CodeLocalisation' => 'TENW'],
                ['Localisation' => 'Tenweich']
            );

            $affectation = Affectation::firstOrCreate(
                ['CodeAffectation' => 'DIREXP'],
                [
                    'Affectation'     => 'Dir exploitation',
                    'idLocalisation'  => $localisation->idLocalisation,
                ]
            );

            $emplacement = Emplacement::firstOrCreate(
                ['CodeEmplacement' => 'TENW-EMP'],
                [
                    'Emplacement'      => 'Tenweich',
                    'idAffectation'    => $affectation->idAffectation,
                    'idLocalisation'   => $localisation->idLocalisation,
                ]
            );

            $etatDefaut = Etat::where('CodeEtat', 'BON')->first();
            $natJur = NatureJuridique::where('CodeNatJur', 'PROP')->first();
            $sourceFin = SourceFinancement::where('CodeSourceFin', 'FP')->first();

            $this->command->info('Création des biens à Tenweich...');

            foreach ($this->biens as $nomDesignation => [$codeCategorie, $codeEtat, $quantite]) {
                $categorie = Categorie::where('CodeCategorie', $codeCategorie)->first();

                $designation = Designation::firstOrCreate(
                    ['designation' => $nomDesignation],
                    [
                        'CodeDesignation' => $this->genererCode($nomDesignation),
                        'idCat'           => $categorie->idCategorie ?? null,
                    ]
                );

                $etat = Etat::where('CodeEtat', $codeEtat)->first() ?? $etatDefaut;

                for ($i = 0; $i < $quantite; $i++) {
                    Gesimmo::create([
                        'idDesignation'   => $designation->id,
                        'idCategorie'     => $categorie->idCategorie ?? $designation->idCat,
                        'idEtat'          => $etat->idEtat,
                        'idEmplacement'   => $emplacement->idEmplacement,
                        'idNatJur'        => $natJur->idNatJur,
                        'idSF'            => $sourceFin->idSF,
                    ]);
                }

                $this->command->line("  ✓ {$nomDesignation} x{$quantite} ({$codeCategorie} / {$codeEtat})");
            }

            $this->command->info('✅ Terminé : emplacement Tenweich peuplé.');
        });
    }

    private function genererCode(string $nom): string
    {
        $nom = preg_replace('/[^A-Za-z0-9 ]/', '', $nom);
        $mots = explode(' ', trim($nom));
        $code = '';

        foreach ($mots as $mot) {
            $code .= mb_strtoupper(mb_substr($mot, 0, 3));
        }

        return mb_substr($code, 0, 15);
    }
}
