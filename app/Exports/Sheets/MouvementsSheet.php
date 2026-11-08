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
 * Sheet Excel pour l'analyse des mouvements
 */
class MouvementsSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
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
        return 'Mouvements';
    }

    /**
     * Collection de données
     */
    public function collection()
    {
        $mouvements = app(RapportService::class)->getAnalyseMouvements($this->inventaire);
        $flux = $mouvements['flux'] ?? collect();

        return $flux->map(function ($fluxItem) {
            return [
                'origine' => $fluxItem['origine'] ?? 'N/A',
                'destination' => $fluxItem['destination'] ?? 'N/A',
                'nombre_biens' => $fluxItem['nombre_biens'] ?? 0,
            ];
        });
    }

    /**
     * En-têtes de colonnes
     */
    public function headings(): array
    {
        return [
            'Origine',
            'Destination',
            'Nombre de Biens',
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B5CF6']],
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
            'A' => 20,
            'B' => 20,
            'C' => 18,
            'D' => 20,
        ];
    }
}
