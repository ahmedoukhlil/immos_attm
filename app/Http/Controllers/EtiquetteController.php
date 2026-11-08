<?php

namespace App\Http\Controllers;

use App\Models\EtiquetteImpression;
use App\Models\Site;
use App\Services\EtiquetteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EtiquetteController extends Controller
{
    public function __construct(private readonly EtiquetteService $etiquetteService) {}

    public function apercu(Site $site): View
    {
        $site->load([
            'localisation',
            'immobilisations.designation.composants',
            'immobilisations.categorie',
        ]);

        $historique = $site->etiquetteImpressions()
            ->with('utilisateur')
            ->latest('generee_at')
            ->limit(100)
            ->get()
            ->groupBy('generation_uuid')
            ->map(function (Collection $impressions): array {
                $premiere = $impressions->first();

                return [
                    'uuid' => $premiere->generation_uuid,
                    'date' => $premiere->generee_at,
                    'utilisateur' => $premiere->utilisateur?->users ?? 'Utilisateur supprimé',
                    'mode' => $premiere->mode,
                    'statut' => $impressions->every(
                        fn (EtiquetteImpression $impression): bool => in_array($impression->statut, ['imprimee', 'reimprimee'], true)
                    ) ? 'imprimee' : $premiere->statut,
                    'total' => $impressions->sum('exemplaires'),
                ];
            })
            ->values();

        return view('etiquettes.apercu-site', [
            'site' => $site,
            'resume' => $this->etiquetteService->resume($site),
            'historique' => $historique,
            'formatsPage' => EtiquetteService::FORMATS_PAGE,
            'qrApercu' => 'data:image/svg+xml;base64,'.base64_encode(
                QrCode::format('svg')->size(180)->margin(1)->errorCorrection('M')
                    ->generate($site->immobilisations->first()?->contenu_qr ?? 'GS0')
            ),
        ]);
    }

    public function generer(Request $request, Site $site): Response|RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['complete', 'nouvelles', 'selection', 'bien', 'composant'])],
            'biens' => ['nullable', 'array'],
            'biens.*' => ['integer', 'exists:immobilisations,id'],
            'bien' => ['nullable', 'integer', 'exists:immobilisations,id'],
            'composant' => ['nullable', 'integer', 'exists:designation_composants,id'],
            'page_format' => ['nullable', Rule::in(array_keys(EtiquetteService::FORMATS_PAGE))],
            'grid_mode' => ['nullable', Rule::in(['dimensions', 'manual'])],
            'columns' => ['nullable', 'integer', 'between:1,12'],
            'rows' => ['nullable', 'integer', 'between:1,20'],
            'label_width' => ['nullable', 'numeric', 'between:20,100'],
            'label_height' => ['nullable', 'numeric', 'between:15,80'],
            'page_margin' => ['nullable', 'numeric', 'between:0,20'],
            'gap_x' => ['nullable', 'numeric', 'between:0,10'],
            'gap_y' => ['nullable', 'numeric', 'between:0,10'],
            'offset_x' => ['nullable', 'numeric', 'between:-10,10'],
            'offset_y' => ['nullable', 'numeric', 'between:-10,10'],
            'printer_margin' => ['nullable', 'numeric', 'between:0,10'],
            'qr_size' => ['nullable', 'numeric', 'between:12,45'],
            'start_position' => ['nullable', 'integer', 'between:1,200'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'show_designation' => ['nullable', 'boolean'],
            'show_emplacement' => ['nullable', 'boolean'],
            'show_code' => ['nullable', 'boolean'],
        ]);

        $mode = $validated['mode'];
        $bienIds = $validated['biens'] ?? [];

        if ($mode === 'selection' && $bienIds === []) {
            return back()->with('error', 'Sélectionnez au moins une immobilisation.');
        }

        if (in_array($mode, ['bien', 'composant'], true)) {
            $bienId = (int) ($validated['bien'] ?? 0);
            $bien = $site->immobilisations()->whereKey($bienId)->firstOrFail();
            $etiquettes = $this->etiquetteService->etiquettesPourBien($bien);

            if ($mode === 'composant') {
                $composantId = (int) ($validated['composant'] ?? 0);
                $etiquettes = $etiquettes
                    ->filter(fn (array $etiquette): bool => $etiquette['composant']?->id === $composantId)
                    ->values();
            }
        } else {
            $etiquettes = $this->etiquetteService->etiquettesPourSite($site, $mode, $bienIds);
        }

        if ($etiquettes->isEmpty()) {
            return back()->with('error', $mode === 'nouvelles'
                ? 'Toutes les étiquettes de ce site ont déjà été générées.'
                : 'Aucune étiquette ne correspond à cette sélection.');
        }

        $configuration = $this->etiquetteService->configurationImpression([
            ...$validated,
            'show_designation' => $request->boolean('show_designation', true),
            'show_emplacement' => $request->boolean('show_emplacement', true),
            'show_code' => $request->boolean('show_code', true),
        ]);

        if ($configuration['label_width'] < 20 || $configuration['label_height'] < 15) {
            throw ValidationException::withMessages([
                'columns' => 'Cette grille produit des étiquettes trop petites. Réduisez le nombre de lignes ou de colonnes.',
            ]);
        }

        if (! $configuration['is_horizontal']) {
            throw ValidationException::withMessages([
                'columns' => 'Cette grille produit des étiquettes verticales. Pour 56 étiquettes horizontales sur A4 portrait, utilisez 4 colonnes et 14 lignes.',
            ]);
        }

        if ($configuration['qr_size'] < 12) {
            throw ValidationException::withMessages([
                'qr_size' => 'Le QR calculé est inférieur à 12 mm et risque d’être difficile à lire. Utilisez le préréglage 56 horizontales ou réduisez les marges et espacements.',
            ]);
        }

        if ($configuration['start_position'] > $configuration['labels_per_page']) {
            throw ValidationException::withMessages([
                'start_position' => "La première position doit être comprise entre 1 et {$configuration['labels_per_page']} pour ce format.",
            ]);
        }

        $contenu = $this->rendrePdf($etiquettes, $site, $configuration);
        $this->etiquetteService->tracerGeneration($etiquettes, $site, (int) $request->user()->getAuthIdentifier(), $mode);

        $nom = 'etiquettes-'.str($site->site)->slug().'-'.now()->format('Ymd-His').'.pdf';

        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function confirmerImpression(Site $site, string $generation): RedirectResponse
    {
        $impressions = $site->etiquetteImpressions()
            ->where('generation_uuid', $generation)
            ->get();

        abort_if($impressions->isEmpty(), 404);

        foreach ($impressions as $impression) {
            $dejaImprimee = EtiquetteImpression::query()
                ->where('code_etiquette', $impression->code_etiquette)
                ->where('generation_uuid', '!=', $generation)
                ->whereIn('statut', ['imprimee', 'reimprimee'])
                ->exists();

            $impression->update([
                'statut' => $dejaImprimee ? 'reimprimee' : 'imprimee',
                'imprimee_at' => now(),
            ]);
        }

        return back()->with('success', 'L’impression a été confirmée dans l’historique.');
    }

    /**
     * Rend les étiquettes au format PDF.
     *
     * @param  Collection<int, array<string, mixed>>  $etiquettes
     * @param  array<string, mixed>  $configuration
     */
    private function rendrePdf(Collection $etiquettes, Site $site, array $configuration): string
    {
        $pointsParMillimetre = 2.834645669;

        $pdf = Pdf::loadHTML($this->etiquetteService->html($etiquettes, $site, $configuration))->setPaper([
            0,
            0,
            $configuration['page_width'] * $pointsParMillimetre,
            $configuration['page_height'] * $pointsParMillimetre,
        ]);
        $pdf->render();

        // Le PDF est déjà au format exact et sans marge : on demande au lecteur d'imprimer
        // en « Taille réelle » plutôt que « Ajuster », qui réduit la page et décale la grille.
        $pdf->getDomPDF()->getCanvas()->get_cpdf()->setPreferences('PrintScaling', 'None');

        return $pdf->output();
    }
}
