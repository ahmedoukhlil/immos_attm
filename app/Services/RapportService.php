<?php

namespace App\Services;

use App\Exports\InventaireExport;
use App\Exports\Sheets\BiensAbsentsSheet;
use App\Exports\Sheets\BiensDeplacesSheet;
use App\Exports\Sheets\BiensPresentsSheet;
use App\Models\Immobilisation;
use App\Models\Inventaire;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Service dédié à la génération des rapports d'inventaire
 * Centralise la logique de génération des rapports PDF et Excel
 */
class RapportService
{
    private array $referentiels = [];

    private array $scans = [];

    private array $localisations = [];

    private ?Collection $responsables = null;

    /**
     * Générer un rapport PDF complet d'un inventaire
     *
     * @return string Chemin du fichier PDF généré
     */
    /**
     * Préparer les données pour le rapport PDF
     */
    protected function preparerDonneesRapport(Inventaire $inventaire): array
    {
        $inventaire->load([
            'creator',
            'closer',
        ]);

        $inventaireService = app(InventaireService::class);
        $localisations = $this->localisationsPourRapport($inventaire);
        $scans = $this->scansPourRapport($inventaire);
        $referentiel = $this->referentielFige($inventaire);
        $statistiques = $inventaireService->calculerStatistiques($inventaire, $localisations, $scans, $referentiel);
        $anomalies = $inventaireService->detecterAnomalies(
            $inventaire,
            $localisations,
            $scans,
            $referentiel->keyBy('immobilisation_id'),
        );

        $detailParSite = $this->getDetailParSite($inventaire);
        $biensPresents = $this->getBiensPresents($inventaire);
        $biensDeplaces = $this->getBiensDeplaces($inventaire);
        $biensAbsents = $this->getBiensAbsents($inventaire);
        $biensNonScannes = $this->getBiensNonScannes($inventaire);
        $performanceLocalisations = $this->getPerformanceLocalisations($inventaire);
        $performanceAgents = $this->getPerformanceAgents($inventaire);
        $mouvements = $this->getAnalyseMouvements($inventaire, $biensDeplaces);

        return [
            'inventaire' => $inventaire,
            'statistiques' => $statistiques,
            'anomalies' => $anomalies,
            'detailParSite' => $detailParSite,
            'biensPresents' => $biensPresents,
            'biensDeplaces' => $biensDeplaces,
            'biensAbsents' => $biensAbsents,
            'biensNonScannes' => $biensNonScannes,
            'performanceLocalisations' => $performanceLocalisations,
            'performanceAgents' => $performanceAgents,
            'mouvements' => $mouvements,
            'recommendations' => $this->genererRecommandations(
                $inventaire,
                $statistiques,
                $anomalies,
                $performanceLocalisations,
                $mouvements,
            ),
        ];
    }

    /**
     * Rapprochement détaillé par site (PDF et vue rapport)
     * Pour chaque site :
     *  - Biens attendus : trouvés ou manquants
     *  - Biens déplacés : trouvés ici mais enregistrés dans un autre site
     */
    public function getDetailParSite(Inventaire $inventaire): array
    {
        $referentiel = $this->referentielFige($inventaire);

        if ($inventaire->hasFrozenReference()) {
            return $this->getDetailParSiteFige($inventaire, $referentiel);
        }

        $localisationIds = $inventaire->inventaireLocalisations()->pluck('localisation_id')->unique()->toArray();
        $sites = Site::whereIn('localisation_id', $localisationIds)
            ->with([
                'localisation',
                'immobilisations.designation',
                'immobilisations.categorie',
            ])
            ->orderBy('site')
            ->get();

        // Tous les scans de cet inventaire, indexés par immobilisation
        $allScans = $this->scansPourRapport($inventaire);

        $scansByBienId = $allScans->keyBy('immobilisation_id');

        $scansDeplacesParSite = [];
        foreach ($allScans as $scan) {
            if ($scan->statut_scan === 'deplace' && $scan->site_reel_id) {
                $scansDeplacesParSite[$scan->site_reel_id][] = $scan;
            }
        }

        $result = [];
        foreach ($sites as $site) {
            $lignesAttendues = [];
            $lignesDeplaces = [];
            $totalTrouves = 0;
            $totalManquants = 0;

            // Biens attendus dans ce site
            foreach ($site->immobilisations as $immobilisation) {
                $scan = $scansByBienId->get($immobilisation->id);
                $trouve = $scan && in_array($scan->statut_scan, ['present', 'deplace']) ? true : false;
                $estPresent = $scan && $scan->statut_scan === 'present';
                $etat = $scan ? $this->labelEtat($scan->etat_constate) : '-';

                if ($estPresent) {
                    $observation = 'Présent';
                    $totalTrouves++;
                } elseif ($scan && $scan->statut_scan === 'deplace') {
                    $observation = 'Déplacé';
                    $totalManquants++;
                } elseif ($scan && $scan->statut_scan === 'absent') {
                    $observation = 'Absent';
                    $totalManquants++;
                } else {
                    $observation = 'Non scanné';
                    $totalManquants++;
                }

                $lignesAttendues[] = [
                    'num_ordre' => $immobilisation->id,
                    'code' => $immobilisation->code_formate,
                    'designation' => $immobilisation->libelle_article ?? 'N/A',
                    'categorie' => $immobilisation->categorie->categorie ?? '-',
                    'attendu' => 'Oui',
                    'trouve' => $estPresent ? 'Oui' : 'Non',
                    'etat' => $etat,
                    'observation' => $observation,
                    'conformite' => $observation,
                    'statut_scan' => $scan?->statut_scan,
                ];
            }

            // Biens déplacés trouvés dans ce site (venant d'ailleurs)
            $deplacesTrouves = $scansDeplacesParSite[$site->id] ?? [];
            foreach ($deplacesTrouves as $scan) {
                $siteOrigine = $scan->bien?->site;
                $origineNom = $siteOrigine ? ($siteOrigine->site ?? $siteOrigine->code ?? 'N/A') : 'Inconnu';
                $etat = $this->labelEtat($scan->etat_constate);

                $lignesDeplaces[] = [
                    'num_ordre' => $scan->immobilisation_id,
                    'code' => $scan->code_inventaire,
                    'designation' => $scan->bien?->libelle_article ?? 'N/A',
                    'categorie' => $scan->bien?->categorie->categorie ?? '-',
                    'attendu' => 'Non',
                    'trouve' => 'Oui',
                    'etat' => $etat,
                    'observation' => 'Déplacé de: '.$origineNom,
                    'conformite' => 'Déplacé',
                    'statut_scan' => 'deplace',
                ];
            }

            $totalAttendus = count($lignesAttendues);
            $tauxConformite = $totalAttendus > 0 ? round(($totalTrouves / $totalAttendus) * 100, 1) : 0;

            $result[] = [
                'site_id' => $site->id,
                'code' => $site->code ?? $site->site,
                'designation' => $site->site,
                'localisation' => $site->localisation?->localisation ?? 'N/A',
                'responsable' => $site->responsable ?? '-',
                'total_attendus' => $totalAttendus,
                'total_trouves' => $totalTrouves,
                'total_manquants' => $totalManquants,
                'total_deplaces_ici' => count($lignesDeplaces),
                'taux_conformite' => $tauxConformite,
                'lignes' => array_merge($lignesAttendues, $lignesDeplaces),
                'lignes_attendues' => $lignesAttendues,
                'lignes_deplaces' => $lignesDeplaces,
            ];
        }

        return $result;
    }

    /**
     * Construit le rapprochement depuis le référentiel immuable de l'inventaire.
     */
    private function getDetailParSiteFige(Inventaire $inventaire, Collection $referentiel): array
    {
        $scans = $this->scansPourRapport($inventaire);
        $scansParBien = $scans->keyBy('immobilisation_id');
        $referentielParBien = $referentiel->keyBy('immobilisation_id');
        $responsables = $this->responsablesParSite();
        $deplacesParSite = $scans
            ->where('statut_scan', 'deplace')
            ->whereNotNull('site_reel_id')
            ->groupBy('site_reel_id');

        return $referentiel
            ->groupBy('site_id')
            ->sortKeys()
            ->map(function (Collection $biens) use ($scansParBien, $referentielParBien, $deplacesParSite, $responsables): array {
                $premier = $biens->first();
                $lignesAttendues = $biens->sortBy('immobilisation_id')->map(function ($bien) use ($scansParBien): array {
                    $scan = $scansParBien->get($bien->immobilisation_id);
                    $estPresent = $scan?->statut_scan === 'present';
                    $observation = match ($scan?->statut_scan) {
                        'present' => 'Présent',
                        'deplace' => 'Déplacé',
                        'absent' => 'Absent',
                        'deteriore' => 'Détérioré',
                        default => 'Non scanné',
                    };

                    return [
                        'num_ordre' => $bien->immobilisation_id,
                        'code' => $bien->code_affiche,
                        'designation' => $bien->designation ?? 'N/A',
                        'categorie' => $bien->categorie ?? '-',
                        'attendu' => 'Oui',
                        'trouve' => $estPresent ? 'Oui' : 'Non',
                        'etat' => $scan ? $this->labelEtat($scan->etat_constate) : '-',
                        'observation' => $observation,
                        'conformite' => $observation,
                        'statut_scan' => $scan?->statut_scan,
                    ];
                })->values()->all();

                $lignesDeplaces = collect($deplacesParSite->get($premier->site_id, []))
                    ->map(function ($scan) use ($referentielParBien): array {
                        $origine = $referentielParBien->get($scan->immobilisation_id);

                        return [
                            'num_ordre' => $scan->immobilisation_id,
                            'code' => $origine?->code_affiche ?? $scan->code_inventaire,
                            'designation' => $origine?->designation ?? $scan->designation,
                            'categorie' => $origine?->categorie ?? $scan->categorie ?? '-',
                            'attendu' => 'Non',
                            'trouve' => 'Oui',
                            'etat' => $this->labelEtat($scan->etat_constate),
                            'observation' => 'Déplacé de: '.($origine?->site_nom ?? $origine?->site_code ?? 'Inconnu'),
                            'conformite' => 'Déplacé',
                            'statut_scan' => 'deplace',
                        ];
                    })
                    ->values()
                    ->all();

                $totalAttendus = $biens->count();
                $totalTrouves = $biens->filter(fn ($bien): bool => $scansParBien->get($bien->immobilisation_id)?->statut_scan === 'present')->count();

                return [
                    'site_id' => $premier->site_id,
                    'code' => $premier->site_code ?? $premier->site_nom ?? 'N/A',
                    'designation' => $premier->site_nom ?? $premier->site_code ?? 'N/A',
                    'localisation' => $premier->localisation_nom ?? $premier->localisation_code ?? 'N/A',
                    'responsable' => $responsables->get($premier->site_id) ?? '-',
                    'total_attendus' => $totalAttendus,
                    'total_trouves' => $totalTrouves,
                    'total_manquants' => $totalAttendus - $totalTrouves,
                    'total_deplaces_ici' => count($lignesDeplaces),
                    'taux_conformite' => $totalAttendus > 0 ? round(($totalTrouves / $totalAttendus) * 100, 1) : 0,
                    'lignes' => array_merge($lignesAttendues, $lignesDeplaces),
                    'lignes_attendues' => $lignesAttendues,
                    'lignes_deplaces' => $lignesDeplaces,
                ];
            })
            ->values()
            ->all();
    }

    private function referentielFige(Inventaire $inventaire): Collection
    {
        if (array_key_exists($inventaire->id, $this->referentiels)) {
            return $this->referentiels[$inventaire->id];
        }

        return $this->referentiels[$inventaire->id] = $inventaire->biensAttendus()->get();
    }

    /**
     * Responsable actuel de chaque site, le référentiel figé ne le conservant pas.
     */
    private function responsablesParSite(): Collection
    {
        return $this->responsables ??= Site::query()->pluck('responsable', 'id');
    }

    private function scansPourRapport(Inventaire $inventaire): Collection
    {
        if (array_key_exists($inventaire->id, $this->scans)) {
            return $this->scans[$inventaire->id];
        }

        return $this->scans[$inventaire->id] = $inventaire->inventaireScans()
            ->with([
                'bien.designation',
                'bien.categorie',
                'bien.site.localisation',
                'localisationReelle',
                'siteReel',
                'agent',
            ])
            ->get();
    }

    private function localisationsPourRapport(Inventaire $inventaire): Collection
    {
        if (array_key_exists($inventaire->id, $this->localisations)) {
            return $this->localisations[$inventaire->id];
        }

        return $this->localisations[$inventaire->id] = $inventaire->inventaireLocalisations()
            ->with(['localisation', 'agent', 'agents:idUser,users'])
            ->get();
    }

    protected function labelConformite(?string $statut): string
    {
        return match ($statut) {
            'present' => 'Présent',
            'absent' => 'Absent',
            'deplace' => 'Déplacé',
            'deteriore' => 'Détérioré',
            default => $statut ?? 'Non scanné',
        };
    }

    protected function labelEtat(?string $etat): string
    {
        return match ($etat) {
            'bon_etat' => 'Bon Etat',
            'neuf' => 'Neuf',
            'defectueux' => 'Defectueux',
            default => '-',
        };
    }

    public function genererRapportPDF(Inventaire $inventaire)
    {
        $data = $this->preparerDonneesRapport($inventaire);

        $pdf = Pdf::loadView('pdf.rapport-inventaire', $data);

        // Configuration PDF
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('isPhpEnabled', false);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        // Nom du fichier
        $filename = 'rapport_inventaire_'.$inventaire->annee.'_'.now()->format('YmdHis').'.pdf';
        $dir = 'rapports/'.$inventaire->annee;
        $path = $dir.'/'.$filename;

        // Créer le répertoire si nécessaire
        if (! Storage::disk('local')->exists($dir)) {
            Storage::disk('local')->makeDirectory($dir);
        }

        // Sauvegarder
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Générer et retourner le PDF en flux direct (sans sauvegarde disque)
     * Évite les problèmes de chemin ou permissions sur le stockage
     *
     * @return Response
     */
    public function streamRapportPDF(Inventaire $inventaire)
    {
        $data = $this->preparerDonneesRapport($inventaire);
        $pdf = Pdf::loadView('pdf.rapport-inventaire', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('isPhpEnabled', false);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        $filename = 'rapport_inventaire_'.$inventaire->annee.'_'.now()->format('Y-m-d_His').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Générer un rapport Excel complet d'un inventaire
     *
     * @return string Chemin du fichier Excel généré
     */
    public function genererRapportExcel(Inventaire $inventaire)
    {
        $filename = 'rapport_inventaire_'.$inventaire->annee.'_'.now()->format('YmdHis').'.xlsx';
        $path = 'rapports/'.$inventaire->annee.'/'.$filename;

        Excel::store(new InventaireExport($inventaire), $path, 'local');

        return $path;
    }

    /**
     * Récupérer les biens présents conformes
     *
     * @return Collection
     */
    public function getBiensPresents(Inventaire $inventaire)
    {
        $referentiel = $this->referentielFige($inventaire)->keyBy('immobilisation_id');
        $responsables = $this->responsablesParSite();

        return $this->scansPourRapport($inventaire)
            ->where('statut_scan', 'present')
            ->map(function ($scan) use ($referentiel, $responsables) {
                $fige = $referentiel->get($scan->immobilisation_id);
                $locPrevue = $fige?->localisation_id ?? $scan->bien?->site?->localisation_id;
                $isConforme = $locPrevue !== null
                    ? (int) $locPrevue === (int) $scan->localisation_reelle_id
                    : true;

                return [
                    'code' => $fige?->code_affiche ?? $scan->code_inventaire,
                    'designation' => $fige?->designation ?? $scan->designation,
                    'nature' => $fige?->categorie ?? $scan->categorie,
                    'localisation' => $fige?->localisation_code ?? $fige?->localisation_nom ?? $scan->localisation_code,
                    'site' => $fige?->site_nom ?? $scan->bien?->site?->site,
                    'responsable' => $responsables->get($fige?->site_id ?? $scan->bien?->site_id),
                    'etat' => $scan->etat_constate,
                    'date_scan' => $scan->date_scan,
                    'agent' => $scan->agent->users ?? null,
                    'conforme' => $isConforme,
                ];
            });
    }

    /**
     * Récupérer les biens déplacés
     *
     * @return Collection
     */
    public function getBiensDeplaces(Inventaire $inventaire)
    {
        $referentiel = $this->referentielFige($inventaire)->keyBy('immobilisation_id');
        $responsables = $this->responsablesParSite();

        return $this->scansPourRapport($inventaire)
            ->where('statut_scan', 'deplace')
            ->map(function ($scan) use ($referentiel, $responsables) {
                $fige = $referentiel->get($scan->immobilisation_id);
                $locPrevue = $fige?->localisation_code ?? $fige?->localisation_nom ?? $scan->localisation_code;
                $locReelle = $scan->localisationReelle?->code ?? $scan->localisationReelle?->localisation ?? null;

                return [
                    'code' => $fige?->code_affiche ?? $scan->code_inventaire,
                    'designation' => $fige?->designation ?? $scan->designation,
                    'nature' => $fige?->categorie ?? $scan->categorie,
                    'localisation_prevue' => $locPrevue,
                    'localisation_reelle' => $locReelle,
                    'site' => $fige?->site_nom ?? $scan->bien?->site?->site,
                    'site_reel' => $scan->siteReel?->site,
                    'responsable' => $responsables->get($fige?->site_id ?? $scan->bien?->site_id),
                    'date_scan' => $scan->date_scan,
                    'agent' => $scan->agent->users ?? null,
                    'commentaire' => $scan->commentaire,
                ];
            });
    }

    /**
     * Récupérer les biens absents
     *
     * @return Collection
     */
    public function getBiensAbsents(Inventaire $inventaire)
    {
        $referentiel = $this->referentielFige($inventaire)->keyBy('immobilisation_id');
        $responsables = $this->responsablesParSite();

        return $this->scansPourRapport($inventaire)
            ->where('statut_scan', 'absent')
            ->map(function ($scan) use ($referentiel, $responsables) {
                $fige = $referentiel->get($scan->immobilisation_id);

                return [
                    'code' => $fige?->code_affiche ?? $scan->code_inventaire,
                    'designation' => $fige?->designation ?? $scan->designation,
                    'nature' => $fige?->categorie ?? $scan->categorie,
                    'localisation' => $fige?->localisation_code ?? $fige?->localisation_nom ?? $scan->localisation_code,
                    'site' => $fige?->site_nom ?? $scan->bien?->site?->site,
                    'responsable' => $responsables->get($fige?->site_id ?? $scan->bien?->site_id),
                    'date_scan' => $scan->date_scan,
                    'agent' => $scan->agent->users ?? null,
                    'commentaire' => $scan->commentaire,
                ];
            });
    }

    /**
     * Récupérer les biens non scannés
     *
     * @return Collection
     */
    public function getBiensNonScannes(Inventaire $inventaire)
    {
        $biensScannesIds = $this->scansPourRapport($inventaire)->pluck('immobilisation_id');

        $referentiel = $this->referentielFige($inventaire);
        if ($inventaire->hasFrozenReference()) {
            $responsables = $this->responsablesParSite();

            return $referentiel
                ->whereNotIn('immobilisation_id', $biensScannesIds)
                ->map(fn ($bien): array => [
                    'code' => $bien->code_affiche,
                    'designation' => $bien->designation ?? 'N/A',
                    'nature' => $bien->categorie,
                    'localisation' => $bien->localisation_code ?? $bien->localisation_nom,
                    'site' => $bien->site_nom,
                    'responsable' => $responsables->get($bien->site_id),
                ])
                ->values();
        }

        $localisationsIds = $inventaire->inventaireLocalisations()
            ->pluck('localisation_id');

        // Biens attendus mais non scannés (immobilisation → site → localisation)
        return Immobilisation::query()
            ->whereHas('site', fn ($query) => $query->whereIn('localisation_id', $localisationsIds))
            ->whereNotIn('id', $biensScannesIds)
            ->with(['designation', 'categorie', 'site.localisation'])
            ->get()
            ->map(fn (Immobilisation $bien): array => [
                'code' => $bien->code_formate,
                'designation' => $bien->libelle_article ?? 'N/A',
                'nature' => $bien->categorie?->categorie,
                'localisation' => $bien->site?->localisation?->libelle_court,
                'site' => $bien->site?->site,
                'responsable' => $bien->site?->responsable,
            ]);
    }

    /**
     * Analyser la performance par localisation
     *
     * @return Collection
     */
    public function getPerformanceLocalisations(Inventaire $inventaire)
    {
        $scansParLocalisation = $this->scansPourRapport($inventaire)
            ->groupBy('inventaire_localisation_id');

        return $this->localisationsPourRapport($inventaire)
            ->map(function ($invLoc) use ($scansParLocalisation) {
                $scans = $scansParLocalisation->get($invLoc->id, collect());
                $scansPhysiques = $scans->where('statut_scan', '!=', 'absent');
                $presents = $scans->where('statut_scan', 'present')->count();
                $deplaces = $scans->where('statut_scan', 'deplace')->count();
                $absents = $scans->where('statut_scan', 'absent')->count();

                $tauxConformite = $invLoc->nombre_biens_attendus > 0
                    ? round(($presents / $invLoc->nombre_biens_attendus) * 100, 2)
                    : 0;

                $duree = null;
                if ($invLoc->date_debut_scan && $invLoc->date_fin_scan) {
                    $duree = $invLoc->date_debut_scan->diffInMinutes($invLoc->date_fin_scan);
                }

                return [
                    'code' => $invLoc->localisation->code ?? null,
                    'designation' => $invLoc->localisation->localisation ?? null,
                    'attendus' => $invLoc->nombre_biens_attendus,
                    'scannes' => $scansPhysiques->unique('immobilisation_id')->count(),
                    'presents' => $presents,
                    'deplaces' => $deplaces,
                    'absents' => $absents,
                    'taux_conformite' => $tauxConformite,
                    'duree_minutes' => $duree,
                    'agent' => $invLoc->agent ? $invLoc->agent->users : 'Non assigné',
                    'statut' => $invLoc->statut,
                ];
            })
            ->sortBy('taux_conformite');
    }

    /**
     * Analyser la performance par agent
     *
     * @return Collection
     */
    public function getPerformanceAgents(Inventaire $inventaire)
    {
        $scansParAgent = $this->scansPourRapport($inventaire)
            ->where('statut_scan', '!=', 'absent')
            ->groupBy('user_id');

        $localisations = $this->localisationsPourRapport($inventaire);
        $performance = [];

        foreach ($scansParAgent as $userId => $scans) {
            $agent = $scans->first()->agent;

            if (! $agent) {
                continue;
            }

            $localisationsAgent = $localisations->filter(
                fn ($localisation) => (int) $localisation->user_id === (int) $userId
                    || $localisation->agents->contains('idUser', (int) $userId),
            );

            $dureeTotale = $localisationsAgent->reduce(function ($total, $invLoc) {
                if ($invLoc->date_debut_scan && $invLoc->date_fin_scan) {
                    return $total + $invLoc->date_debut_scan->diffInMinutes($invLoc->date_fin_scan);
                }

                return $total;
            }, 0);

            $performance[] = [
                'agent' => $agent->users,
                'localisations' => $localisationsAgent->count(),
                'localisations_terminees' => $localisationsAgent->where('statut', 'termine')->count(),
                'biens_scannes' => $scans->count(),
                'duree_totale_minutes' => $dureeTotale,
                'moyenne_par_localisation' => $localisationsAgent->count() > 0
                    ? round($dureeTotale / $localisationsAgent->count(), 0)
                    : 0,
                'moyenne_par_bien' => $scans->count() > 0
                    ? round($dureeTotale / $scans->count(), 2)
                    : 0,
            ];
        }

        return collect($performance)->sortByDesc('biens_scannes');
    }

    /**
     * Analyser les mouvements de biens
     *
     * @return array
     */
    public function getAnalyseMouvements(Inventaire $inventaire, ?Collection $biensDeplaces = null)
    {
        $biensDeplaces ??= $this->getBiensDeplaces($inventaire);

        // Flux : Localisation A → Localisation B (nombre de biens)
        $flux = [];
        foreach ($biensDeplaces as $bien) {
            $key = $bien['localisation_prevue'].' → '.$bien['localisation_reelle'];

            if (! isset($flux[$key])) {
                $flux[$key] = [
                    'origine' => $bien['localisation_prevue'],
                    'destination' => $bien['localisation_reelle'],
                    'nombre_biens' => 0,
                ];
            }

            $flux[$key]['nombre_biens']++;
        }

        return [
            'total_deplaces' => $biensDeplaces->count(),
            'flux' => collect($flux)->sortByDesc('nombre_biens')->values(),
        ];
    }

    /**
     * Générer des recommandations basées sur l'analyse
     *
     * @return array
     */
    private function genererRecommandations(
        Inventaire $inventaire,
        array $statistiques,
        array $anomalies,
        ?Collection $performanceLocalisations = null,
        ?array $mouvements = null,
    ) {
        $recommendations = [
            'corrections_immediates' => [],
            'ameliorations_organisationnelles' => [],
            'prochain_inventaire' => [],
        ];

        // Corrections immédiates
        if ($statistiques['biens_deplaces'] > 0) {
            $recommendations['corrections_immediates'][] =
                "Mettre à jour les localisations de {$statistiques['biens_deplaces']} bien(s) déplacé(s) dans le système.";
        }

        if ($statistiques['biens_absents'] > 0) {
            $recommendations['corrections_immediates'][] =
                "Investiguer {$statistiques['biens_absents']} bien(s) absent(s).";
        }

        if (count($anomalies['biens_deteriores'] ?? []) > 0) {
            $recommendations['corrections_immediates'][] =
                'Planifier la réparation ou le remplacement de '.
                count($anomalies['biens_deteriores']).' bien(s) détérioré(s).';
        }

        // Améliorations organisationnelles
        $localisationsProblematiques = ($performanceLocalisations ?? $this->getPerformanceLocalisations($inventaire))
            ->where('taux_conformite', '<', 80);

        if ($localisationsProblematiques->count() > 0) {
            $recommendations['ameliorations_organisationnelles'][] =
                'Améliorer la gestion de '.$localisationsProblematiques->count().
                ' localisation(s) avec un taux de conformité inférieur à 80%.';
        }

        $mouvements ??= $this->getAnalyseMouvements($inventaire);
        if (count($mouvements['flux']) > 5) {
            $recommendations['ameliorations_organisationnelles'][] =
                "Analyser les flux de déplacement fréquents et optimiser l'organisation spatiale.";
        }

        // Prochain inventaire
        $debut = $inventaire->started_at ?? $inventaire->date_debut;
        $fin = $inventaire->finished_at ?? $inventaire->date_fin ?? now();
        $duree = $debut->diffInDays($fin);
        $recommendations['prochain_inventaire'][] =
            "Prévoir environ {$duree} jours pour le prochain inventaire basé sur cette expérience.";

        $nbAgents = $this->localisationsPourRapport($inventaire)
            ->flatMap(fn ($localisation) => collect([$localisation->user_id])
                ->merge($localisation->agents->pluck('idUser')))
            ->filter()
            ->unique()
            ->count();

        $recommendations['prochain_inventaire'][] =
            "Mobiliser au minimum {$nbAgents} agent(s) pour maintenir l'efficacité.";

        if ($statistiques['taux_conformite'] < 90) {
            $recommendations['prochain_inventaire'][] =
                "Renforcer la formation des agents sur les procédures de scan et d'identification.";
        }

        return $recommendations;
    }

    /**
     * Générer un rapport par localisation (PDF)
     *
     * @param  int  $localisationId
     * @return string
     */
    public function genererRapportLocalisation(Inventaire $inventaire, $localisationId)
    {
        $inventaireLocalisation = $inventaire->inventaireLocalisations()
            ->where('localisation_id', $localisationId)
            ->with(['localisation', 'agent', 'inventaireScans.bien.designation'])
            ->firstOrFail();

        $data = [
            'inventaire' => $inventaire,
            'inventaireLocalisation' => $inventaireLocalisation,
        ];

        $pdf = Pdf::loadView('pdf.rapport-localisation', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'rapport_'.$inventaireLocalisation->localisation->code.'_'.
                    $inventaire->annee.'_'.now()->format('YmdHis').'.pdf';
        $path = 'rapports/'.$inventaire->annee.'/localisations/'.$filename;

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Générer un export Excel des biens par statut
     *
     * @param  string  $statut
     * @return string
     */
    public function exportBiensParStatut(Inventaire $inventaire, $statut)
    {
        $export = match ($statut) {
            'present' => new BiensPresentsSheet($inventaire),
            'deplace' => new BiensDeplacesSheet($inventaire),
            'absent' => new BiensAbsentsSheet($inventaire),
            default => throw new \InvalidArgumentException('Statut de rapport non pris en charge.'),
        };

        $filename = "biens_{$statut}_{$inventaire->annee}_".now()->format('YmdHis').'.xlsx';
        $path = 'exports/'.$inventaire->annee.'/'.$filename;

        Excel::store($export, $path, 'local');

        return $path;
    }
}
