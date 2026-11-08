<?php

namespace App\Livewire\Designations;

use App\Livewire\Traits\WithSafeTableControls;
use App\Models\Categorie;
use App\Models\Designation;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]

class ListeDesignations extends Component
{
    use WithPagination, WithSafeTableControls;

    private const SORT_FIELDS = ['designation', 'categorie'];

    /**
     * Propriétés publiques pour les filtres et la recherche
     */
    public $search = '';

    public $filterCategorie = '';

    public $sortField = 'designation';

    public $sortDirection = 'asc';

    public $perPage = 20;

    public $selectedDesignations = [];

    /**
     * Initialisation du composant
     */
    public function mount(): void
    {
        $this->resetPage();
    }

    /**
     * Propriété calculée : Retourne toutes les catégories pour le filtre
     */
    public function getCategoriesProperty()
    {
        return Categorie::orderBy('categorie')->get();
    }

    /**
     * Construit la requête de base pour les désignations
     */
    protected function getDesignationsQuery()
    {
        $query = Designation::with(['categorie'])
            ->withCount('immobilisations');

        // Recherche globale
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('designation', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%')
                    ->orWhereHas('categorie', function ($q) {
                        $q->where('categorie', 'like', '%'.$this->search.'%');
                    });
            });
        }

        // Filtre par catégorie
        if (! empty($this->filterCategorie)) {
            $query->where('categorie_id', $this->filterCategorie);
        }

        // Tri
        $sortField = $this->resolveSortField(self::SORT_FIELDS, 'designation');
        $sortDirection = $this->resolveSortDirection();

        if ($sortField === 'categorie') {
            $query->join('categories', 'designations.categorie_id', '=', 'categories.id')
                ->orderBy('categories.categorie', $sortDirection)
                ->select('designations.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        return $query;
    }

    /**
     * Change le champ de tri
     */
    public function sortBy($field)
    {
        $this->changeSort((string) $field, self::SORT_FIELDS);
    }

    /**
     * Réinitialise les filtres
     */
    public function resetFilters()
    {
        $this->search = '';
        $this->filterCategorie = '';
        $this->resetPage();
    }

    /**
     * Supprime une désignation
     */
    public function deleteDesignation($designationId)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $designation = Designation::find($designationId);

        if (! $designation) {
            session()->flash('error', 'Désignation introuvable.');

            return;
        }

        // Vérifier si la désignation est utilisée
        if ($designation->immobilisations()->count() > 0) {
            session()->flash('error', 'Cette désignation ne peut pas être supprimée car elle est utilisée par des immobilisations.');

            return;
        }

        $designation->delete();
        session()->flash('success', 'Désignation supprimée avec succès.');
    }

    /**
     * Rendu du composant
     */
    public function render()
    {
        $designations = $this->getDesignationsQuery()->paginate($this->resolvePageSize());
        $totalDesignations = Designation::count();

        return view('livewire.designations.liste-designations', [
            'designations' => $designations,
            'totalDesignations' => $totalDesignations,
        ]);
    }
}
