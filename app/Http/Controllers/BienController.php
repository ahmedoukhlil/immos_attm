<?php

namespace App\Http\Controllers;

use App\Models\Immobilisation;
use App\Services\ModeleImportService;
use App\Support\SelectedIds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BienController extends Controller
{
    /**
     * Nombre maximal d'étiquettes générées en une seule demande.
     */
    private const MAX_ETIQUETTES = 1000;

    /**
     * Exporte les biens en CSV, avec les colonnes du fichier « Inventaire Immo ».
     *
     * @return StreamedResponse|RedirectResponse
     */
    public function exportExcel(Request $request)
    {
        $selectedIds = SelectedIds::parse($request->input('ids'));

        $query = Immobilisation::with([
            'designation',
            'categorie',
            'etat',
            'site.localisation',
        ])->when($selectedIds !== null, fn ($query) => $query->whereIn('id', $selectedIds));

        if (! (clone $query)->exists()) {
            return redirect()->back()->with('warning', 'Aucun bien à exporter.');
        }

        $filename = 'biens_'.now()->format('Y-m-d_His').'.csv';

        $callback = function () use ($query): void {
            $file = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Localisation',
                'Site',
                'Catégorie',
                'Imputation comptable',
                "Désignation de l'immobilisation",
                'N° article',
                "Descriptifs de l'immobilisation",
                'Marque',
                'Modèle',
                'Numéro de châssis',
                'Numéro ATTM',
                'Code immo',
                'Code dernier inventaire',
                'Code premier inventaire',
                'Responsable',
                "État de l'immo",
                'Code proposé',
                'Autres informations',
                'Identifiant de scan',
            ], ';');

            foreach ($query->lazyById(500, 'id') as $bien) {
                fputcsv($file, [
                    $bien->site?->localisation?->localisation ?? '',
                    $bien->site?->site ?? '',
                    $bien->categorie?->categorie ?? '',
                    $bien->categorie?->imputation_comptable ?? '',
                    $bien->designation?->designation ?? '',
                    $bien->numero_article ?? '',
                    $bien->descriptif ?? '',
                    $bien->marque ?? '',
                    $bien->modele ?? '',
                    $bien->numero_chassis ?? '',
                    $bien->numero_attm ?? '',
                    $bien->code_immo ?? '',
                    $bien->code_dernier_inventaire ?? '',
                    $bien->code_premier_inventaire ?? '',
                    $bien->site?->responsable ?? '',
                    $bien->etat?->etat ?? '',
                    $bien->code_propose ?? '',
                    $bien->autres_informations ?? '',
                    $bien->scan_code,
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Télécharge le modèle Excel d'import des immobilisations.
     */
    public function modeleImport(ModeleImportService $modele)
    {
        return response()
            ->download($modele->generer(), 'modele_import_immobilisations.xlsx')
            ->deleteFileAfterSend();
    }
}
