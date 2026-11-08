<?php

namespace App\Livewire\Localisations;

use App\Livewire\Traits\WithSafeTableControls;
use App\Models\Localisation;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

class ListeLocalisations extends Component
{
    use WithPagination, WithSafeTableControls;

    private const SORT_FIELDS = ['code', 'localisation'];

    /**
     * Propriétés publiques pour les filtres et la recherche
     */
    public $search = '';

    public $sortField = 'code';

    public $sortDirection = 'asc';

    public $perPage = 20;

    public $selectedLocalisations = [];

    /**
     * Initialisation du composant
     */
    public function mount(): void
    {
        // Réinitialiser la pagination si nécessaire
        $this->resetPage();
    }

    /**
     * Propriété calculée : Vérifie si toutes les localisations sont sélectionnées
     * Optimisé : ne recalcule que si nécessaire
     */
    public function getAllSelectedProperty()
    {
        // Si aucune sélection, retourner false immédiatement
        if (empty($this->selectedLocalisations)) {
            return false;
        }

        // Compter seulement les IDs correspondant aux filtres (sans les récupérer tous)
        $totalCount = $this->getLocalisationsQuery()->count();

        if ($totalCount === 0) {
            return false;
        }

        // Si le nombre de sélections ne correspond pas, retourner false
        if (count($this->selectedLocalisations) !== $totalCount) {
            return false;
        }

        // Vérifier que tous les IDs sélectionnés correspondent aux filtres
        $allLocalisationsIds = $this->getLocalisationsQuery()->pluck('id')->toArray();

        return empty(array_diff($allLocalisationsIds, $this->selectedLocalisations));
    }

    /**
     * Change le tri de la colonne
     */
    public function sortBy($field): void
    {
        $this->changeSort((string) $field, self::SORT_FIELDS);
    }

    /**
     * Réinitialise tous les filtres
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->selectedLocalisations = [];
        $this->resetPage();
    }

    /**
     * Sélectionne ou désélectionne toutes les localisations (toutes pages)
     */
    public function toggleSelectAll(): void
    {
        // Récupérer tous les IDs des localisations correspondant aux filtres (sans pagination)
        $allLocalisationsIds = $this->getLocalisationsQuery()->pluck('id')->toArray();

        // Vérifier si toutes les localisations sont déjà sélectionnées
        $allSelected = ! empty($allLocalisationsIds) &&
                       count($this->selectedLocalisations) === count($allLocalisationsIds) &&
                       empty(array_diff($allLocalisationsIds, $this->selectedLocalisations));

        if ($allSelected) {
            // Tout désélectionner
            $this->selectedLocalisations = [];
        } else {
            // Tout sélectionner (fusionner avec les localisations déjà sélectionnées)
            $this->selectedLocalisations = array_unique(array_merge($this->selectedLocalisations, $allLocalisationsIds));
        }
    }

    /**
     * Supprime une localisation
     */
    public function deleteLocalisation($localisationId): void
    {
        // Vérifier que l'utilisateur est admin
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Vous n\'avez pas les permissions nécessaires pour supprimer une localisation.');

            return;
        }

        $localisation = Localisation::find($localisationId);

        if (! $localisation) {
            session()->flash('error', 'Localisation introuvable.');

            return;
        }

        // Vérifier qu'aucun site n'est associé à cette localisation
        $nombreSites = $localisation->sites()->count();

        if ($nombreSites > 0) {
            session()->flash('error', "Impossible de supprimer cette localisation : {$nombreSites} site(s) y sont associé(s).");

            return;
        }

        try {
            $localisation->delete();
            // Invalider le cache des statistiques
            Cache::forget('localisations_total_count');
            Cache::forget('sites_total_count');
            session()->flash('success', 'La localisation a été supprimée avec succès.');

            // Retirer de la sélection si présent
            $this->selectedLocalisations = array_diff($this->selectedLocalisations, [$localisationId]);
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'Erreur lors de la suppression.');
        }
    }

    /**
     * Exporte les localisations sélectionnées
     */
    public function exportSelected()
    {
        if (empty($this->selectedLocalisations)) {
            session()->flash('warning', 'Veuillez sélectionner au moins une localisation à exporter.');

            return;
        }

        // Rediriger vers la route d'export avec les IDs sélectionnés en paramètre de requête (query string)
        $ids = implode(',', $this->selectedLocalisations);

        return redirect()->to(route('localisations.export-excel').'?ids='.urlencode($ids));
    }

    /**
     * Construit la requête de base pour les localisations
     */
    protected function getLocalisationsQuery()
    {
        $query = Localisation::withCount('sites');

        // Recherche globale
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('localisation', 'like', '%'.$this->search.'%');
            });
        }

        // Tri
        $query->orderBy(
            $this->resolveSortField(self::SORT_FIELDS, 'code'),
            $this->resolveSortDirection(),
        );

        return $query;
    }

    /**
     * Rendu du composant
     */
    public function render()
    {
        $localisations = $this->getLocalisationsQuery()->paginate($this->resolvePageSize());

        // Statistiques pour le header (avec cache)
        $totalLocalisations = Cache::remember('localisations_total_count', 300, function () {
            return Localisation::count();
        });
        $totalSites = Cache::remember('sites_total_count', 300, function () {
            return Site::count();
        });

        return view('livewire.localisations.liste-localisations', [
            'localisations' => $localisations,
            'totalLocalisations' => $totalLocalisations,
            'totalSites' => $totalSites,
        ]);
    }
}
