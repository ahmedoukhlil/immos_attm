<?php

namespace App\Livewire\Inventaires;

use App\Exceptions\InventaireWorkflowException;
use App\Models\Inventaire;
use App\Models\Localisation;
use App\Models\User;
use App\Services\InventaireService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DemarrerInventaire extends Component
{
    /**
     * Propriétés publiques du formulaire
     */
    public $annee;

    public $date_debut;

    public $observation = '';

    public $localisationsSelectionnees = [];

    public $assignations = []; // [localisation_id => [user_id, user_id, ...]]

    public $agentGlobalSelect = ''; // Pour le select global d'assignation

    public $rechercheLocalisation = ''; // Filtre de recherche pour les localisations

    /**
     * Étape actuelle du wizard (1, 2, ou 3)
     */
    public $etapeActuelle = 1;

    /**
     * Initialisation du composant
     */
    public function mount(): void
    {
        // Vérifier qu'aucun inventaire n'est déjà en cours ou en préparation
        $inventaireExistant = Inventaire::whereIn('statut', ['en_cours', 'en_preparation'])->first();

        if ($inventaireExistant) {
            session()->flash('error', "Un inventaire est déjà en cours ou en préparation pour l'année {$inventaireExistant->annee}.");
            $this->redirect(route('inventaires.show', $inventaireExistant), navigate: true);

            return;
        }

        // Initialiser les valeurs par défaut (l'inventaire porte sur l'année n-1)
        $this->annee = date('Y') - 1;
        $this->date_debut = now()->format('Y-m-d');

        // Pré-sélectionner toutes les localisations actives
        $this->selectToutesLocalisations();
    }

    /**
     * Propriété calculée : Retourne les années disponibles (non utilisées)
     */
    public function getAnneesDisponiblesProperty()
    {
        $anneesUtilisees = Inventaire::pluck('annee')->toArray();
        $anneeActuelle = (int) date('Y');
        $anneesDisponibles = [];

        // Générer les années de n-3 à n+1 (l'inventaire porte généralement sur l'année n-1)
        for ($i = -3; $i <= 1; $i++) {
            $annee = $anneeActuelle + $i;
            if (! in_array($annee, $anneesUtilisees)) {
                $anneesDisponibles[] = $annee;
            }
        }

        return $anneesDisponibles;
    }

    /**
     * Propriété calculée : Retourne toutes les localisations avec stats pré-calculées
     * Évite les requêtes N+1 en comptant sites et immobilisations en SQL
     */
    public function getLocalisationsProperty()
    {
        return Localisation::withCount(['sites', 'immobilisations as biens_count'])
            ->orderBy('localisation')
            ->get();
    }

    /**
     * Propriété calculée : Retourne tous les agents (users avec role 'agent' ou 'admin')
     */
    public function getAgentsProperty()
    {
        return User::whereIn('role', ['agent', 'admin'])
            ->orderBy('users')
            ->get();
    }

    /**
     * Options pour SearchableSelect : Années disponibles
     */
    public function getAnneeOptionsProperty()
    {
        return collect($this->anneesDisponibles)
            ->map(function ($annee) {
                return [
                    'value' => (string) $annee,
                    'text' => (string) $annee,
                ];
            })
            ->toArray();
    }

    /**
     * Options pour SearchableSelect : Agents
     */
    public function getAgentOptionsProperty()
    {
        $options = [[
            'value' => '',
            'text' => 'Sélectionner un agent',
        ]];

        $agents = User::whereIn('role', ['agent', 'admin'])
            ->orderBy('users')
            ->get()
            ->map(function ($agent) {
                return [
                    'value' => (string) $agent->idUser,
                    'text' => $agent->users.' ('.$agent->role_name.')',
                ];
            })
            ->toArray();

        return array_merge($options, $agents);
    }

    /**
     * Propriété calculée : Retourne les localisations filtrées par la recherche
     */
    public function getLocalisationsFiltreesProperty()
    {
        $recherche = trim($this->rechercheLocalisation);

        if (empty($recherche)) {
            return $this->localisations;
        }

        return $this->localisations->filter(function ($loc) use ($recherche) {
            $terme = mb_strtolower($recherche);

            return str_contains(mb_strtolower($loc->localisation ?? ''), $terme)
                || str_contains(mb_strtolower($loc->code ?? ''), $terme);
        });
    }

    /**
     * Propriété calculée : Retourne le nombre total de localisations sélectionnées
     */
    public function getTotalLocalisationsProperty(): int
    {
        return count($this->localisationsSelectionnees);
    }

    /**
     * Propriété calculée : Retourne le nombre total de biens attendus dans les localisations sélectionnées
     * Utilise les données pré-calculées de getLocalisationsProperty pour éviter des requêtes supplémentaires
     */
    public function getTotalBiensAttendusProperty(): int
    {
        if (empty($this->localisationsSelectionnees)) {
            return 0;
        }

        return $this->localisations
            ->whereIn('id', $this->localisationsSelectionnees)
            ->sum('biens_count');
    }

    /**
     * Propriété calculée : Retourne le nombre d'agents distincts impliqués
     */
    public function getAgentsImpliquesProperty(): int
    {
        $allAgents = [];
        foreach ($this->assignations as $agents) {
            if (is_array($agents)) {
                $allAgents = array_merge($allAgents, $agents);
            }
        }

        return count(array_unique($allAgents));
    }

    /**
     * Règles de validation
     */
    protected function rules(): array
    {
        return [
            'annee' => 'required|integer|unique:inventaires,annee',
            'date_debut' => 'required|date|after_or_equal:today',
            'observation' => 'nullable|string|max:1000',
            'localisationsSelectionnees' => 'required|array|min:1',
            'localisationsSelectionnees.*' => 'exists:localisations,id',
        ];
    }

    /**
     * Messages de validation personnalisés
     */
    protected function messages(): array
    {
        return [
            'annee.required' => 'L\'année est obligatoire.',
            'annee.unique' => 'Un inventaire existe déjà pour cette année.',
            'annee.integer' => 'L\'année doit être un nombre entier.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_debut.after_or_equal' => 'La date de début ne peut pas être dans le passé.',
            'localisationsSelectionnees.required' => 'Vous devez sélectionner au moins une localisation.',
            'localisationsSelectionnees.min' => 'Vous devez sélectionner au moins une localisation.',
            'observation.max' => 'L\'observation ne peut pas dépasser 1000 caractères.',
        ];
    }

    /**
     * Vérifie si l'année est unique (pour validation en temps réel)
     */
    public function updatedAnnee($value)
    {
        if (empty($value)) {
            return;
        }

        $exists = Inventaire::where('annee', $value)->exists();

        if ($exists) {
            $this->addError('annee', 'Un inventaire existe déjà pour cette année.');
        } else {
            $this->resetErrorBag('annee');
        }
    }

    /**
     * Toggle la sélection d'une localisation
     */
    public function toggleLocalisation($localisationId): void
    {
        $localisationId = (int) $localisationId;

        if (in_array($localisationId, $this->localisationsSelectionnees)) {
            // Retirer de la sélection
            $this->localisationsSelectionnees = array_values(
                array_filter($this->localisationsSelectionnees, fn ($id) => $id !== $localisationId)
            );
            // Retirer aussi l'assignation si elle existe
            unset($this->assignations[$localisationId]);
        } else {
            // Ajouter à la sélection
            $this->localisationsSelectionnees[] = $localisationId;
        }
    }

    /**
     * Sélectionne toutes les localisations actives
     */
    public function selectToutesLocalisations(): void
    {
        $this->localisationsSelectionnees = $this->localisations->pluck('id')->toArray();
    }

    /**
     * Désélectionne toutes les localisations
     */
    public function deselectToutesLocalisations(): void
    {
        $this->localisationsSelectionnees = [];
        $this->assignations = [];
    }

    /**
     * Ajoute ou retire un agent d'une localisation (toggle)
     */
    public function toggleAgent($localisationId, $userId): void
    {
        $localisationId = (int) $localisationId;
        $userId = $userId ? (int) $userId : null;

        if (! $userId || ! in_array($localisationId, $this->localisationsSelectionnees)) {
            return;
        }

        if (! isset($this->assignations[$localisationId])) {
            $this->assignations[$localisationId] = [];
        }

        $index = array_search($userId, $this->assignations[$localisationId]);
        if ($index !== false) {
            array_splice($this->assignations[$localisationId], $index, 1);
            if (empty($this->assignations[$localisationId])) {
                unset($this->assignations[$localisationId]);
            }
        } else {
            $this->assignations[$localisationId][] = $userId;
        }
    }

    /**
     * Retire un agent spécifique d'une localisation
     */
    public function retirerAgent($localisationId, $userId): void
    {
        $localisationId = (int) $localisationId;
        $userId = (int) $userId;

        if (isset($this->assignations[$localisationId])) {
            $this->assignations[$localisationId] = array_values(
                array_filter($this->assignations[$localisationId], fn ($id) => $id !== $userId)
            );
            if (empty($this->assignations[$localisationId])) {
                unset($this->assignations[$localisationId]);
            }
        }
    }

    /**
     * Réagit au changement de l'agent global
     */
    public function updatedAgentGlobalSelect($value): void
    {
        if ($value) {
            $this->assignerAgentGlobal($value);
            $this->agentGlobalSelect = '';
        }
    }

    /**
     * Ajoute un agent à toutes les localisations sélectionnées (s'il n'y est pas déjà)
     */
    public function assignerAgentGlobal($userId): void
    {
        if (! $userId) {
            return;
        }

        $userId = (int) $userId;

        foreach ($this->localisationsSelectionnees as $localisationId) {
            if (! isset($this->assignations[$localisationId])) {
                $this->assignations[$localisationId] = [];
            }
            if (! in_array($userId, $this->assignations[$localisationId])) {
                $this->assignations[$localisationId][] = $userId;
            }
        }
    }

    /**
     * Passe à l'étape suivante
     */
    public function etapeSuivante(): void
    {
        // Valider l'étape actuelle avant de passer à la suivante
        if ($this->etapeActuelle === 1) {
            $this->validate([
                'annee' => 'required|integer|unique:inventaires,annee',
                'date_debut' => 'required|date|after_or_equal:today',
            ]);
        } elseif ($this->etapeActuelle === 2) {
            $this->validate([
                'localisationsSelectionnees' => 'required|array|min:1',
            ]);
        }

        if ($this->etapeActuelle < 3) {
            $this->etapeActuelle++;
        }
    }

    /**
     * Retourne à l'étape précédente
     */
    public function etapePrecedente(): void
    {
        if ($this->etapeActuelle > 1) {
            $this->etapeActuelle--;
        }
    }

    /**
     * Démarre l'inventaire (création)
     */
    public function demarrer()
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        // Valider toutes les données
        $validated = $this->validate();

        try {
            $inventaire = app(InventaireService::class)->creerInventaire([
                ...$validated,
                'localisations' => $this->localisationsSelectionnees,
                'assignations' => $this->assignations,
            ], Auth::user());

            session()->flash('success', "L'inventaire {$inventaire->annee} a été créé avec succès. {$this->totalLocalisations} localisation(s) ont été ajoutée(s).");

            // Rediriger vers la page de détail de l'inventaire
            return redirect()->route('inventaires.show', $inventaire);
        } catch (\Exception $e) {
            session()->flash('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la création de l\'inventaire.'));
        }
    }

    /**
     * Annule et redirige vers la liste
     */
    public function cancel()
    {
        return redirect()->route('inventaires.index');
    }

    /**
     * Rendu du composant
     */
    public function render()
    {
        return view('livewire.inventaires.demarrer-inventaire');
    }
}
