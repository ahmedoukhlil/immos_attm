<?php

namespace App\Livewire;

use App\Models\Immobilisation;
use App\Models\Inventaire;
use App\Models\InventaireScan;
use App\Models\Localisation;
use App\Models\Site;
use App\Models\User;
use App\Services\InventaireService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public $totalBiens = 0;

    public $totalLocalisations = 0;

    public $inventaireEnCours = null;

    public $biensAReformer = 0;

    public $dernieresActions = [];

    public $statistiquesInventaire = [];

    public $localisationsInventaire = [];

    public $repartitionStatuts = [];

    public $progressionParService = [];

    public $biensCetteAnnee = 0;

    public $nombreEmplacements = 0;

    public $emplacementsInventories = [];

    public function mount(): void
    {
        $this->loadStatistics();
    }

    public function refresh(): void
    {
        $this->loadStatistics();
        $this->dispatch('dashboard-updated');
    }

    private function loadStatistics(): void
    {
        try {
            $this->totalBiens = Immobilisation::count();
            $this->biensCetteAnnee = Immobilisation::whereYear('created_at', now()->year)->count();
            $this->totalLocalisations = Localisation::count();
            $this->nombreEmplacements = Site::count();
            $this->biensAReformer = Immobilisation::whereHas('etat', fn ($query) => $query->whereIn('code', ['MAUV', 'HS']))->count();

            /** @var User $user */
            $user = Auth::user();
            $inventairesAccessibles = Inventaire::query()->accessibleTo($user);

            // Un inventaire clôturé plus récent ne doit pas masquer l'inventaire actif.
            $this->inventaireEnCours = (clone $inventairesAccessibles)
                ->whereIn('statut', ['en_preparation', 'en_cours'])
                ->orderByDesc('annee')
                ->orderByDesc('created_at')
                ->first()
                ?? (clone $inventairesAccessibles)
                    ->orderByDesc('annee')
                    ->orderByDesc('created_at')
                    ->first();

            if ($this->inventaireEnCours) {
                $this->loadInventaireStats();
            } else {
                $this->resetInventaireStats();
            }

            $this->loadRecentActions($user);
        } catch (\Throwable $exception) {
            report($exception);
            $this->inventaireEnCours = null;
            $this->resetInventaireStats();
            $this->dernieresActions = [];
        }
    }

    private function loadInventaireStats(): void
    {
        $inventaire = $this->inventaireEnCours;
        $localisations = $inventaire->inventaireLocalisations()
            ->with(['localisation', 'agent'])
            ->orderBy('statut')
            ->orderByDesc('nombre_biens_attendus')
            ->get();
        $scans = $inventaire->inventaireScans()
            ->with('bien.categorie')
            ->get();

        $statistiques = app(InventaireService::class)->calculerStatistiques(
            $inventaire,
            $localisations,
            $scans,
        );

        $this->localisationsInventaire = $localisations->map(function ($item): array {
            $localisation = $item->localisation;
            $code = $localisation?->code;
            $nom = $localisation?->localisation ?? 'N/A';

            return [
                'id' => $item->id,
                'localisation' => $code ? "{$code} - {$nom}" : $nom,
                'biens_attendus' => (int) $item->nombre_biens_attendus,
                'biens_scannes' => (int) $item->nombre_biens_scannes,
                'progression' => (float) $item->progression,
                'statut' => $item->statut ?? 'en_attente',
                'agent' => $item->agent?->users ?? $item->agent?->email ?? 'Non assigné',
            ];
        })->all();

        $this->repartitionStatuts = [
            'present' => $statistiques['biens_presents'],
            'deplace' => $statistiques['biens_deplaces'],
            'absent' => $statistiques['biens_absents'],
            'deteriore' => $statistiques['biens_deteriores'],
        ];

        // Le référentiel ne comporte pas de service : le graphique suit les localisations.
        $this->progressionParService = collect($statistiques['par_localisation'])
            ->map(fn (array $ligne): array => [
                'service' => $ligne['code'],
                'progression' => $ligne['progression'],
            ])
            ->values()
            ->all();

        $this->statistiquesInventaire = array_merge($statistiques, [
            'progression' => $statistiques['progression_globale'],
            'total_scans' => $statistiques['total_biens_scannes'],
            'scans_presents' => $statistiques['biens_presents'],
            'scans_deplaces' => $statistiques['biens_deplaces'],
            'scans_absents' => $statistiques['biens_absents'],
            'scans_deteriores' => $statistiques['biens_deteriores'],
        ]);

        $this->emplacementsInventories = collect($statistiques['par_site'])
            ->map(fn (array $ligne): array => [
                'id' => $ligne['site_id'],
                'nom' => $ligne['designation'],
                'code' => $ligne['code'],
                'localisation' => $ligne['localisation'],
                'responsable' => $ligne['responsable'],
                'biens_scannes' => $ligne['biens_scannes'],
                'total_biens' => $ligne['biens_attendus'],
                'progression' => $ligne['taux_couverture'],
            ])
            ->sortByDesc('biens_scannes')
            ->values()
            ->all();
    }

    private function loadRecentActions(User $user): void
    {
        $actions = collect();

        $scansRecents = InventaireScan::query()
            ->whereHas('inventaire', fn ($query) => $query->accessibleTo($user))
            ->where('statut_scan', '!=', 'absent')
            ->where('date_scan', '>=', now()->subDays(7))
            ->with(['inventaire.biensAttendus', 'bien.designation', 'localisationReelle', 'agent'])
            ->orderByDesc('date_scan')
            ->limit(5)
            ->get();

        foreach ($scansRecents as $scan) {
            $agent = $scan->agent?->users ?? 'Système';
            $localisation = $scan->localisationReelle?->localisation ?? $scan->localisation_code ?? 'N/A';
            $actions->push([
                'type' => 'scan',
                'icon' => 'qrcode',
                'message' => "{$agent} a scanné {$scan->designation} dans {$localisation}",
                'time' => $scan->date_scan,
            ]);
        }

        $inventairesRecents = Inventaire::query()
            ->accessibleTo($user)
            ->with('creator')
            ->where(function ($query): void {
                $query->where('date_debut', '>=', now()->subDays(7))
                    ->orWhere('closed_at', '>=', now()->subDays(7));
            })
            ->orderByDesc('created_at')
            ->limit(2)
            ->get();

        foreach ($inventairesRecents as $inventaire) {
            $creator = $inventaire->creator?->users ?? 'Système';

            if ($inventaire->statut === 'en_cours' && ($inventaire->started_at || $inventaire->date_debut)) {
                $actions->push([
                    'type' => 'inventaire_started',
                    'icon' => 'play',
                    'message' => "{$creator} a démarré l'inventaire {$inventaire->annee}",
                    'time' => $inventaire->started_at ?? $inventaire->date_debut,
                ]);
            } elseif ($inventaire->statut === 'cloture' && ($inventaire->closed_at || $inventaire->date_fin)) {
                $actions->push([
                    'type' => 'inventaire_closed',
                    'icon' => 'check-circle',
                    'message' => "L'inventaire {$inventaire->annee} a été clôturé",
                    'time' => $inventaire->closed_at ?? $inventaire->date_fin,
                ]);
            }
        }

        $this->dernieresActions = $actions
            ->sortByDesc('time')
            ->take(10)
            ->values()
            ->map(function (array $action): array {
                $action['time_ago'] = Carbon::parse($action['time'])->diffForHumans();

                return $action;
            })
            ->all();
    }

    private function resetInventaireStats(): void
    {
        $this->statistiquesInventaire = [];
        $this->localisationsInventaire = [];
        $this->repartitionStatuts = [];
        $this->progressionParService = [];
        $this->emplacementsInventories = [];
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
