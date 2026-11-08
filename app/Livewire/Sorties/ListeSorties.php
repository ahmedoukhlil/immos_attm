<?php

namespace App\Livewire\Sorties;

use App\Models\Immobilisation;
use App\Models\Sortie;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Décisions de sortie et registre des biens sortis.
 */
#[Layout('components.layouts.app')]
class ListeSorties extends Component
{
    use WithPagination;

    #[Url]
    public string $onglet = 'decisions';

    public string $search = '';

    public string $filtreMotif = '';

    public string $filtreStatut = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403, 'Accès non autorisé.');
    }

    public function updating(string $propriete): void
    {
        if (in_array($propriete, ['onglet', 'search', 'filtreMotif', 'filtreStatut'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $search = trim($this->search);

        $decisions = $this->onglet === 'decisions'
            ? Sortie::query()
                ->withCount('lignes')
                ->with('inventaire')
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('numero', 'like', "%{$search}%")
                    ->orWhere('observations', 'like', "%{$search}%")))
                ->when($this->filtreMotif !== '', fn ($query) => $query->where('motif', $this->filtreMotif))
                ->when($this->filtreStatut !== '', fn ($query) => $query->where('statut', $this->filtreStatut))
                ->latest('id')
                ->paginate(20)
            : null;

        $biensSortis = $this->onglet === 'registre'
            ? Immobilisation::query()
                ->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE)
                ->whereNotNull('sortie_id')
                ->with(['sortie', 'designation', 'site.localisation'])
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('code_propose', $search)
                    ->orWhere('code_dernier_inventaire', $search)
                    ->orWhereHas('designation', fn ($relation) => $relation->where('designation', 'like', "%{$search}%"))
                    ->orWhereHas('sortie', fn ($relation) => $relation->where('numero', 'like', "%{$search}%"))))
                ->when($this->filtreMotif !== '', fn ($query) => $query->whereHas('sortie', fn ($relation) => $relation->where('motif', $this->filtreMotif)))
                ->orderByDesc('date_sortie')
                ->orderByDesc('id')
                ->paginate(25)
            : null;

        return view('livewire.sorties.liste-sorties', compact('decisions', 'biensSortis'));
    }
}
