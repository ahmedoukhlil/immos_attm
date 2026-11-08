<?php

namespace App\Livewire\Inventaires;

use App\Exceptions\InventaireWorkflowException;
use App\Livewire\Traits\WithSafeTableControls;
use App\Models\Inventaire;
use App\Models\InventaireLocalisation;
use App\Models\User;
use App\Services\InventaireService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ListeInventaires extends Component
{
    use WithPagination, WithSafeTableControls;

    private const SORT_FIELDS = ['annee', 'statut', 'date_debut', 'date_fin', 'created_at'];

    /**
     * Propriétés publiques pour les filtres et le tri
     */
    public $filterStatut = 'all';

    public $filterAnnee = '';

    public $sortField = 'annee';

    public $sortDirection = 'desc';

    public $perPage = 10;

    public $showAgentsModal = false;

    public $modalInventaireId = null;

    public $modalInventaireAnnee = null;

    /**
     * Initialisation du composant
     */
    public function mount(): void
    {
        // Réinitialiser la pagination si nécessaire
        $this->resetPage();
    }

    /**
     * Propriété calculée : Retourne la liste des années disponibles
     */
    public function getAnneesProperty()
    {
        return Inventaire::query()
            ->accessibleTo(Auth::user())
            ->distinct()
            ->orderBy('annee', 'desc')
            ->pluck('annee')
            ->values();
    }

    /**
     * Propriété calculée : Retourne l'inventaire actuellement en cours
     */
    public function getInventaireEnCoursProperty()
    {
        return Inventaire::query()
            ->accessibleTo(Auth::user())
            ->whereIn('statut', ['en_cours', 'en_preparation'])
            ->with(['creator'])
            ->withCount([
                'inventaireLocalisations',
                'inventaireLocalisations as localisations_terminees_count' => function ($q) {
                    $q->where('statut', 'termine');
                },
                'inventaireScans',
                'inventaireScans as scans_presents_count' => function ($q) {
                    $q->where('statut_scan', 'present');
                },
                'inventaireScans as scans_deplaces_count' => function ($q) {
                    $q->where('statut_scan', 'deplace');
                },
                'inventaireScans as scans_absents_count' => function ($q) {
                    $q->where('statut_scan', 'absent');
                },
            ])
            ->first();
    }

    /**
     * Propriété calculée : Compteurs rapides pour le header
     */
    public function getCompteursProperty(): array
    {
        return [
            'total' => Inventaire::accessibleTo(Auth::user())->count(),
            'en_cours' => Inventaire::accessibleTo(Auth::user())->whereIn('statut', ['en_cours', 'en_preparation'])->count(),
            'termines' => Inventaire::accessibleTo(Auth::user())->where('statut', 'termine')->count(),
            'clotures' => Inventaire::accessibleTo(Auth::user())->where('statut', 'cloture')->count(),
        ];
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
        $this->filterStatut = 'all';
        $this->filterAnnee = '';
        $this->resetPage();
    }

    /**
     * Archive un inventaire (change le statut à 'cloture' si 'termine')
     */
    public function archiverInventaire($inventaireId): void
    {
        // Vérifier que l'utilisateur est admin
        if (! Auth::user()->canManageOperations()) {
            session()->flash('error', 'Vous n\'avez pas les permissions nécessaires pour clôturer un inventaire.');

            return;
        }

        $inventaire = Inventaire::find($inventaireId);

        if (! $inventaire) {
            session()->flash('error', 'Inventaire introuvable.');

            return;
        }

        if ($inventaire->statut !== 'termine') {
            session()->flash('error', 'Seuls les inventaires terminés peuvent être clôturés.');

            return;
        }

        try {
            app(InventaireService::class)->cloturerInventaire($inventaire, Auth::user());
            session()->flash('success', "L'inventaire {$inventaire->annee} a été clôturé avec succès.");
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la clôture.'));
        }
    }

    /**
     * Supprime un inventaire
     */
    public function supprimerInventaire($inventaireId): void
    {
        // Vérifier que l'utilisateur est admin
        if (! Auth::user()->canManageOperations()) {
            session()->flash('error', 'Vous n\'avez pas les permissions nécessaires pour supprimer un inventaire.');

            return;
        }

        $inventaire = Inventaire::find($inventaireId);

        if (! $inventaire) {
            session()->flash('error', 'Inventaire introuvable.');

            return;
        }

        if ($inventaire->statut !== 'en_preparation' || $inventaire->inventaireScans()->exists()) {
            session()->flash('error', 'Seul un inventaire en préparation et sans scan peut être supprimé.');

            return;
        }

        try {
            app(InventaireService::class)->supprimerInventaire($inventaire, Auth::user());
            session()->flash('success', "L'inventaire {$inventaire->annee} a été supprimé avec succès.");
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la suppression.'));
        }
    }

    /**
     * Ouvre la modale de gestion des agents pour un inventaire
     */
    public function ouvrirModalAgents($inventaireId): void
    {
        if (! Auth::user()->canManageOperations()) {
            return;
        }

        $inventaire = Inventaire::find($inventaireId);
        if (! $inventaire || ! in_array($inventaire->statut, ['en_cours', 'en_preparation'])) {
            session()->flash('error', 'Inventaire introuvable ou non modifiable.');

            return;
        }

        $this->modalInventaireId = $inventaireId;
        $this->modalInventaireAnnee = $inventaire->annee;
        $this->showAgentsModal = true;
    }

    /**
     * Ferme la modale
     */
    public function fermerModalAgents(): void
    {
        $this->showAgentsModal = false;
        $this->modalInventaireId = null;
        $this->modalInventaireAnnee = null;
    }

    /**
     * Retourne les localisations de l'inventaire ouvert dans la modale
     */
    public function getModalLocalisationsProperty()
    {
        if (! Auth::user()->canManageOperations() || ! $this->modalInventaireId) {
            return collect();
        }

        return InventaireLocalisation::where('inventaire_id', $this->modalInventaireId)
            ->with(['localisation', 'agents'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Retourne tous les agents disponibles
     */
    public function getAllAgentsProperty()
    {
        if (! Auth::user()->canManageOperations()) {
            return collect();
        }

        return User::whereIn('role', ['agent', 'gestionnaire', 'admin'])
            ->orderBy('users')
            ->get();
    }

    /**
     * Toggle un agent sur une localisation d'inventaire
     */
    public function toggleAgentLoc($invLocId, $userId): void
    {
        if (! Auth::user()->canManageOperations()) {
            return;
        }

        $invLoc = InventaireLocalisation::find($invLocId);
        if (! $invLoc || $invLoc->inventaire_id !== $this->modalInventaireId) {
            return;
        }

        try {
            $agent = User::findOrFail((int) $userId);
            app(InventaireService::class)->basculerAgentLocalisation(
                $invLoc,
                $agent,
                Auth::user(),
            );
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Impossible de modifier cette assignation.'));
        }
    }

    /**
     * Ajoute un agent à toutes les localisations de l'inventaire
     */
    public function ajouterAgentPartout($userId): void
    {
        if (! Auth::user()->canManageOperations() || ! $this->modalInventaireId || ! $userId) {
            return;
        }

        try {
            $inventaire = Inventaire::findOrFail($this->modalInventaireId);
            $agent = User::findOrFail((int) $userId);
            app(InventaireService::class)->assignerAgentAToutesLesLocalisations(
                $inventaire,
                $agent,
                Auth::user(),
            );
            session()->flash('success', 'Agent ajouté à toutes les localisations.');
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Impossible d’ajouter cet agent aux localisations.'));
        }
    }

    /**
     * Construit la requête de base pour les inventaires
     */
    protected function getInventairesQuery()
    {
        $query = Inventaire::query()
            ->accessibleTo(Auth::user())
            ->with(['creator'])
            ->withCount([
                'inventaireLocalisations',
                'inventaireLocalisations as localisations_terminees_count' => function ($q) {
                    $q->where('statut', 'termine');
                },
                'inventaireScans',
                'inventaireScans as scans_presents_count' => function ($q) {
                    $q->where('statut_scan', 'present');
                },
                'inventaireScans as scans_deplaces_count' => function ($q) {
                    $q->where('statut_scan', 'deplace');
                },
                'inventaireScans as scans_absents_count' => function ($q) {
                    $q->where('statut_scan', 'absent');
                },
            ])
            // Sous-requête pour le total de biens attendus (somme des nombre_biens_attendus)
            ->withSum('inventaireLocalisations as total_biens_attendus', 'nombre_biens_attendus');

        // Filtre par statut
        if ($this->filterStatut !== 'all') {
            $query->where('statut', $this->filterStatut);
        }

        // Filtre par année
        if (! empty($this->filterAnnee)) {
            $query->where('annee', $this->filterAnnee);
        }

        // Tri
        $query->orderBy(
            $this->resolveSortField(self::SORT_FIELDS, 'annee'),
            $this->resolveSortDirection(),
        );

        return $query;
    }

    /**
     * Rendu du composant
     */
    public function render()
    {
        $inventaires = $this->getInventairesQuery()->paginate($this->resolvePageSize());

        return view('livewire.inventaires.liste-inventaires', [
            'inventaires' => $inventaires,
        ]);
    }

    protected function defaultPageSize(): int
    {
        return 10;
    }
}
