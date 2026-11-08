<?php

namespace App\Http\Controllers;

use App\Models\Localisation;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Contrôleur pour générer les QR codes des sites
 * Pour l'inventaire via PWA Scanner
 *
 * Le contenu « EMP-{id} » est conservé pour rester lisible par la PWA
 * et compatible avec les étiquettes déjà imprimées.
 */
class QRCodeSiteController extends Controller
{
    /**
     * Affiche la page de génération des QR codes
     */
    public function index(Request $request)
    {
        $sites = $this->sitesFiltres($request)->get();
        $localisations = Localisation::orderBy('localisation')->get();

        return view('qrcodes.sites', compact('sites', 'localisations'));
    }

    /**
     * Génère le QR code d'un site
     */
    public function generate(Site $site)
    {
        return response($this->qrCode($site, 300))
            ->header('Content-Type', 'image/svg+xml');
    }

    /**
     * Télécharge un PDF avec tous les QR codes
     */
    public function downloadPdf(Request $request)
    {
        $qrCodes = $this->sitesFiltres($request)->get()->map(fn (Site $site): array => [
            'site' => $site,
            'qrCode' => base64_encode($this->qrCode($site, 250)),
            'qrData' => $this->qrData($site),
        ]);

        $pdf = Pdf::loadView('qrcodes.sites-pdf', [
            'qrCodes' => $qrCodes,
            'date' => now()->format('d/m/Y'),
        ]);

        return $pdf->download('qrcodes-sites-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Imprime les QR codes sélectionnés
     */
    public function printSelected(Request $request)
    {
        $validated = $request->validate([
            'sites' => ['required', 'array', 'min:1', 'max:1000'],
            'sites.*' => ['integer', 'distinct', 'exists:sites,id'],
        ]);

        $qrCodes = Site::with('localisation')
            ->whereIn('id', $validated['sites'])
            ->orderBy('site')
            ->get()
            ->map(fn (Site $site): array => [
                'site' => $site,
                'qrCode' => $this->qrCode($site, 250),
                'qrData' => $this->qrData($site),
            ])
            ->all();

        return view('qrcodes.sites-print', [
            'qrCodes' => $qrCodes,
            'date' => now()->format('d/m/Y'),
        ]);
    }

    private function sitesFiltres(Request $request)
    {
        return Site::with('localisation')
            ->withCount('immobilisations')
            ->when($request->filled('localisation'), fn ($query) => $query->where('localisation_id', $request->localisation))
            ->orderBy('site');
    }

    private function qrData(Site $site): string
    {
        return "EMP-{$site->id}";
    }

    private function qrCode(Site $site, int $taille): string
    {
        return (string) QrCode::format('svg')
            ->size($taille)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($this->qrData($site));
    }
}
