<?php

namespace App\Exports\Sheets;

use App\Models\Inventaire;
use App\Services\InventaireService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet Excel pour la synthèse de l'inventaire
 */
class SyntheseSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    protected $inventaire;

    protected $statistiques;

    public function __construct(Inventaire $inventaire)
    {
        $this->inventaire = $inventaire;
        $inventaireService = app(InventaireService::class);
        $this->statistiques = $inventaireService->calculerStatistiques($inventaire);
    }

    /**
     * Titre de l'onglet
     */
    public function title(): string
    {
        return 'Synthèse';
    }

    /**
     * Contenu de l'onglet
     */
    public function array(): array
    {
        $tauxConformite = $this->statistiques['taux_conformite'];
        $statut = $tauxConformite >= 90
            ? 'CONFORME'
            : ($tauxConformite >= 70 ? 'À AMÉLIORER' : 'NON CONFORME');

        return [
            // En-tête
            ['RAPPORT D\'INVENTAIRE - ANNÉE '.$this->inventaire->annee],
            [''],
            ['Statut Global', $statut],
            ['Taux de Conformité', number_format($tauxConformite, 2).'%'],
            [''],

            // Informations générales
            ['INFORMATIONS GÉNÉRALES'],
            ['Date prévue', $this->inventaire->date_debut->format('d/m/Y')],
            ['Démarrage effectif', $this->inventaire->started_at?->format('d/m/Y H:i') ?? 'Non démarré'],
            ['Fin opérationnelle', $this->inventaire->finished_at?->format('d/m/Y H:i') ?? 'En cours'],
            ['Clôture administrative', $this->inventaire->closed_at?->format('d/m/Y H:i') ?? 'Non clôturé'],
            ['Durée', $this->statistiques['duree_jours'].' jours'],
            ['Créé par', $this->inventaire->creator->users ?? 'N/A'],
            ['Clôturé par', $this->inventaire->closer ? $this->inventaire->closer->users : '-'],
            [''],

            // Statistiques principales
            ['STATISTIQUES PRINCIPALES'],
            ['Indicateur', 'Valeur'],
            ['Total biens attendus', $this->statistiques['total_biens_attendus']],
            ['Biens rapprochés', $this->statistiques['total_biens_traites']],
            ['Biens scannés physiquement', $this->statistiques['total_biens_scannes']],
            ['Biens présents', $this->statistiques['biens_presents']],
            ['Biens déplacés', $this->statistiques['biens_deplaces']],
            ['Biens absents', $this->statistiques['biens_absents']],
            ['Biens détériorés', $this->statistiques['biens_deteriores']],
            ['Taux de rapprochement', number_format($this->statistiques['taux_rapprochement'], 2).'%'],
            ['Taux de couverture physique', number_format($this->statistiques['taux_couverture'], 2).'%'],
            ['Taux d\'anomalies uniques', number_format($this->statistiques['taux_anomalies'], 2).'%'],
            ['Progression globale', number_format($this->statistiques['progression_globale'], 2).'%'],
            [''],

            // Localisations
            ['LOCALISATIONS'],
            ['Total localisations', $this->statistiques['total_localisations']],
            ['Localisations terminées', $this->statistiques['localisations_terminees']],
            ['Localisations en cours', $this->statistiques['localisations_en_cours']],
            ['Localisations en attente', $this->statistiques['localisations_en_attente']],
            [''],

            // Répartition par nature
            ['RÉPARTITION PAR NATURE DE BIEN'],
            ['Nature', 'Total', 'Présents', 'Déplacés', 'Absents', 'Conformité (%)'],
            ...collect($this->statistiques['par_nature'] ?? [])->map(function ($stats, $nature) {
                return [
                    ucfirst($nature),
                    $stats['total'] ?? 0,
                    $stats['presents'] ?? 0,
                    $stats['deplaces'] ?? 0,
                    $stats['absents'] ?? 0,
                    number_format($stats['conformite'] ?? 0, 2),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Styles de l'onglet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // En-tête principal
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Titres de sections
            6 => ['font' => ['bold' => true, 'size' => 12]],
            13 => ['font' => ['bold' => true, 'size' => 12]],
            22 => ['font' => ['bold' => true, 'size' => 12]],
            30 => ['font' => ['bold' => true, 'size' => 12]],
            38 => ['font' => ['bold' => true, 'size' => 12]],
            // En-têtes de tableaux
            14 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            ],
            31 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            ],
        ];
    }

    /**
     * Largeurs des colonnes
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 15,
        ];
    }
}
