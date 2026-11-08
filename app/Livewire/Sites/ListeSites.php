<?php

namespace App\Livewire\Sites;

use App\Livewire\Traits\WithCachedOptions;
use App\Livewire\Traits\WithSafeTableControls;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ListeSites extends Component
{
    use WithCachedOptions, WithPagination, WithSafeTableControls;

    private const SORT_FIELDS = ['site', 'responsable'];

    public $search = '';

    public $filterLocalisation = '';

    public $sortField = 'site';

    public $sortDirection = 'asc';

    public $perPage = 20;

    public $selectedSites = [];

    public function mount(): void
    {
        $this->resetPage();
    }

    /**
     * Options pour SearchableSelect : Localisations
     */
    public function getLocalisationOptionsProperty(): array
    {
        return array_merge([['value' => '', 'text' => 'Toutes']], $this->getCachedLocalisationOptions());
    }

    public function sortBy($field): void
    {
        $this->changeSort((string) $field, self::SORT_FIELDS);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterLocalisation = '';
        $this->selectedSites = [];
        $this->resetPage();
    }

    /**
     * Supprime un site sans immobilisation
     */
    public function deleteSite($siteId): void
    {
        if (! Auth::user()->canManageOperations()) {
            session()->flash('error', 'Vous n\'avez pas les permissions nécessaires pour supprimer un site.');

            return;
        }

        $site = Site::find($siteId);

        if (! $site) {
            session()->flash('error', 'Site introuvable.');

            return;
        }

        $nombreImmobilisations = $site->immobilisations()->count();

        if ($nombreImmobilisations > 0) {
            session()->flash('error', "Impossible de supprimer ce site : {$nombreImmobilisations} immobilisation(s) y sont associée(s).");

            return;
        }

        try {
            $site->delete();
            Cache::forget('sites_total_count');
            session()->flash('success', 'Le site a été supprimé avec succès.');

            $this->selectedSites = array_diff($this->selectedSites, [$siteId]);
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'Erreur lors de la suppression.');
        }
    }

    protected function getSitesQuery()
    {
        return Site::with('localisation')
            ->withCount('immobilisations')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('site', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%')
                        ->orWhere('responsable', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterLocalisation !== '', fn ($query) => $query->where('localisation_id', $this->filterLocalisation))
            ->orderBy(
                $this->resolveSortField(self::SORT_FIELDS, 'site'),
                $this->resolveSortDirection(),
            );
    }

    public function render()
    {
        return view('livewire.sites.liste-sites', [
            'sites' => $this->getSitesQuery()->paginate($this->resolvePageSize()),
            'totalSites' => Cache::remember('sites_total_count', 300, fn () => Site::count()),
        ]);
    }
}
