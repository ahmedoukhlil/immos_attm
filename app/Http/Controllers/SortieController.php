<?php

namespace App\Http\Controllers;

use App\Models\Immobilisation;
use App\Models\Sortie;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SortieController extends Controller
{
    /**
     * Procès-verbal de sortie ; marqué « Projet » tant que la décision n'est pas validée.
     */
    public function pv(Sortie $sortie): Response
    {
        $sortie->load(['lignes', 'inventaire', 'validateur', 'auteur']);

        $nom = 'pv-sortie-'.str($sortie->numero)->slug().'.pdf';

        return Pdf::loadView('pdf.pv-sortie', ['sortie' => $sortie])
            ->setPaper('a4')
            ->stream($nom);
    }

    /**
     * Registre des biens sortis, dans l'ordre des colonnes du registre, suivi de la sortie.
     */
    public function export(): StreamedResponse
    {
        $filename = 'registre-sorties-'.now()->format('Ymd-His').'.csv';

        return response()->stream(function (): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Localisation',
                'Site',
                'Catégorie',
                'Imputation comptable',
                "Désignation de l'immobilisation",
                'N° article',
                'Code dernier inventaire',
                'Code proposé',
                "État de l'immo",
                'Motif de sortie',
                'Date de sortie',
                'N° de décision',
            ], ';');

            Immobilisation::query()
                ->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE)
                ->whereNotNull('sortie_id')
                ->with(['site.localisation', 'categorie', 'designation', 'etat', 'sortie'])
                ->lazyById(500, 'id')
                ->each(function (Immobilisation $bien) use ($file): void {
                    fputcsv($file, [
                        $bien->site?->localisation?->localisation ?? '',
                        $bien->site?->site ?? '',
                        $bien->categorie?->categorie ?? '',
                        $bien->categorie?->imputation_comptable ?? '',
                        $bien->designation?->designation ?? '',
                        $bien->numero_article ?? '',
                        $bien->code_dernier_inventaire ?? '',
                        $bien->code_propose ?? '',
                        $bien->etat?->etat ?? '',
                        $bien->sortie?->motif_libelle ?? '',
                        $bien->date_sortie?->format('d/m/Y') ?? '',
                        $bien->sortie?->numero ?? '',
                    ], ';');
                });

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
