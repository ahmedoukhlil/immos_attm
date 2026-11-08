<?php

namespace App\Livewire\Categories;

use App\Livewire\Traits\WithSafeTableControls;
use App\Models\Categorie;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]

class ListeCategories extends Component
{
    use WithPagination, WithSafeTableControls;

    private const SORT_FIELDS = ['code', 'categorie', 'imputation_comptable'];

    public $search = '';

    public $sortField = 'categorie';

    public $sortDirection = 'asc';

    public $perPage = 20;

    public function mount(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy($field): void
    {
        $this->changeSort((string) $field, self::SORT_FIELDS);
    }

    public function deleteCategorie($categorieId): void
    {
        abort_unless(auth()->user()?->canManageOperations(), 403);

        $categorie = Categorie::withCount('immobilisations')->find($categorieId);

        if (! $categorie) {
            session()->flash('error', 'Catégorie introuvable.');

            return;
        }

        if ($categorie->immobilisations_count > 0) {
            session()->flash('error', "Impossible de supprimer : {$categorie->immobilisations_count} immobilisation(s) utilisent cette catégorie.");

            return;
        }

        if ($categorie->designations()->count() > 0) {
            session()->flash('error', 'Impossible de supprimer : des désignations utilisent cette catégorie.');

            return;
        }

        $categorie->delete();
        session()->flash('success', 'Catégorie supprimée avec succès.');
    }

    public function render()
    {
        $query = Categorie::withCount(['immobilisations', 'designations']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('categorie', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%')
                    ->orWhere('imputation_comptable', 'like', '%'.$this->search.'%');
            });
        }

        $query->orderBy(
            $this->resolveSortField(self::SORT_FIELDS, 'categorie'),
            $this->resolveSortDirection(),
        );

        $categories = $query->paginate($this->resolvePageSize());
        $total = Categorie::count();

        return view('livewire.categories.liste-categories', [
            'categories' => $categories,
            'total' => $total,
        ]);
    }
}
