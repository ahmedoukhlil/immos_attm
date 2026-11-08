<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class FormUser extends Component
{
    /**
     * Propriétés publiques
     */
    public $userId = null;

    public $users = ''; // Nom d'utilisateur (colonne 'users' dans la table)

    public $mdp = ''; // Mot de passe

    public $mdp_confirmation = '';

    public $role = 'agent';

    /**
     * Mode édition ou création
     */
    public $isEdit = false;

    /**
     * Initialisation du composant
     *
     * @param  User|int|string|null  $user  Instance de l'utilisateur pour l'édition, ID, ou null pour la création
     */
    public function mount($user = null): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        if ($user) {
            // Si $user est une chaîne ou un entier (ID), charger l'utilisateur
            if (is_string($user) || is_int($user)) {
                $user = User::findOrFail($user);
            }

            // Vérifier que $user est bien une instance de User
            if ($user instanceof User) {
                $this->isEdit = true;
                $this->userId = $user->idUser;
                $this->users = $user->users;
                $this->role = $user->role;
            }
        }
    }

    /**
     * Options pour SearchableSelect : Rôles
     */
    public function getRoleOptionsProperty()
    {
        return [
            ['value' => 'agent', 'text' => 'Agent'],
            ['value' => 'admin', 'text' => 'Administrateur'],
        ];
    }

    /**
     * Règles de validation
     */
    protected function rules(): array
    {
        $rules = [
            'users' => [
                'required',
                'string',
                'max:255',
                $this->isEdit
                    ? 'unique:users,users,'.$this->userId.',idUser'
                    : 'unique:users,users',
            ],
            'role' => 'required|in:admin,agent',
        ];

        // Règles pour le mot de passe
        if ($this->isEdit) {
            // En édition, le mot de passe est optionnel
            if (! empty($this->mdp)) {
                $rules['mdp'] = ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'max:255', 'confirmed'];
            }
        } else {
            // En création, le mot de passe est obligatoire
            $rules['mdp'] = ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'max:255', 'confirmed'];
        }

        return $rules;
    }

    /**
     * Messages de validation personnalisés
     */
    protected function messages(): array
    {
        return [
            'users.required' => 'Le nom d\'utilisateur est obligatoire.',
            'users.max' => 'Le nom d\'utilisateur ne peut pas dépasser 255 caractères.',
            'users.unique' => 'Ce nom d\'utilisateur est déjà utilisé.',
            'mdp.required' => 'Le mot de passe est obligatoire.',
            'mdp.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'mdp.max' => 'Le mot de passe ne peut pas dépasser 255 caractères.',
            'mdp.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'mdp.password.mixed' => 'Le mot de passe doit contenir au moins une lettre majuscule et une lettre minuscule.',
            'mdp.password.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Le rôle sélectionné est invalide.',
        ];
    }

    /**
     * Sauvegarde l'utilisateur (création ou édition)
     */
    public function save(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $this->validate();

        // Vérifier si on peut changer le rôle du dernier admin
        if ($this->isEdit) {
            $user = User::findOrFail($this->userId);

            // Vérifier si on change le rôle d'admin vers agent
            if ($user->role === 'admin' && $this->role === 'agent') {
                $adminsCount = User::where('role', 'admin')
                    ->where('idUser', '!=', $this->userId)
                    ->count();

                if ($adminsCount === 0) {
                    $this->addError('role', 'Impossible de changer le rôle du dernier administrateur.');

                    return;
                }
            }
        }

        // Préparer les données
        $data = [
            'users' => $this->users,
            'role' => $this->role,
        ];

        // Ajouter le mot de passe seulement s'il est fourni
        if (! empty($this->mdp)) {
            $data['mdp'] = Hash::make($this->mdp);
        }

        // Créer ou mettre à jour
        if ($this->isEdit) {
            $user = User::findOrFail($this->userId);
            $user->update($data);
            session()->flash('success', "L'utilisateur {$user->users} a été modifié avec succès.");
        } else {
            // En création, le mot de passe est obligatoire
            if (empty($data['mdp'])) {
                $this->addError('mdp', 'Le mot de passe est obligatoire.');

                return;
            }
            $user = User::create($data);
            session()->flash('success', "L'utilisateur {$user->users} a été créé avec succès.");
        }

        // Rediriger vers la liste
        $this->redirect(route('users.index'), navigate: true);
    }

    /**
     * Annuler et retourner à la liste
     */
    public function cancel(): void
    {
        $this->redirect(route('users.index'), navigate: true);
    }

    /**
     * Render du composant
     */
    public function render()
    {
        return view('livewire.users.form-user');
    }
}
