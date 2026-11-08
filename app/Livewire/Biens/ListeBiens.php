<?php

namespace App\Livewire\Biens;

use App\Livewire\Traits\WithCachedOptions;
use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Etat;
use App\Models\Immobilisation;
use App\Services\ReferenceCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ListeBiens extends Component
{
    use WithCachedOptions, WithPagination;

    private const ALLOWED_SORT_FIELDS = ['id', 'code_propose'];

    private const ALLOWED_PAGE_SIZES = [10, 20, 50, 100];

    public $search = '';

    #[Url]
    public $filterDesignation = '';

    #[Url]
    public $filterCategorie = '';

    public $filterLocalisation = '';

    #[Url]
    public $filterSite = '';

    public $filterEtat = '';

    public $filterNumOrdreMin = '';

    public $filterNumOrdreMax = '';

    public $sortField = 'id';

    public $sortDirection = 'asc';

    public $perPage = 20;

    public $selectedBiens = [];

    public bool $showArchived = false;

    public function mount(): void
    {
        $this->resetPage();
    }

    /**
     * Désignations avec leur catégorie (cache 1h, table de référence peu modifiée)
     */
    public function getDesignationsProperty()
    {
        return cache()->remember(ReferenceCache::key('designations', 'liste_biens'), 3600, function () {
            return Designation::with('categorie:id,categorie')
                ->orderBy('designation')
                ->get();
        });
    }

    public function getCategoriesProperty()
    {
        return cache()->remember(ReferenceCache::key('categories', 'liste_biens'), 3600, function () {
            return Categorie::orderBy('categorie')->get();
        });
    }

    public function getEtatsProperty()
    {
        return Etat::orderBy('id')->get();
    }

    public function getLocalisationOptionsProperty(): array
    {
        return array_merge([['value' => '', 'text' => 'Toutes les localisations']], $this->getCachedLocalisationOptions());
    }

    public function getSiteOptionsProperty(): array
    {
        return array_merge(
            [['value' => '', 'text' => 'Tous les sites']],
            $this->getCachedSiteOptions($this->filterLocalisation !== '' ? (int) $this->filterLocalisation : null),
        );
    }

    /**
     * Requête légère pour récupérer uniquement les identifiants (sans eager loading)
     */
    protected function getBiensIdsQuery(): Builder
    {
        $query = Immobilisation::query()->select('immobilisations.id');

        $this->applyFilters($query);

        return $this->applySort($query);
    }

    /**
     * Vérifie si tous les biens filtrés sont sélectionnés
     */
    public function getAllSelectedProperty()
    {
        if (empty($this->selectedBiens)) {
            return false;
        }

        $query = $this->getBiensIdsQuery()->reorder();
        $total = (clone $query)->count();

        if ($total === 0 || count($this->selectedBiens) !== $total) {
            return false;
        }

        return (clone $query)
            ->whereIn('immobilisations.id', $this->selectedBiens)
            ->count() === $total;
    }

    public function sortBy($field): void
    {
        if (! in_array($field, self::ALLOWED_SORT_FIELDS, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        $value = (int) $value;
        $this->perPage = in_array($value, self::ALLOWED_PAGE_SIZES, true) ? $value : 20;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterDesignation = '';
        $this->filterCategorie = '';
        $this->filterLocalisation = '';
        $this->filterSite = '';
        $this->filterEtat = '';
        $this->filterNumOrdreMin = '';
        $this->filterNumOrdreMax = '';
        $this->selectedBiens = [];
        $this->resetPage();
    }

    /**
     * Le site sélectionné doit appartenir à la nouvelle localisation
     */
    public function updatedFilterLocalisation(): void
    {
        $this->filterSite = '';
        $this->resetPage();
    }

    public function updatedShowArchived(): void
    {
        abort_unless(auth()->user()?->canManageOperations(), 403);

        $this->selectedBiens = [];
        $this->resetPage();
    }

    /**
     * Sélectionne ou désélectionne tous les biens (toutes pages)
     */
    public function toggleSelectAll(): void
    {
        $allBiensIds = $this->getBiensIdsQuery()->pluck('id')->toArray();

        $allSelected = ! empty($allBiensIds)
            && count($this->selectedBiens) === count($allBiensIds)
            && empty(array_diff($allBiensIds, $this->selectedBiens));

        $this->selectedBiens = $allSelected
            ? []
            : array_values(array_unique(array_merge($this->selectedBiens, $allBiensIds)));
    }

    /**
     * Archive une immobilisation
     */
    public function deleteBien($bienId): void
    {
        if (! Auth::user()->canManageOperations()) {
            session()->flash('error', 'Vous n\'avez pas les permissions nécessaires pour supprimer un bien.');

            return;
        }

        $bien = Immobilisation::find($bienId);

        if (! $bien) {
            session()->flash('error', 'Immobilisation introuvable.');

            return;
        }

        $bien->delete();
        session()->flash('success', 'L\'immobilisation a été archivée avec succès.');
        $this->selectedBiens = array_values(array_diff($this->selectedBiens, [$bienId]));
    }

    /**
     * Restaure une immobilisation précédemment archivée.
     */
    public function restoreBien($bienId): void
    {
        abort_unless(auth()->user()?->canManageOperations(), 403);

        $bien = Immobilisation::onlyTrashed()->find($bienId);

        if (! $bien) {
            session()->flash('error', 'Immobilisation archivée introuvable.');

            return;
        }

        $bien->restore();
        session()->flash('success', 'L\'immobilisation a été restaurée avec succès.');
    }

    /**
     * Exporte les biens sélectionnés
     */
    public function exportSelected()
    {
        abort_unless(auth()->user()?->canManageOperations(), 403);

        if (empty($this->selectedBiens)) {
            session()->flash('warning', 'Veuillez sélectionner au moins un bien à exporter.');

            return;
        }

        return redirect()->route('biens.export-excel', ['ids' => implode(',', $this->selectedBiens)]);
    }

    protected function getBiensQuery(): Builder
    {
        $query = Immobilisation::with([
            'designation',
            'categorie',
            'etat',
            'site.localisation',
        ]);

        $this->applyFilters($query);

        return $this->applySort($query);
    }

    private function applyFilters(Builder $query): void
    {
        if ($this->showArchived) {
            abort_unless(auth()->user()?->canManageOperations(), 403);
            $query->onlyTrashed();
        }

        $search = trim((string) $this->search);

        if ($search !== '') {
            // « GS1234 » (identifiant de scan) et « 1234 » désignent la même immobilisation.
            $identifiant = preg_replace('/^GS/i', '', $search);

            $query->where(function (Builder $query) use ($search, $identifiant): void {
                if (ctype_digit($identifiant)) {
                    $query->where('immobilisations.id', (int) $identifiant);
                }

                // « Ordinateur 3 » : désignation suivie du numéro d'article.
                if (preg_match('/^(.*\S)\s+(\d+)$/u', $search, $article)) {
                    $query->orWhere(fn (Builder $q) => $q
                        ->where('numero_article', (int) $article[2])
                        ->whereHas('designation', fn (Builder $relation) => $relation->where('designation', 'like', "%{$article[1]}%")));
                }

                $query->orWhere('code_propose', $search)
                    ->orWhere('code_premier_inventaire', $search)
                    ->orWhere('code_dernier_inventaire', $search)
                    ->orWhere('descriptif', 'like', "%{$search}%")
                    ->orWhere('marque', 'like', "%{$search}%")
                    ->orWhere('modele', 'like', "%{$search}%")
                    ->orWhere('numero_chassis', 'like', "%{$search}%")
                    ->orWhere('numero_attm', 'like', "%{$search}%")
                    ->orWhere('code_immo', 'like', "%{$search}%")
                    ->orWhereHas('designation', fn (Builder $relation) => $relation->where('designation', 'like', "%{$search}%"))
                    ->orWhereHas('site', fn (Builder $relation) => $relation->where('site', 'like', "%{$search}%")
                        ->orWhere('responsable', 'like', "%{$search}%"));
            });
        }

        if (! empty($this->filterDesignation)) {
            $query->where('designation_id', $this->filterDesignation);
        }

        if (! empty($this->filterCategorie)) {
            $query->where('categorie_id', $this->filterCategorie);
        }

        if (! empty($this->filterEtat)) {
            $query->where('etat_id', $this->filterEtat);
        }

        if (! empty($this->filterSite)) {
            $query->where('site_id', $this->filterSite);
        } elseif (! empty($this->filterLocalisation)) {
            $query->whereHas('site', fn (Builder $relation) => $relation->where('localisation_id', $this->filterLocalisation));
        }

        if ($this->filterNumOrdreMin !== '' && is_numeric($this->filterNumOrdreMin)) {
            $query->where('immobilisations.id', '>=', (int) $this->filterNumOrdreMin);
        }

        if ($this->filterNumOrdreMax !== '' && is_numeric($this->filterNumOrdreMax)) {
            $query->where('immobilisations.id', '<=', (int) $this->filterNumOrdreMax);
        }
    }

    private function applySort(Builder $query): Builder
    {
        $field = in_array($this->sortField, self::ALLOWED_SORT_FIELDS, true)
            ? $this->sortField
            : 'id';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('immobilisations.'.$field, $direction);
    }

    public function render()
    {
        $perPage = in_array((int) $this->perPage, self::ALLOWED_PAGE_SIZES, true)
            ? (int) $this->perPage
            : 20;

        return view('livewire.biens.liste-biens', [
            'biens' => $this->getBiensQuery()->paginate($perPage),
        ]);
    }
}
