<?php

namespace App\Services;

use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Etat;
use App\Models\Localisation;
use App\Models\Site;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Modèle Excel d'import des immobilisations, lu par InventaireExcelImporter.
 */
class ModeleImportService
{
    public const COLONNES = [
        'Localisation',
        'Site',
        'Responsable',
        'Catégorie',
        'Imputation comptable',
        "Désignation de l'immobilisation",
        "Descriptifs de l'immobilisation",
        'Marque',
        'Modèle',
        'Numéro de châssis',
        'Numéro ATTM',
        'Code immo',
        "État de l'immo",
        "Année d'acquisition",
        'Quantité',
        'Code dernier inventaire',
        'Code premier inventaire',
        'Autres informations',
    ];

    private const LIGNES_SAISIE = 1000;

    /**
     * Génère le modèle et retourne le chemin du fichier temporaire.
     */
    public function generer(): string
    {
        $classeur = new Spreadsheet;
        $immo = $classeur->getActiveSheet()->setTitle('Immo');
        $immo->fromArray([
            self::COLONNES,
            ['Siège', 'Chef Service Informatique', 'Sid’El Moctar', 'Mobilier de bureau', '21800000', 'Bureau', 'En bois rouge, type directeur', 'Exemple', 'Modèle A', '', '', 'IMMO-001', 'Bon', (int) now()->year, 1, '', '', ''],
        ]);
        $immo->getStyle('A1:R1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $immo->getStyle('A1:R1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $immo->freezePane('A2');
        foreach (range('A', 'R') as $colonne) {
            $immo->getColumnDimension($colonne)->setAutoSize(true);
        }

        $listes = $classeur->createSheet()->setTitle('Listes');
        $categories = Categorie::orderBy('categorie')->get();
        $etats = Etat::orderBy('id')->pluck('etat');
        $listes->fromArray(['Localisations', 'Sites', 'Responsable du site', 'Catégories', 'Imputation', 'Désignations', 'États']);
        $this->colonne($listes, 'A', Localisation::orderBy('localisation')->pluck('localisation')->all());
        $sites = Site::orderBy('site')->get(['site', 'responsable']);
        $this->colonne($listes, 'B', $sites->pluck('site')->all());
        $this->colonne($listes, 'C', $sites->pluck('responsable')->all());
        $this->colonne($listes, 'D', $categories->pluck('categorie')->all());
        $this->colonne($listes, 'E', $categories->pluck('imputation_comptable')->all());
        $this->colonne($listes, 'F', Designation::orderBy('designation')->pluck('designation')->all());
        $this->colonne($listes, 'G', $etats->all());
        $listes->getStyle('A1:G1')->getFont()->setBold(true);
        foreach (range('A', 'G') as $colonne) {
            $listes->getColumnDimension($colonne)->setAutoSize(true);
        }

        // Listes déroulantes indicatives : une nouvelle valeur reste possible (proposée à la création).
        $this->listeDeroulante($immo, 'D', 'Listes!$D$2:$D$'.max(2, $categories->count() + 1));
        $this->listeDeroulante($immo, 'M', 'Listes!$G$2:$G$'.max(2, $etats->count() + 1));

        $classeur->setActiveSheetIndex(0);
        $chemin = tempnam(sys_get_temp_dir(), 'modele').'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        return $chemin;
    }

    private function colonne($feuille, string $lettre, array $valeurs): void
    {
        foreach (array_values($valeurs) as $index => $valeur) {
            $feuille->setCellValue($lettre.($index + 2), $valeur);
        }
    }

    private function listeDeroulante($feuille, string $lettre, string $plage): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_INFORMATION)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(false)
            ->setFormula1($plage);

        $feuille->setDataValidation("{$lettre}2:{$lettre}".(self::LIGNES_SAISIE + 1), $validation);
    }
}
