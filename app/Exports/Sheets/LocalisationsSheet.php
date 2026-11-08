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
 * Sheet Excel pour l'analyse par localisation
 */
class LocalisationsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
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
        return 'Par Localisation';
    }

    /**
     * Collection de données
     */
    public function collection()
    {
        return app(RapportService::class)->getPerformanceLocalisations($this->inventaire)->map(function ($perf) {
            return [
                'code' => $perf['code'] ?? 'N/A',
                'designation' => $perf['designation'] ?? 'N/A',
                'attendus' => $perf['attendus'] ?? 0,
                'scannes' => $perf['scannes'] ?? 0,
                'presents' => $perf['presents'] ?? 0,
                'deplaces' => $perf['deplaces'] ?? 0,
                'absents' => $perf['absents'] ?? 0,
                'taux_conformite' => number_format($perf['taux_conformite'] ?? 0, 2).'%',
                'duree_minutes' => $perf['duree_minutes'] ?? '-',
                'agent' => $perf['agent'] ?? 'Non assigné',
                'statut' => ucfirst($perf['statut'] ?? 'N/A'),
            ];
        });
    }

    /**
     * En-têtes de colonnes
     */
    public function headings(): array
    {
        return [
            'Code',
            'Désignation',
            'Attendus',
            'Scannés physiquement',
            'Présents',
            'Déplacés',
            'Absents',
            'Taux Conformité',
            'Durée (min)',
            'Agent',
            'Statut',
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
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
            'A' => 15,
            'B' => 30,
            'C' => 12,
            'D' => 12,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 15,
            'I' => 15,
            'J' => 20,
            'K' => 15,
        ];
    }
}
