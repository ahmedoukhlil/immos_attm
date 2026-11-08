<?php

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class GestionRoles extends Component
{
    use WithPagination;

    public $search = '';

    public $filterRole = 'all'; // all, admin, agent

    public $selectedUserId = null;

    public $newRole = '';

    public $confirmingRoleChange = false;

    protected $queryString = ['search', 'filterRole'];

    /**
     * Vérification des permissions
     */
    public function mount()
    {
        $user = auth()->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Accès non autorisé. Seuls les administrateurs peuvent gérer les rôles.');
        }
    }

    /**
     * Reset pagination lors de la recherche
     */
    public function updatingSearch()
    {
        $this->resetPage();
    }

    /**
     * Reset pagination lors du changement de filtre
     */
    public function updatingFilterRole()
    {
        $this->resetPage();
    }

    /**
     * Confirmer le changement de rôle
     */
    public function confirmRoleChange($userId, $currentRole, $targetRole = null)
    {
        $this->selectedUserId = $userId;

        // Si un rôle cible est spécifié, l'utiliser
        if ($targetRole) {
            $this->newRole = $targetRole;
        } else {
            // Sinon, toggle entre les rôles
            $roles = ['admin', 'agent'];
            $currentIndex = array_search($currentRole, $roles);
            $this->newRole = $roles[($currentIndex + 1) % count($roles)];
        }

        $this->confirmingRoleChange = true;
    }

    /**
     * Annuler le changement de rôle
     */
    public function cancelRoleChange()
    {
        $this->confirmingRoleChange = false;
        $this->selectedUserId = null;
        $this->newRole = '';
    }

    /**
     * Changer le rôle de l'utilisateur
     */
    public function changeRole()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        if (! in_array($this->newRole, ['admin', 'agent'], true)) {
            abort(422, 'Rôle invalide.');
        }

        $user = User::find($this->selectedUserId);

        if (! $user) {
            session()->flash('error', 'Utilisateur introuvable.');
            $this->cancelRoleChange();

            return;
        }

        // Empêcher de changer son propre rôle
        if ($user->idUser === auth()->user()->idUser) {
            session()->flash('error', 'Vous ne pouvez pas modifier votre propre rôle.');
            $this->cancelRoleChange();

            return;
        }

        // Vérifier qu'il reste au moins un administrateur.
        if ($user->role === 'admin' && $this->newRole !== 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                session()->flash('error', 'Impossible de retirer le rôle admin. Il doit y avoir au moins un administrateur principal.');
                $this->cancelRoleChange();

                return;
            }
        }

        // Changer le rôle
        $oldRole = $user->role;
        $user->role = $this->newRole;
        $user->save();

        $roleNames = [
            'admin' => 'administrateur',
            'agent' => 'agent',
        ];
        $roleName = $roleNames[$this->newRole] ?? $this->newRole;
        session()->flash('success', "Le rôle de {$user->users} a été changé en {$roleName} avec succès.");

        $this->cancelRoleChange();
    }

    /**
     * Changer le rôle directement (sans confirmation)
     */
    public function toggleRole($userId)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $user = User::find($userId);

        if (! $user) {
            session()->flash('error', 'Utilisateur introuvable.');

            return;
        }

        // Empêcher de changer son propre rôle
        if ($user->idUser === auth()->user()->idUser) {
            session()->flash('error', 'Vous ne pouvez pas modifier votre propre rôle.');

            return;
        }

        // Vérifier qu'il reste au moins un administrateur.
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                session()->flash('error', 'Impossible de retirer le rôle admin. Il doit y avoir au moins un administrateur principal.');

                return;
            }
        }

        // Alterner entre les deux rôles métier.
        $roles = ['admin', 'agent'];
        $currentIndex = array_search($user->role, $roles);
        $nextIndex = ($currentIndex + 1) % count($roles);
        $user->role = $roles[$nextIndex];
        $user->save();

        $roleNames = [
            'admin' => 'administrateur',
            'agent' => 'agent',
        ];
        $roleName = $roleNames[$user->role] ?? $user->role;
        session()->flash('success', "Le rôle de {$user->users} a été changé en {$roleName} avec succès.");
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where('users', 'like', '%'.$this->search.'%');
            })
            ->when($this->filterRole !== 'all', function ($query) {
                $query->where('role', $this->filterRole);
            })
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'agent' THEN 2 ELSE 3 END")
            ->orderBy('users')
            ->paginate(20);

        // Statistiques
        $stats = [
            'total' => User::count(),
            'admins' => User::where('role', 'admin')->count(),
            'agents' => User::where('role', 'agent')->count(),
        ];

        return view('livewire.users.gestion-roles', [
            'users' => $users,
            'stats' => $stats,
        ]);
    }
}
