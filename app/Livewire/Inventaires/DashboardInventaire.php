<?php

namespace App\Livewire\Inventaires;

use App\Exceptions\InventaireWorkflowException;
use App\Models\Inventaire;
use App\Models\InventaireLocalisation;
use App\Models\InventaireScan;
use App\Models\InventaireSite;
use App\Models\User;
use App\Services\InventaireService;
use App\Services\SiteScanService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DashboardInventaire extends Component
{
    use WithPagination;

    /**
     * Instance de l'inventaire
     */
    public Inventaire $inventaire;

    /**
     * Propriétés pour les filtres
     */
    public $filterStatutLoc = 'all'; // all, en_attente, en_cours, termine

    public $filterAgent = 'all';

    public $searchLoc = '';

    public $sortField = 'code';

    public $sortDirection = 'asc';

    public $activeAgentDropdown = null;

    public $activeSitesPanel = null;

    /**
     * Filtres du détail des immobilisations scannées
     */
    public string $scanRecherche = '';

    public string $scanStatut = 'physiques'; // physiques, present, deplace, absent, tous

    public string $scanEtat = '';

    /**
     * Initialisation du composant
     */
    public function mount(Inventaire $inventaire): void
    {
        // Vérifier autorisation (admin ou agent assigné)
        $user = Auth::user();

        $hasAccess = $inventaire->inventaireLocalisations()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->idUser)
                    ->orWhereHas('agents', fn ($sub) => $sub->where('users.idUser', $user->idUser));
            })->exists();

        if (! $user->isAdmin() && ! $hasAccess) {
            abort(403, 'Vous n\'avez pas accès à cet inventaire.');
        }

        $this->inventaire = $inventaire->load([
            'inventaireLocalisations.localisation',
            'inventaireLocalisations.agent',
            'inventaireLocalisations.agents',
            'inventaireScans',
        ]);
    }

    /**
     * Propriété calculée : Retourne les statistiques complètes de l'inventaire
     * Note: Le refresh est géré par refreshStatistiques() pour éviter les appels multiples
     */
    public function getStatistiquesProperty(): array
    {
        $inventaireLocalisations = $this->inventaire->inventaireLocalisations;
        $scans = $this->inventaire->inventaireScans;

        // Calculer le total de biens attendus
        $totalBiensAttendus = $inventaireLocalisations->sum('nombre_biens_attendus');

        // Les anciens inventaires sans instantané utilisent un calcul de secours en
        // mémoire. Une simple consultation du dashboard ne doit jamais écrire en base.
        if ($totalBiensAttendus == 0 && ! $this->inventaire->hasFrozenReference()) {
            $this->recalculerBiensAttendusEnMemoire();
            $inventaireLocalisations = $this->inventaire->inventaireLocalisations;
        }

        $statistiques = app(InventaireService::class)->calculerStatistiques(
            $this->inventaire,
            $inventaireLocalisations,
            $scans,
        );
        $scansPhysiques = $scans->where('statut_scan', '!=', 'absent');

        // Scans effectués aujourd'hui
        $scansAujourdhui = $scansPhysiques
            ->filter(fn ($scan) => $scan->date_scan && $scan->date_scan->isToday())
            ->count();

        // Vitesse moyenne (scans par jour)
        $vitesseMoyenne = 0;
        $debutEffectif = $this->inventaire->started_at ?? $this->inventaire->date_debut;
        if ($debutEffectif && $statistiques['total_biens_scannes'] > 0) {
            $joursEcoules = max(1, now()->diffInDays($debutEffectif));
            $vitesseMoyenne = round($statistiques['total_biens_scannes'] / $joursEcoules, 1);
        }

        return array_merge($statistiques, [
            // Le tableau de bord affiche l'avancement des rapprochements de biens.
            'progression_globale' => $statistiques['taux_rapprochement'],
            'progression_localisations' => $statistiques['progression_globale'],
            'biens_non_verifies' => $statistiques['biens_non_scannes'],
            'scans_aujourdhui' => $scansAujourdhui,
            'vitesse_moyenne' => $vitesseMoyenne,
        ]);
    }

    public function updatedScanRecherche(): void
    {
        $this->resetPage('scansPage');
    }

    public function updatedScanStatut(): void
    {
        $this->resetPage('scansPage');
    }

    public function updatedScanEtat(): void
    {
        $this->resetPage('scansPage');
    }

    /**
     * Immobilisations scannées, avec le site attendu figé au démarrage.
     * Par défaut, seuls les biens vus physiquement (présents et déplacés).
     */
    public function getScansDetailProperty(): LengthAwarePaginator
    {
        $recherche = trim($this->scanRecherche);
        $statut = in_array($this->scanStatut, ['physiques', 'present', 'deplace', 'absent', 'tous'], true)
            ? $this->scanStatut
            : 'physiques';
        $etat = in_array($this->scanEtat, ['neuf', 'bon_etat', 'defectueux'], true) ? $this->scanEtat : '';

        return InventaireScan::query()
            ->select('inventaire_scans.*')
            ->addSelect([
                'attendus.code_propose as code_fige',
                'attendus.designation as designation_figee',
                'attendus.site_nom as site_attendu',
            ])
            ->leftJoin('inventaire_immobilisations_attendues as attendus', function ($join): void {
                $join->on('attendus.inventaire_id', '=', 'inventaire_scans.inventaire_id')
                    ->on('attendus.immobilisation_id', '=', 'inventaire_scans.immobilisation_id');
            })
            ->leftJoin('immobilisations as immo', 'immo.id', '=', 'inventaire_scans.immobilisation_id')
            ->leftJoin('designations as designation', 'designation.id', '=', 'immo.designation_id')
            ->where('inventaire_scans.inventaire_id', $this->inventaire->id)
            ->when($statut === 'physiques', fn ($query) => $query->where('inventaire_scans.statut_scan', '!=', 'absent'))
            ->when(! in_array($statut, ['physiques', 'tous'], true), fn ($query) => $query->where('inventaire_scans.statut_scan', $statut))
            ->when($etat !== '', fn ($query) => $query
                ->where('inventaire_scans.etat_constate', $etat)
                ->where('inventaire_scans.statut_scan', '!=', 'absent'))
            ->when($recherche !== '', function ($query) use ($recherche): void {
                $identifiant = preg_match('/^(?:GS)?(\d+)$/i', $recherche, $m) ? (int) $m[1] : null;
                $motif = '%'.str_replace(['%', '_'], ['\%', '\_'], $recherche).'%';

                $query->where(function ($sousRequete) use ($identifiant, $motif): void {
                    $sousRequete->where('attendus.code_propose', 'like', $motif)
                        ->orWhere('attendus.designation', 'like', $motif)
                        ->orWhere('immo.code_propose', 'like', $motif)
                        ->orWhere('designation.designation', 'like', $motif);

                    if ($identifiant !== null) {
                        $sousRequete->orWhere('inventaire_scans.immobilisation_id', $identifiant);
                    }
                });
            })
            ->with(['bien.designation', 'bien.site', 'siteReel', 'agent'])
            ->orderByDesc('inventaire_scans.date_scan')
            ->orderByDesc('inventaire_scans.id')
            ->paginate(20, pageName: 'scansPage');
    }

    /**
     * Avancement des sites : c'est l'unité de travail des agents.
     *
     * @return array{total: int, termines: int, en_cours: int, en_attente: int}
     */
    public function getStatistiquesSitesProperty(): array
    {
        $parStatut = InventaireSite::query()
            ->whereIn('inventaire_localisation_id', $this->inventaire->inventaireLocalisations->pluck('id'))
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return [
            'total' => (int) $parStatut->sum(),
            'termines' => (int) $parStatut->get('termine', 0),
            'en_cours' => (int) $parStatut->get('en_cours', 0),
            'en_attente' => (int) $parStatut->get('en_attente', 0),
        ];
    }

    /**
     * Calcule les compteurs legacy depuis les sites sans modifier la base.
     */
    private function recalculerBiensAttendusEnMemoire(): void
    {
        foreach ($this->inventaire->inventaireLocalisations as $invLoc) {
            if ($invLoc->localisation && $invLoc->nombre_biens_attendus == 0) {
                $nombreBiensAttendus = $invLoc->localisation->immobilisations()->count();

                $invLoc->setAttribute('nombre_biens_attendus', $nombreBiensAttendus);
            }
        }
    }

    /**
     * Propriété calculée : Retourne les localisations filtrées et triées
     */
    public function getInventaireLocalisationsProperty()
    {
        $query = $this->inventaire->inventaireLocalisations()
            ->with(['localisation', 'agent', 'agents', 'inventaireSites.site']);

        // Filtre par statut
        if ($this->filterStatutLoc !== 'all') {
            $query->where('statut', $this->filterStatutLoc);
        }

        // Filtre par agent (vérifie user_id legacy ET la table pivot)
        if ($this->filterAgent !== 'all') {
            $agentId = $this->filterAgent;
            $query->where(function ($q) use ($agentId) {
                $q->where('user_id', $agentId)
                    ->orWhereHas('agents', fn ($sub) => $sub->where('users.idUser', $agentId));
            });
        }

        // Recherche
        if (! empty($this->searchLoc)) {
            $query->whereHas('localisation', function ($q) {
                $q->where('code', 'like', '%'.$this->searchLoc.'%')
                    ->orWhere('localisation', 'like', '%'.$this->searchLoc.'%');
            });
        }

        $results = $query->get();

        $isDesc = $this->sortDirection === 'desc';

        if ($this->sortField === 'code') {
            $results = $results->sortBy(function ($invLoc) {
                return $invLoc->localisation->code ?? $invLoc->localisation->localisation ?? '';
            }, SORT_REGULAR, $isDesc);
        } elseif ($this->sortField === 'progression') {
            $results = $results->sortBy(function ($invLoc) {
                return $invLoc->nombre_biens_attendus > 0
                    ? ($invLoc->nombre_biens_scannes / $invLoc->nombre_biens_attendus)
                    : 0;
            }, SORT_NUMERIC, $isDesc);
        }

        return $results->values();
    }

    /**
     * Propriété calculée : Données pour le graphique progression temporelle
     */
    public function getScansGraphDataProperty(): array
    {
        return $this->inventaire->inventaireScans()
            ->where('statut_scan', '!=', 'absent')
            ->selectRaw('DATE(date_scan) as scan_date, COUNT(DISTINCT immobilisation_id) as total')
            ->groupByRaw('DATE(date_scan)')
            ->orderBy('scan_date')
            ->get()
            ->map(fn ($ligne): array => [
                'date' => $ligne->scan_date,
                'total' => (int) $ligne->total,
            ])
            ->all();
    }

    /**
     * Propriété calculée : Retourne la liste des agents assignés à cet inventaire (pour les filtres)
     */
    public function getAgentsProperty()
    {
        $invLocs = $this->inventaire->inventaireLocalisations()
            ->with(['agent', 'agents'])
            ->get();

        $allAgents = collect();

        foreach ($invLocs as $invLoc) {
            if ($invLoc->agent) {
                $allAgents->push($invLoc->agent);
            }
            foreach ($invLoc->agents as $ag) {
                $allAgents->push($ag);
            }
        }

        return $allAgents->unique('idUser')->values();
    }

    /**
     * Propriété calculée : Retourne tous les utilisateurs pouvant être assignés (pour le dropdown d'assignation)
     */
    public function getAgentsDisponiblesProperty()
    {
        return User::whereIn('role', ['agent', 'admin'])
            ->orderBy('users')
            ->get();
    }

    /**
     * Propriété calculée : Retourne les 5 derniers scans
     */
    public function getDerniersScansProperty()
    {
        return $this->inventaire->inventaireScans()
            ->with(['bien.designation', 'localisationReelle', 'agent'])
            ->orderBy('date_scan', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Propriété calculée : Retourne les alertes détectées
     */
    public function getAlertesProperty(): array
    {
        $alertes = [
            'localisations_non_demarrees' => [],
            'localisations_bloquees' => [],
            'biens_absents' => [],
            'biens_defectueux' => [],
            'localisations_non_assignees' => [],
        ];

        // Localisations non démarrées
        $nonDemarrees = $this->inventaire->inventaireLocalisations()
            ->where('statut', 'en_attente')
            ->with('localisation')
            ->get();

        foreach ($nonDemarrees as $invLoc) {
            $alertes['localisations_non_demarrees'][] = [
                'id' => $invLoc->id,
                'code' => $invLoc->localisation->code ?? $invLoc->localisation->localisation ?? 'N/A',
                'designation' => $invLoc->localisation->localisation ?? 'N/A',
            ];
        }

        // Localisations bloquées (pas de scan depuis 24h)
        $ilY24h = now()->subDay();
        $bloquees = $this->inventaire->inventaireLocalisations()
            ->where('statut', 'en_cours')
            ->where(function ($q) use ($ilY24h) {
                $q->whereNull('date_debut_scan')
                    ->orWhere('date_debut_scan', '<', $ilY24h);
            })
            ->whereDoesntHave('inventaireScans', fn ($query) => $query
                ->where('date_scan', '>=', $ilY24h))
            ->with('localisation')
            ->get();

        foreach ($bloquees as $invLoc) {
            $dernierScan = $this->inventaire->inventaireScans()
                ->where('inventaire_localisation_id', $invLoc->id)
                ->orderBy('date_scan', 'desc')
                ->first();

            $joursSansScan = $dernierScan
                ? $dernierScan->date_scan->diffInDays(now())
                : ($invLoc->date_debut_scan ? $invLoc->date_debut_scan->diffInDays(now()) : 0);

            $alertes['localisations_bloquees'][] = [
                'id' => $invLoc->id,
                'code' => $invLoc->localisation->code ?? $invLoc->localisation->localisation ?? 'N/A',
                'designation' => $invLoc->localisation->localisation ?? 'N/A',
                'jours' => $joursSansScan,
            ];
        }

        // Biens absents (liste les 10 premiers)
        $biensAbsents = $this->inventaire->inventaireScans()
            ->where('statut_scan', 'absent')
            ->with('bien.designation')
            ->limit(10)
            ->get();

        foreach ($biensAbsents as $scan) {
            $alertes['biens_absents'][] = [
                'immobilisation_id' => $scan->immobilisation_id,
                'code' => $scan->code_inventaire,
                'designation' => $scan->designation,
            ];
        }

        // Biens défectueux (etat_constate = defectueux, signalés via PWA)
        $biensDefectueux = $this->inventaire->inventaireScans()
            ->where('etat_constate', 'defectueux')
            ->with(['bien.designation', 'localisationReelle'])
            ->get();

        foreach ($biensDefectueux as $scan) {
            $alertes['biens_defectueux'][] = [
                'immobilisation_id' => $scan->immobilisation_id,
                'code' => $scan->code_inventaire,
                'designation' => $scan->designation,
                'localisation' => $scan->localisationReelle?->code ?? $scan->localisationReelle?->localisation ?? 'N/A',
                'photo_url' => $scan->photo_url,
                'commentaire' => $scan->commentaire,
            ];
        }

        // Localisations non assignées (ni via user_id legacy, ni via table pivot)
        $nonAssignees = $this->inventaire->inventaireLocalisations()
            ->whereNull('user_id')
            ->whereDoesntHave('agents')
            ->with('localisation')
            ->get();

        foreach ($nonAssignees as $invLoc) {
            $alertes['localisations_non_assignees'][] = [
                'id' => $invLoc->id,
                'code' => $invLoc->localisation->code ?? $invLoc->localisation->localisation ?? 'N/A',
                'designation' => $invLoc->localisation->localisation ?? 'N/A',
            ];
        }

        return $alertes;
    }

    /**
     * Propriété calculée : Retourne le nombre total d'alertes
     * Note: Utilise les données déjà chargées pour éviter un double calcul
     */
    public function getTotalAlertesProperty(): int
    {
        // Compter rapidement sans recalculer toutes les alertes en détail
        $total = 0;

        $total += $this->inventaire->inventaireLocalisations()
            ->where('statut', 'en_attente')->count();

        $ilY24h = now()->subDay();
        $total += $this->inventaire->inventaireLocalisations()
            ->where('statut', 'en_cours')
            ->where(function ($q) use ($ilY24h) {
                $q->whereNull('date_debut_scan')
                    ->orWhere('date_debut_scan', '<', $ilY24h);
            })
            ->whereDoesntHave('inventaireScans', fn ($query) => $query
                ->where('date_scan', '>=', $ilY24h))
            ->count();

        $total += $this->inventaire->inventaireScans()
            ->where('statut_scan', 'absent')
            ->count();

        $total += $this->inventaire->inventaireScans()
            ->where('etat_constate', 'defectueux')->count();

        $total += $this->inventaire->inventaireLocalisations()
            ->whereNull('user_id')
            ->whereDoesntHave('agents')
            ->count();

        return $total;
    }

    /**
     * Recharge les statistiques (appelé par polling)
     */
    public function refresh(): void
    {
        $this->inventaire->refresh();
        // Les propriétés calculées seront recalculées automatiquement
    }

    /**
     * Rafraîchit uniquement les statistiques de manière légère
     * Émet un événement pour déclencher l'animation
     */
    public function refreshStatistiques(): void
    {
        // Recharger l'inventaire avec toutes ses relations
        $this->inventaire->refresh();
        $this->inventaire->load([
            'inventaireLocalisations.localisation',
            'inventaireLocalisations.agent',
            'inventaireScans',
        ]);

        // Émettre un événement pour l'indicateur visuel
        $this->dispatch('statistiques-updated');
    }

    /**
     * Passe l'inventaire en cours
     */
    public function passerEnCours(): void
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Seuls les administrateurs peuvent démarrer un inventaire.');

            return;
        }

        if ($this->inventaire->statut !== 'en_preparation') {
            session()->flash('error', 'Seuls les inventaires en préparation peuvent être démarrés.');

            return;
        }

        try {
            app(InventaireService::class)->demarrerInventaire($this->inventaire, Auth::user());
            $this->inventaire->refresh();
            session()->flash('success', "L'inventaire {$this->inventaire->annee} a été démarré avec succès.");
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors du démarrage.'));
        }
    }

    /**
     * Termine l'inventaire
     */
    public function terminerInventaire(): void
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Seuls les administrateurs peuvent terminer un inventaire.');

            return;
        }

        // Vérifier que toutes les localisations sont terminées
        $localisationsNonTerminees = $this->inventaire->inventaireLocalisations()
            ->where('statut', '!=', 'termine')
            ->count();

        if ($localisationsNonTerminees > 0) {
            session()->flash('error', "Toutes les localisations doivent être terminées avant de terminer l'inventaire. {$localisationsNonTerminees} localisation(s) restante(s).");

            return;
        }

        try {
            app(InventaireService::class)->terminerInventaire($this->inventaire, Auth::user());
            $this->inventaire->refresh();
            session()->flash('success', "L'inventaire {$this->inventaire->annee} a été terminé avec succès.");
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la finalisation.'));
        }
    }

    /**
     * Clôture définitivement l'inventaire
     */
    public function cloturerInventaire()
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Seuls les administrateurs peuvent clôturer un inventaire.');

            return;
        }

        if ($this->inventaire->statut !== 'termine') {
            session()->flash('error', 'Seuls les inventaires terminés peuvent être clôturés.');

            return;
        }

        try {
            app(InventaireService::class)->cloturerInventaire($this->inventaire, Auth::user());
            session()->flash('success', "L'inventaire {$this->inventaire->annee} a été clôturé définitivement.");

            return redirect()->route('inventaires.rapport', $this->inventaire);
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la clôture.'));
        }
    }

    /**
     * Réassigne une localisation à un agent (legacy, remplacé par toggleAgentLocalisation)
     */
    public function reassignerLocalisation($invLocId, $userId): void
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Seuls les administrateurs peuvent réassigner des localisations.');

            return;
        }

        $invLoc = InventaireLocalisation::find($invLocId);

        if (! $invLoc || $invLoc->inventaire_id !== $this->inventaire->id) {
            session()->flash('error', 'Localisation introuvable.');

            return;
        }

        try {
            $newAgent = $userId ? User::findOrFail((int) $userId) : null;
            app(InventaireService::class)->reassignerLocalisation(
                $invLoc,
                $newAgent,
                Auth::user(),
            );
            session()->flash('success', 'Localisation réassignée avec succès.');
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la réassignation.'));
        }
    }

    /**
     * Ajoute/retire un agent d'une localisation (toggle multi-agent)
     */
    public function toggleAgentLocalisation($invLocId, $userId): void
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Seuls les administrateurs peuvent modifier les assignations.');

            return;
        }

        $invLoc = InventaireLocalisation::with('agents')->find($invLocId);

        if (! $invLoc || $invLoc->inventaire_id !== $this->inventaire->id) {
            session()->flash('error', 'Localisation introuvable.');

            return;
        }

        try {
            $agent = User::findOrFail((int) $userId);
            app(InventaireService::class)->basculerAgentLocalisation(
                $invLoc,
                $agent,
                Auth::user(),
            );

            $this->inventaire->load([
                'inventaireLocalisations.localisation',
                'inventaireLocalisations.agent',
                'inventaireLocalisations.agents',
            ]);
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Une erreur est survenue.'));
        }
    }

    /**
     * Change le tri de la colonne
     */
    public function sortBy($field): void
    {
        if (! in_array($field, ['code', 'progression'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleAgentDropdown($invLocId): void
    {
        $this->activeAgentDropdown = $this->activeAgentDropdown === $invLocId ? null : $invLocId;
    }

    public function closeAgentDropdown(): void
    {
        $this->activeAgentDropdown = null;
    }

    public function toggleSitesPanel($invLocId): void
    {
        $this->activeSitesPanel = $this->activeSitesPanel === $invLocId ? null : $invLocId;
    }

    /**
     * Rouvre un site terminé par erreur pour qu'il puisse être rescanné
     */
    public function rouvrirSite($inventaireSiteId): void
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Seuls les administrateurs peuvent rouvrir un site.');

            return;
        }

        $inventaireSite = InventaireSite::with(['inventaireLocalisation', 'site'])->find($inventaireSiteId);

        if (! $inventaireSite || $inventaireSite->inventaireLocalisation?->inventaire_id !== $this->inventaire->id) {
            session()->flash('error', 'Site introuvable.');

            return;
        }

        try {
            app(SiteScanService::class)->rouvrir($inventaireSite, Auth::user());
            $this->inventaire->refresh();
            session()->flash('success', "Le site {$inventaireSite->site?->site} a été rouvert : ses biens peuvent être scannés à nouveau.");
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la réouverture du site.'));
        }
    }

    /**
     * Rendu du composant
     */
    public function render()
    {
        return view('livewire.inventaires.dashboard-inventaire');
    }
}
