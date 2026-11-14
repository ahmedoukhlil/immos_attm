<?php

namespace App\Services;

use App\Models\EtiquetteImpression;
use App\Models\Immobilisation;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EtiquetteService
{
    public const LARGEUR_MM = 70.0;

    public const HAUTEUR_MM = 24.4;

    public const FORMATS_PAGE = [
        'a4_portrait' => ['largeur' => 210.0, 'hauteur' => 297.0, 'libelle' => 'A4 portrait'],
        'a4_paysage' => ['largeur' => 297.0, 'hauteur' => 210.0, 'libelle' => 'A4 paysage'],
        'a5_portrait' => ['largeur' => 148.0, 'hauteur' => 210.0, 'libelle' => 'A5 portrait'],
        'a5_paysage' => ['largeur' => 210.0, 'hauteur' => 148.0, 'libelle' => 'A5 paysage'],
        'individuelle' => ['largeur' => self::LARGEUR_MM, 'hauteur' => self::HAUTEUR_MM, 'libelle' => 'Une étiquette par page'],
    ];

    /**
     * Normalise les paramètres physiques et calcule la grille imprimable.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, int|float|string|bool>
     */
    public function configurationImpression(array $options): array
    {
        $format = array_key_exists($options['page_format'] ?? '', self::FORMATS_PAGE)
            ? $options['page_format']
            : 'individuelle';

        $largeurEtiquette = (float) ($options['label_width'] ?? self::LARGEUR_MM);
        $hauteurEtiquette = (float) ($options['label_height'] ?? self::HAUTEUR_MM);
        $marge = (float) ($options['page_margin'] ?? 0);
        $espacementHorizontal = (float) ($options['gap_x'] ?? 0);
        $espacementVertical = (float) ($options['gap_y'] ?? 0);
        // Décalages de compensation : corrigent le léger glissement propre à chaque imprimante.
        $decalageHorizontal = (float) ($options['offset_x'] ?? 0);
        $decalageVertical = (float) ($options['offset_y'] ?? 0);
        // Bande que l'imprimante ne sait pas encrer : la grille reste calée sur la planche,
        // seul le contenu des étiquettes qui la chevauchent est rentré vers l'intérieur.
        $zoneNonImprimable = (float) ($options['printer_margin'] ?? 4);
        // Agrandit le contenu sans modifier les dimensions physiques de l'étiquette.
        $echelleContenuDemandee = max(100.0, min(125.0, (float) ($options['content_scale'] ?? 100)));
        $page = self::FORMATS_PAGE[$format];
        $modeGrille = ($options['grid_mode'] ?? 'dimensions') === 'manual' ? 'manual' : 'dimensions';

        if ($format === 'individuelle') {
            $page = ['largeur' => $largeurEtiquette, 'hauteur' => $hauteurEtiquette];
            $marge = $espacementHorizontal = $espacementVertical = $decalageHorizontal = $decalageVertical = $zoneNonImprimable = 0.0;
            $modeGrille = 'dimensions';
        }

        $largeurUtile = max(0, $page['largeur'] - (2 * $marge));
        $hauteurUtile = max(0, $page['hauteur'] - (2 * $marge));

        if ($modeGrille === 'manual') {
            $colonnes = max(1, min(12, (int) ($options['columns'] ?? 1)));
            $lignes = max(1, min(20, (int) ($options['rows'] ?? 1)));
            $largeurEtiquette = ($largeurUtile - (($colonnes - 1) * $espacementHorizontal)) / $colonnes;
            $hauteurEtiquette = ($hauteurUtile - (($lignes - 1) * $espacementVertical)) / $lignes;
        } else {
            $colonnes = max(1, (int) floor(($largeurUtile + $espacementHorizontal) / ($largeurEtiquette + $espacementHorizontal)));
            $lignes = max(1, (int) floor(($hauteurUtile + $espacementVertical) / ($hauteurEtiquette + $espacementVertical)));
        }

        $tailleQr = min(
            (float) ($options['qr_size'] ?? 18),
            max(8, $hauteurEtiquette - 8.5),
            max(8, $largeurEtiquette * 0.46)
        );
        $echelleMaximale = min(
            1.25,
            max(1.0, ($hauteurEtiquette - 2.4) / max(1.0, $tailleQr)),
            max(1.0, ($largeurEtiquette * 0.46) / max(1.0, $tailleQr)),
        );
        $echelleContenu = min($echelleContenuDemandee / 100, $echelleMaximale) * 100;

        return [
            'page_format' => $format,
            'grid_mode' => $modeGrille,
            'page_width' => (float) $page['largeur'],
            'page_height' => (float) $page['hauteur'],
            'label_width' => $largeurEtiquette,
            'label_height' => $hauteurEtiquette,
            'page_margin' => $marge,
            'gap_x' => $espacementHorizontal,
            'gap_y' => $espacementVertical,
            'offset_x' => $decalageHorizontal,
            'offset_y' => $decalageVertical,
            'printer_margin' => $zoneNonImprimable,
            'columns' => $colonnes,
            'rows' => $lignes,
            'labels_per_page' => $colonnes * $lignes,
            'start_position' => max(1, (int) ($options['start_position'] ?? 1)),
            'qr_size' => $tailleQr,
            'content_scale' => round($echelleContenu, 1),
            'compact_layout' => $largeurEtiquette < 45 || $hauteurEtiquette < 24,
            'narrow_layout' => $largeurEtiquette < 45,
            'short_layout' => $hauteurEtiquette < 24,
            'is_horizontal' => $largeurEtiquette > $hauteurEtiquette,
            'background_color' => $options['background_color'] ?? '#FFFFFF',
            'text_color' => $options['text_color'] ?? '#111827',
            'show_designation' => (bool) ($options['show_designation'] ?? true),
            'show_emplacement' => (bool) ($options['show_emplacement'] ?? true),
            'show_code' => (bool) ($options['show_code'] ?? true),
        ];
    }

    /**
     * @param  array<int, int|string>  $bienIds
     * @return Collection<int, array{bien: Immobilisation, composant: mixed, code: string, code_affiche: string, contenu_qr: string, nom_composant: ?string}>
     */
    public function etiquettesPourSite(Site $site, string $mode = 'complete', array $bienIds = []): Collection
    {
        $biens = $site->immobilisations()
            ->with([
                'designation.composants',
                'categorie',
                'site.localisation',
            ])
            ->when($mode === 'selection', fn ($query) => $query->whereIn('id', $bienIds))
            ->orderBy('id')
            ->get();

        $etiquettes = $biens->flatMap(fn (Immobilisation $bien): Collection => $this->etiquettesPourBien($bien));

        if ($mode !== 'nouvelles') {
            return $etiquettes->values();
        }

        $codesDejaGeneres = EtiquetteImpression::query()
            ->where('site_id', $site->id)
            ->whereIn('statut', ['generee', 'imprimee', 'reimprimee'])
            ->pluck('code_etiquette')
            ->all();

        return $etiquettes
            ->reject(fn (array $etiquette): bool => in_array($etiquette['code'], $codesDejaGeneres, true))
            ->values();
    }

    /**
     * @return Collection<int, array{bien: Immobilisation, composant: mixed, code: string, code_affiche: string, contenu_qr: string, nom_composant: ?string}>
     */
    public function etiquettesPourBien(Immobilisation $bien): Collection
    {
        $bien->loadMissing(['designation.composants', 'categorie']);
        $composants = $bien->designation?->composants ?? collect();

        if (! $bien->designation?->est_compose || $composants->isEmpty()) {
            return collect([[
                'bien' => $bien,
                'composant' => null,
                'code' => $bien->scan_code,
                'code_affiche' => $bien->code_formate,
                'contenu_qr' => $bien->contenu_qr,
                'nom_composant' => null,
            ]]);
        }

        return $composants->flatMap(function ($composant) use ($bien): array {
            return collect(range(1, max(1, $composant->quantite)))
                ->map(function (int $numero) use ($bien, $composant): array {
                    $suffixe = $composant->quantite > 1 ? "-{$numero}" : '';

                    return [
                        'bien' => $bien,
                        'composant' => $composant,
                        'code' => "{$bien->scan_code}-{$composant->code}{$suffixe}",
                        'code_affiche' => "{$bien->code_formate}-{$composant->code}{$suffixe}",
                        'contenu_qr' => Immobilisation::contenuQr(
                            $bien->code_propose ? "{$bien->code_propose}-{$composant->code}{$suffixe}" : null,
                            "{$bien->scan_code}-{$composant->code}{$suffixe}",
                        ),
                        'nom_composant' => $composant->nom,
                    ];
                })
                ->all();
        })->values();
    }

    /**
     * HTML du gabarit d'étiquettes, tel qu'il est converti en PDF.
     * Le QR code contient « code d'inventaire | GS… » ; le code affiché est en clair.
     *
     * @param  Collection<int, array<string, mixed>>  $etiquettes
     * @param  array<string, mixed>  $configuration
     */
    public function html(Collection $etiquettes, Site $site, array $configuration): string
    {
        $etiquettesAvecQr = $etiquettes->map(function (array $etiquette): array {
            $etiquette['qr_base64'] = base64_encode(
                QrCode::format('svg')
                    ->size(320)
                    ->margin(1)
                    ->errorCorrection('M')
                    ->generate($etiquette['contenu_qr'] ?? $etiquette['code'])
            );

            return $etiquette;
        });

        return view('pdf.etiquettes-site', [
            'site' => $site->loadMissing(['localisation']),
            'etiquettes' => $etiquettesAvecQr,
            'configuration' => $configuration,
        ])->render();
    }

    /** @return array{immobilisations: int, composants: int, total: int, nouvelles: int} */
    public function resume(Site $site): array
    {
        $toutes = $this->etiquettesPourSite($site);
        $immobilisations = $toutes->pluck('bien.id')->unique()->count();

        return [
            'immobilisations' => $immobilisations,
            'composants' => $toutes->whereNotNull('composant')->count(),
            'total' => $toutes->count(),
            'nouvelles' => $this->etiquettesPourSite($site, 'nouvelles')->count(),
        ];
    }

    /**
     * @param  Collection<int, array{bien: Immobilisation, composant: mixed, code: string, code_affiche: string, contenu_qr: string, nom_composant: ?string}>  $etiquettes
     */
    public function tracerGeneration(Collection $etiquettes, Site $site, int $userId, string $mode): string
    {
        $generationUuid = (string) Str::uuid();

        $etiquettes->each(function (array $etiquette) use ($generationUuid, $site, $userId, $mode): void {
            EtiquetteImpression::create([
                'generation_uuid' => $generationUuid,
                'immobilisation_id' => $etiquette['bien']->id,
                'designation_composant_id' => $etiquette['composant']?->id,
                'site_id' => $site->id,
                'user_id' => $userId,
                'code_etiquette' => $etiquette['code'],
                'nom_composant' => $etiquette['nom_composant'],
                'mode' => $mode,
                'exemplaires' => 1,
                'statut' => 'generee',
                'generee_at' => now(),
            ]);
        });

        return $generationUuid;
    }
}
