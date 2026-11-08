<?php

namespace App\Livewire\Users;

use App\Livewire\Traits\WithSafeTableControls;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class ListeUsers extends Component
{
    use WithPagination, WithSafeTableControls;

    private const SORT_FIELDS = ['users', 'role', 'idUser'];

    /**
     * Propriétés publiques pour les filtres et la recherche
     */
    public $search = '';

    public $filterRole = 'all'; // all, admin, agent, superuser, immobilisation, stock

    public $sortField = 'users';

    public $sortDirection = 'asc';

    public $perPage = 20;

    public $selectedUsers = [];

    /**
     * Initialisation du composant
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $this->resetPage();
    }

    /**
     * Réinitialiser les filtres
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterRole = 'all';
        $this->selectedUsers = [];
        $this->resetPage();
    }

    /**
     * Trier par champ
     */
    public function sortBy($field): void
    {
        $this->changeSort((string) $field, self::SORT_FIELDS);
    }

    /**
     * Toggle sélection d'un utilisateur
     */
    public function toggleSelect($userId): void
    {
        if (in_array($userId, $this->selectedUsers)) {
            $this->selectedUsers = array_diff($this->selectedUsers, [$userId]);
        } else {
            $this->selectedUsers[] = $userId;
        }
    }

    /**
     * Sélectionner/désélectionner tous les utilisateurs
     */
    public function toggleSelectAll(): void
    {
        if (count($this->selectedUsers) === $this->getUsersQuery()->count()) {
            $this->selectedUsers = [];
        } else {
            $this->selectedUsers = $this->getUsersQuery()->pluck('idUser')->toArray();
        }
    }

    // Note: La table users n'a pas de colonne 'actif', cette fonctionnalité n'est pas disponible

    /**
     * Supprimer un utilisateur
     */
    public function delete($userId): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $user = User::findOrFail($userId);

        // Empêcher la suppression de l'utilisateur connecté
        if ($user->idUser === auth()->id()) {
            session()->flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return;
        }

        // Empêcher de supprimer le dernier administrateur
        if ($user->role === 'admin') {
            $adminsCount = User::where('role', 'admin')
                ->where('idUser', '!=', $userId)
                ->count();

            if ($adminsCount === 0) {
                session()->flash('error', 'Impossible de supprimer le dernier administrateur.');

                return;
            }
        }

        $userName = $user->users;
        $user->delete();

        session()->flash('success', "L'utilisateur {$userName} a été supprimé avec succès.");
        $this->resetPage();
    }

    /**
     * Requête de base pour les utilisateurs
     */
    protected function getUsersQuery()
    {
        $query = User::query();

        // Recherche
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('users', 'like', '%'.$this->search.'%');
            });
        }

        // Filtre par rôle
        if ($this->filterRole !== 'all') {
            $query->where('role', $this->filterRole);
        }

        // Note: La table users n'a pas de colonne 'actif', ce filtre a été supprimé

        // Tri
        $query->orderBy(
            $this->resolveSortField(self::SORT_FIELDS, 'users'),
            $this->resolveSortDirection(),
        );

        return $query;
    }

    /**
     * Propriété calculée : Liste paginée des utilisateurs
     */
    public function getUsersProperty()
    {
        return $this->getUsersQuery()->paginate($this->resolvePageSize());
    }

    /**
     * Propriété calculée : Statistiques
     */
    public function getStatsProperty()
    {
        $stats = User::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins")
            ->selectRaw("SUM(CASE WHEN role = 'agent' THEN 1 ELSE 0 END) as agents")
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'admins' => (int) ($stats->admins ?? 0),
            'agents' => (int) ($stats->agents ?? 0),
        ];
    }

    /**
     * Render du composant
     */
    public function render()
    {
        return view('livewire.users.liste-users', [
            'users' => $this->users,
            'stats' => $this->stats,
        ]);
    }
}
