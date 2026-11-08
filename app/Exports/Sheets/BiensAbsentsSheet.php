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
 * Sheet Excel pour les biens absents
 */
class BiensAbsentsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
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
        return 'Biens Absents';
    }

    /**
     * Collection de données
     */
    public function collection()
    {
        return app(RapportService::class)->getBiensAbsents($this->inventaire)->map(function ($bien) {
            return [
                'code' => $bien['code'] ?? 'N/A',
                'designation' => $bien['designation'] ?? 'N/A',
                'nature' => ucfirst($bien['nature'] ?? 'N/A'),
                'localisation' => $bien['localisation'] ?? 'N/A',
                'site' => $bien['site'] ?? 'N/A',
                'responsable' => $bien['responsable'] ?? 'N/A',
                'date_scan' => $bien['date_scan'] ? $bien['date_scan']->format('d/m/Y H:i') : 'N/A',
                'agent' => $bien['agent'] ?? 'N/A',
                'commentaire' => $bien['commentaire'] ?? '-',
            ];
        });
    }

    /**
     * En-têtes de colonnes
     */
    public function headings(): array
    {
        return [
            'Code Inventaire',
            'Désignation',
            'Nature',
            'Localisation',
            'Site',
            'Responsable',
            'Date Scan',
            'Agent',
            'Commentaire',
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EF4444']],
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
            'A' => 18,
            'B' => 40,
            'C' => 15,
            'D' => 15,
            'E' => 20,
            'F' => 15,
            'G' => 18,
            'H' => 18,
            'I' => 20,
            'J' => 40,
        ];
    }
}
