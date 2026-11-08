<?php

namespace App\Exports;

use App\Exports\Sheets\BiensAbsentsSheet;
use App\Exports\Sheets\BiensDeplacesSheet;
use App\Exports\Sheets\BiensNonScannesSheet;
use App\Exports\Sheets\BiensPresentsSheet;
use App\Exports\Sheets\LocalisationsSheet;
use App\Exports\Sheets\MouvementsSheet;
use App\Exports\Sheets\PerformanceAgentsSheet;
use App\Exports\Sheets\SyntheseSheet;
use App\Models\Inventaire;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Classe principale d'export Excel pour un inventaire
 * Génère un fichier Excel multi-onglets avec toutes les données
 */
class InventaireExport implements WithMultipleSheets
{
    use Exportable;

    protected $inventaire;

    /**
     * Constructeur
     */
    public function __construct(Inventaire $inventaire)
    {
        $this->inventaire = $inventaire;
    }

    /**
     * Retourne les différents onglets du fichier Excel
     */
    public function sheets(): array
    {
        return [
            'Synthèse' => new SyntheseSheet($this->inventaire),
            'Par Localisation' => new LocalisationsSheet($this->inventaire),
            'Biens Présents' => new BiensPresentsSheet($this->inventaire),
            'Biens Déplacés' => new BiensDeplacesSheet($this->inventaire),
            'Biens Absents' => new BiensAbsentsSheet($this->inventaire),
            'Biens Non Scannés' => new BiensNonScannesSheet($this->inventaire),
            'Performance Agents' => new PerformanceAgentsSheet($this->inventaire),
            'Mouvements' => new MouvementsSheet($this->inventaire),
        ];
    }
}
