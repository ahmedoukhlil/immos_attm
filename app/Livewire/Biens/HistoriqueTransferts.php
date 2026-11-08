<?php

namespace App\Livewire\Biens;

use App\Models\HistoriqueTransfert;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class HistoriqueTransferts extends Component
{
    use WithPagination;

    public $search = '';

    public $filterGroupe = '';

    public $filterDateDebut = '';

    public $filterDateFin = '';

    /**
     * Vérification des permissions
     */
    public function mount()
    {
        $user = auth()->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * Réinitialiser les filtres
     */
    public function resetFilters()
    {
        $this->reset(['search', 'filterGroupe', 'filterDateDebut', 'filterDateFin']);
        $this->resetPage();
    }

    /**
     * Propriété calculée : Historique des transferts
     */
    public function getHistoriqueProperty()
    {
        $query = HistoriqueTransfert::with([
            'immobilisation.designation',
            'utilisateur',
            'ancienSite',
            'nouveauSite',
        ])
            ->orderBy('date_transfert', 'desc')
            ->orderBy('groupe_transfert_id', 'desc');

        // Recherche par identifiant d'immobilisation ou libellés
        if (! empty($this->search)) {
            $search = trim($this->search);
            $query->where(function ($q) use ($search) {
                $q->where('immobilisation_id', 'like', '%'.preg_replace('/^GS/i', '', $search).'%')
                    ->orWhere('ancien_site_libelle', 'like', '%'.$search.'%')
                    ->orWhere('nouveau_site_libelle', 'like', '%'.$search.'%')
                    ->orWhere('raison', 'like', '%'.$search.'%');
            });
        }

        // Filtre par groupe
        if (! empty($this->filterGroupe)) {
            $query->where('groupe_transfert_id', $this->filterGroupe);
        }

        // Filtre par date
        if (! empty($this->filterDateDebut)) {
            $query->whereDate('date_transfert', '>=', $this->filterDateDebut);
        }
        if (! empty($this->filterDateFin)) {
            $query->whereDate('date_transfert', '<=', $this->filterDateFin);
        }

        return $query->paginate(20);
    }

    /**
     * Obtenir les groupes de transferts uniques
     */
    public function getGroupesProperty()
    {
        return HistoriqueTransfert::select('groupe_transfert_id')
            ->whereNotNull('groupe_transfert_id')
            ->distinct()
            ->orderBy('groupe_transfert_id', 'desc')
            ->limit(50)
            ->pluck('groupe_transfert_id')
            ->toArray();
    }

    public function render()
    {
        return view('livewire.biens.historique-transferts', [
            'historique' => $this->historique,
            'groupes' => $this->groupes,
        ]);
    }
}
