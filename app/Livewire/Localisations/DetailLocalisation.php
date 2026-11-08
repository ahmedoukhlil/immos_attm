<?php

namespace App\Livewire\Localisations;

use App\Models\Categorie;
use App\Models\Immobilisation;
use App\Models\InventaireLocalisation;
use App\Models\InventaireScan;
use App\Models\Localisation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class DetailLocalisation extends Component
{
    use WithPagination;

    /**
     * Instance de la localisation
     */
    public Localisation $localisation;

    /**
     * Toggle pour afficher/masquer la liste des sites
     */
    public $afficherEmplacements = true;

    /**
     * Toggle pour afficher/masquer la liste des biens
     */
    public $afficherBiens = false;

    /**
     * Recherche dans les sites
     */
    public $searchEmplacement = '';

    /**
     * Recherche dans les biens
     */
    public $searchBien = '';

    /**
     * Filtre par catégorie
     */
    public $filterNature = '';

    /**
     * Initialisation du composant
     */
    public function mount(Localisation $localisation): void
    {
        $this->localisation = $localisation;
    }

    /**
     * Sites de cette localisation, filtrés
     */
    public function getSitesProperty()
    {
        return $this->localisation->sites()
            ->withCount('immobilisations')
            ->when($this->searchEmplacement !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('site', 'like', '%'.$this->searchEmplacement.'%')
                        ->orWhere('code', 'like', '%'.$this->searchEmplacement.'%')
                        ->orWhere('responsable', 'like', '%'.$this->searchEmplacement.'%');
                });
            })
            ->orderBy('site')
            ->paginate(10, ['*'], 'sitesPage');
    }

    public function getCategoriesProperty()
    {
        return Categorie::orderBy('categorie')->get();
    }

    /**
     * Requête des immobilisations rattachées à cette localisation
     */
    private function immobilisationsQuery()
    {
        return Immobilisation::whereHas('site', fn ($q) => $q->where('localisation_id', $this->localisation->id));
    }

    /**
     * Statistiques de la localisation
     */
    public function getStatistiquesProperty(): array
    {
        $totalImmobilisations = $this->immobilisationsQuery()->count();

        $repartition = fn (string $relation, string $colonne): array => $this->immobilisationsQuery()
            ->join($relation, "{$relation}.id", '=', 'immobilisations.'.($relation === 'categories' ? 'categorie_id' : 'etat_id'))
            ->selectRaw("{$relation}.{$colonne} as libelle, COUNT(*) as total")
            ->groupBy("{$relation}.{$colonne}")
            ->pluck('total', 'libelle')
            ->map(fn ($total) => (int) $total)
            ->toArray();

        return [
            'total_sites' => $this->localisation->sites()->count(),
            'total_immobilisations' => $totalImmobilisations,
            'total_biens' => $totalImmobilisations, // Alias pour la vue
            // La vue affiche la répartition « par_nature » : ce sont désormais les catégories.
            'par_nature' => $repartition('categories', 'categorie'),
            'par_categorie' => $repartition('categories', 'categorie'),
            'par_etat' => $repartition('etats', 'etat'),
        ];
    }

    /**
     * Toggle l'affichage de la liste des sites
     */
    public function toggleAfficherEmplacements(): void
    {
        $this->afficherEmplacements = ! $this->afficherEmplacements;
        $this->resetPage('sitesPage');
    }

    /**
     * Toggle l'affichage de la liste des biens
     */
    public function toggleAfficherBiens(): void
    {
        $this->afficherBiens = ! $this->afficherBiens;
    }

    /**
     * Immobilisations de cette localisation, filtrées
     */
    public function getBiensProperty()
    {
        return $this->immobilisationsQuery()
            ->with(['designation', 'categorie', 'etat', 'site'])
            ->when($this->searchBien !== '', fn ($query) => $query->whereHas(
                'designation',
                fn ($q) => $q->where('designation', 'like', '%'.$this->searchBien.'%'),
            ))
            ->when($this->filterNature !== '', fn ($query) => $query->where('categorie_id', $this->filterNature))
            ->orderBy('id')
            ->paginate(20, ['*'], 'biensPage');
    }

    /**
     * Derniers inventaires de cette localisation
     */
    public function getDerniersInventairesProperty()
    {
        return InventaireLocalisation::where('localisation_id', $this->localisation->id)
            ->with(['inventaire', 'agent'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Tous les inventaires de cette localisation
     */
    public function getTousInventairesProperty()
    {
        return InventaireLocalisation::where('localisation_id', $this->localisation->id)
            ->with(['inventaire', 'agent'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mouvements récents (entrées et sorties) constatés lors des inventaires
     */
    public function getMouvementsRecentsProperty(): array
    {
        // Biens trouvés dans cette localisation
        $entres = InventaireScan::where('localisation_reelle_id', $this->localisation->id)
            ->with(['bien.designation', 'inventaire', 'localisationReelle'])
            ->orderBy('date_scan', 'desc')
            ->limit(10)
            ->get();

        // Biens attendus ici mais déplacés, absents ou trouvés ailleurs
        $sortis = InventaireScan::whereHas('bien.site', fn ($q) => $q->where('localisation_id', $this->localisation->id))
            ->where(function ($q): void {
                $q->whereIn('statut_scan', ['deplace', 'absent'])
                    ->orWhere(function ($subQ): void {
                        $subQ->whereNotNull('localisation_reelle_id')
                            ->where('localisation_reelle_id', '!=', $this->localisation->id);
                    });
            })
            ->with(['bien.designation', 'inventaire', 'localisationReelle'])
            ->orderBy('date_scan', 'desc')
            ->limit(10)
            ->get();

        return [
            'entres' => $entres,
            'sortis' => $sortis,
        ];
    }

    /**
     * Supprime la localisation si elle ne contient aucun site
     */
    public function supprimer()
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Vous n\'avez pas les permissions nécessaires pour supprimer une localisation.');

            return;
        }

        $nombreSites = $this->localisation->sites()->count();

        if ($nombreSites > 0) {
            session()->flash('error', "Impossible de supprimer cette localisation : {$nombreSites} site(s) y sont associé(s).");

            return;
        }

        try {
            $this->localisation->delete();
            session()->flash('success', 'La localisation a été supprimée avec succès.');

            return redirect()->route('localisations.index');
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'Erreur lors de la suppression.');
        }
    }

    public function render()
    {
        return view('livewire.localisations.detail-localisation');
    }
}
