<?php

namespace App\Http\Controllers;

use App\Models\Localisation;
use App\Support\SelectedIds;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalisationController extends Controller
{
    /**
     * Génère le QR code d'une localisation
     */
    public function generateQRCode(Localisation $localisation): RedirectResponse
    {
        // Seuls les sites portent un QR code scannable par la PWA.
        return redirect()->back()->with('info', 'La génération de QR code pour les localisations n\'est pas encore implémentée.');
    }

    /**
     * Télécharge l'étiquette PDF d'une localisation (10 x 8 cm)
     *
     * @return Response|RedirectResponse
     */
    public function downloadEtiquette(Localisation $localisation)
    {
        try {
            $pdf = Pdf::loadView('pdf.etiquette-localisation', [
                'localisation' => $localisation,
            ])->setPaper([0, 0, 283.46, 226.77], 'portrait');

            return $pdf->download('etiquette_'.Str::slug($localisation->libelle_court).'.pdf');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->with('error', 'Erreur lors de la génération de l\'étiquette.');
        }
    }

    /**
     * Imprime en masse les étiquettes de plusieurs localisations
     * (4 étiquettes par page A4 : 2 colonnes x 2 lignes).
     *
     * @return Response|RedirectResponse
     */
    public function imprimerEtiquettes(Request $request)
    {
        $validated = $request->validate([
            'localisations' => 'required|array|min:1|max:1000',
            'localisations.*' => 'integer|distinct|exists:localisations,id',
        ]);

        try {
            $pdf = Pdf::loadView('pdf.etiquettes-localisations', [
                'localisations' => Localisation::whereIn('id', $validated['localisations'])->get(),
            ])->setPaper('a4', 'portrait');

            return $pdf->download('etiquettes_localisations_'.now()->format('Y-m-d_His').'.pdf');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->with('error', 'Erreur lors de l\'impression des étiquettes.');
        }
    }

    /**
     * Exporte les localisations en CSV
     *
     * @return StreamedResponse|RedirectResponse
     */
    public function exportExcel(Request $request)
    {
        $selectedIds = SelectedIds::parse($request->input('ids'));

        $query = Localisation::query()
            ->withCount('sites')
            ->when($selectedIds !== null, fn ($query) => $query->whereIn('id', $selectedIds));

        if (! (clone $query)->exists()) {
            return redirect()->back()->with('warning', 'Aucune localisation à exporter.');
        }

        $filename = 'localisations_'.now()->format('Y-m-d_His').'.csv';

        $callback = function () use ($query): void {
            $file = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['ID', 'Localisation', 'Code', 'Nombre de sites'], ';');

            foreach ($query->lazyById(500, 'id') as $localisation) {
                fputcsv($file, [
                    $localisation->id,
                    $localisation->localisation,
                    $localisation->code ?? '',
                    $localisation->sites_count,
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
