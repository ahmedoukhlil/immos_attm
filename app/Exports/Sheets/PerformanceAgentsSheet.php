<?php

namespace App\Exports\Sheets;

use App\Models\Inventaire;
use App\Services\RapportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet Excel pour la performance par agent
 */
class PerformanceAgentsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    protected $inventaire;

    public function __construct(Inventaire $inventaire)
    {
        $this->inventaire = $inventaire;
    }

    /**
     * Titre de l'onglet
     */
    public function title(): string
    {
        return 'Performance Agents';
    }

    /**
     * Collection de données
     */
    public function collection()
    {
        return app(RapportService::class)->getPerformanceAgents($this->inventaire)->map(function ($perf) {
            return [
                'agent' => $perf['agent'] ?? 'N/A',
                'localisations' => $perf['localisations'] ?? 0,
                'localisations_terminees' => $perf['localisations_terminees'] ?? 0,
                'biens_scannes' => $perf['biens_scannes'] ?? 0,
                'duree_totale_heures' => number_format(($perf['duree_totale_minutes'] ?? 0) / 60, 1),
                'moyenne_par_localisation' => $perf['moyenne_par_localisation'] ?? 0,
                'moyenne_par_bien' => $perf['moyenne_par_bien'] ?? 0,
            ];
        });
    }

    /**
     * En-têtes de colonnes
     */
    public function headings(): array
    {
        return [
            'Agent',
            'Localisations',
            'Terminées',
            'Biens Scannés',
            'Durée Totale (h)',
            'Moy./Localisation (min)',
            'Moy./Bien (min)',
        ];
    }

    /**
     * Styles
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    /**
     * Largeurs des colonnes
     */
    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 15,
            'C' => 15,
            'D' => 15,
            'E' => 18,
            'F' => 22,
            'G' => 18,
        ];
    }
}
