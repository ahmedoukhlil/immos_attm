<?php

namespace App\Livewire\Designations;

use App\Models\Categorie;
use App\Models\Designation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]

class FormDesignation extends Component
{
    /**
     * Instance de la désignation (null si création)
     */
    public $designation = null;

    /**
     * ID de la désignation pour l'édition
     */
    public $designationId = null;

    /**
     * Propriétés du formulaire
     */
    public $designation_name = '';

    public $code = '';

    public $categorie_id = '';

    public bool $estCompose = false;

    /** @var array<int, array{nom: string, code: string, quantite: int, obligatoire: bool}> */
    public array $composants = [];

    /**
     * Initialisation du composant
     *
     * @param  Designation|int|string|null  $designation  Instance de la désignation pour l'édition, ID, ou null pour la création
     */
    public function mount($designation = null): void
    {
        if ($designation) {
            // Si c'est un ID (string ou int) et pas une instance de Designation, charger la désignation
            if (! ($designation instanceof Designation)) {
                if (is_numeric($designation) || (is_string($designation) && ctype_digit($designation))) {
                    try {
                        $designation = Designation::findOrFail($designation);
                    } catch (\Exception $e) {
                        // Si la désignation n'existe pas, traiter comme création
                        $designation = null;
                    }
                } else {
                    // Si ce n'est ni un ID ni une instance, traiter comme création
                    $designation = null;
                }
            }

            // Si on a une instance valide de Designation
            if ($designation instanceof Designation) {
                // Mode édition : charger les valeurs
                $this->designation = $designation;
                $this->designationId = $designation->id;
                $this->designation_name = $designation->designation;
                $this->code = $designation->code ?? '';
                $this->categorie_id = $designation->categorie_id;
                $this->estCompose = (bool) $designation->est_compose;
                $this->composants = $designation->composants()
                    ->get()
                    ->map(fn ($composant): array => [
                        'nom' => $composant->nom,
                        'code' => $composant->code,
                        'quantite' => $composant->quantite,
                        'obligatoire' => $composant->obligatoire,
                    ])->all();
            }
        }
    }

    /**
     * Propriété calculée : Retourne toutes les catégories
     */
    public function getCategoriesProperty()
    {
        return Categorie::orderBy('categorie')->get();
    }

    /**
     * Options pour SearchableSelect : Catégories
     */
    public function getCategorieOptionsProperty()
    {
        return Categorie::orderBy('categorie')
            ->get()
            ->map(function ($categorie) {
                return [
                    'value' => (string) $categorie->id,
                    'text' => $categorie->categorie,
                ];
            })
            ->toArray();
    }

    /**
     * Génère une suggestion de code basée sur le nom
     */
    public function generateCodeSuggestion()
    {
        if (empty($this->designation_name)) {
            return;
        }

        // Générer un code basé sur les premières lettres du nom
        $words = explode(' ', strtoupper($this->designation_name));
        $code = '';

        foreach ($words as $word) {
            if (strlen($word) > 0) {
                $code .= substr($word, 0, 1);
            }
        }

        // Limiter à 10 caractères
        $code = substr($code, 0, 10);

        // Vérifier si le code existe déjà
        $existing = Designation::where('code', $code)
            ->where('id', '!=', $this->designationId)
            ->exists();

        if ($existing) {
            // Ajouter un numéro si le code existe
            $counter = 1;
            while (Designation::where('code', $code.$counter)
                ->where('id', '!=', $this->designationId)
                ->exists()) {
                $counter++;
            }
            $code .= $counter;
        }

        $this->code = $code;
    }

    /**
     * Propriété calculée : Vérifie si on est en mode édition
     */
    public function getIsEditProperty(): bool
    {
        return $this->designation !== null;
    }

    /**
     * Règles de validation
     */
    protected function rules(): array
    {
        return [
            'designation_name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20|unique:designations,code,'.($this->designationId ?? 'NULL').',id',
            'categorie_id' => 'required|exists:categories,id',
            'estCompose' => 'boolean',
            'composants' => $this->estCompose ? 'required|array|min:1' : 'array',
            'composants.*.nom' => 'required_if:estCompose,true|string|max:100',
            'composants.*.code' => ['required_if:estCompose,true', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', 'distinct:ignore_case'],
            'composants.*.quantite' => 'required_if:estCompose,true|integer|min:1|max:20',
            'composants.*.obligatoire' => 'boolean',
        ];
    }

    /**
     * Messages de validation personnalisés
     */
    protected function messages(): array
    {
        return [
            'designation_name.required' => 'Le nom de la désignation est obligatoire.',
            'designation_name.max' => 'Le nom de la désignation ne peut pas dépasser 255 caractères.',
            'code.max' => 'Le code ne peut pas dépasser 20 caractères.',
            'code.unique' => 'Ce code est déjà utilisé par une autre désignation.',
            'categorie_id.required' => 'La catégorie est obligatoire.',
            'categorie_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'composants.required' => 'Ajoutez au moins un composant pour un matériel composé.',
            'composants.*.nom.required_if' => 'Le nom de chaque composant est obligatoire.',
            'composants.*.code.required_if' => 'Le code de chaque composant est obligatoire.',
            'composants.*.code.regex' => 'Le code d’un composant ne peut contenir que des lettres et des chiffres.',
            'composants.*.code.distinct' => 'Chaque composant doit avoir un code unique.',
            'composants.*.quantite.min' => 'La quantité doit être au moins égale à 1.',
            'composants.*.quantite.max' => 'La quantité ne peut pas dépasser 20.',
        ];
    }

    public function updatedEstCompose(bool $estCompose): void
    {
        if ($estCompose && $this->composants === []) {
            $this->ajouterComposant();
        }
    }

    public function ajouterComposant(): void
    {
        $this->composants[] = [
            'nom' => '',
            'code' => '',
            'quantite' => 1,
            'obligatoire' => true,
        ];
    }

    public function retirerComposant(int $index): void
    {
        unset($this->composants[$index]);
        $this->composants = array_values($this->composants);
    }

    /**
     * Sauvegarde la désignation (création ou édition)
     */
    public function save()
    {
        abort_unless(auth()->user()?->canManageOperations(), 403);

        // Valider les données
        $validated = $this->validate();

        try {
            $message = '';
            DB::transaction(function () use ($validated, &$message): void {
                $donnees = [
                    'designation' => $validated['designation_name'],
                    'code' => $validated['code'] ?: null,
                    'categorie_id' => $validated['categorie_id'],
                    'est_compose' => $validated['estCompose'],
                ];

                if ($this->isEdit) {
                    $this->designation->update($donnees);
                    $message = 'Désignation modifiée avec succès';
                } else {
                    $this->designation = Designation::create($donnees);
                    $message = 'Désignation créée avec succès';
                }

                $this->designation->composants()->delete();

                if ($validated['estCompose']) {
                    foreach ($validated['composants'] as $ordre => $composant) {
                        $this->designation->composants()->create([
                            'nom' => trim($composant['nom']),
                            'code' => strtoupper(trim($composant['code'])),
                            'quantite' => (int) $composant['quantite'],
                            'obligatoire' => (bool) $composant['obligatoire'],
                            'ordre' => $ordre,
                        ]);
                    }
                }
            });

            session()->flash('success', $message);

            // Rediriger vers la liste
            return redirect()->route('designations.index');
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'Une erreur est survenue lors de la sauvegarde.');
        }
    }

    /**
     * Annule et redirige vers la liste
     */
    public function cancel()
    {
        return redirect()->route('designations.index');
    }

    /**
     * Rendu du composant
     */
    public function render()
    {
        return view('livewire.designations.form-designation');
    }
}
