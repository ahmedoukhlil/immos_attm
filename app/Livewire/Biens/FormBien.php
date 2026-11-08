<?php

namespace App\Livewire\Biens;

use App\Livewire\Traits\WithCachedOptions;
use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Etat;
use App\Models\Immobilisation;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Formulaire d'immobilisation calqué sur les colonnes du fichier « Inventaire Immo ».
 */
#[Layout('components.layouts.app')]
class FormBien extends Component
{
    use WithCachedOptions;

    public ?Immobilisation $bien = null;

    #[Locked]
    public $bienId = null;

    /** Force le remontage des listes de choix après un import Excel. */
    public int $referencesVersion = 0;

    public $localisation_id = ''; // Filtre des sites

    public $site_id = '';

    public $categorie_id = '';

    public $designation_id = '';

    public $descriptif = '';

    public $marque = '';

    public $modele = '';

    public $numero_chassis = '';

    public $numero_attm = '';

    public $code_immo = '';

    public $etat_id = '';

    public $code_dernier_inventaire = '';

    public $code_premier_inventaire = '';

    public $annee_acquisition = '';

    public $autres_informations = '';

    public $quantite = 1;

    public function mount(?Immobilisation $bien = null): void
    {
        if ($bien?->exists) {
            $this->bien = $bien;
            $this->bienId = $bien->id;
            $this->site_id = (string) $bien->site_id;
            $this->localisation_id = (string) ($bien->site?->localisation_id ?? '');
            $this->categorie_id = (string) $bien->categorie_id;
            $this->designation_id = (string) $bien->designation_id;
            $this->descriptif = $bien->descriptif ?? '';
            $this->marque = $bien->marque ?? '';
            $this->modele = $bien->modele ?? '';
            $this->numero_chassis = $bien->numero_chassis ?? '';
            $this->numero_attm = $bien->numero_attm ?? '';
            $this->code_immo = $bien->code_immo ?? '';
            $this->etat_id = (string) $bien->etat_id;
            $this->code_dernier_inventaire = $bien->code_dernier_inventaire ?? '';
            $this->code_premier_inventaire = $bien->code_premier_inventaire ?? '';
            $this->annee_acquisition = (string) ($bien->annee_acquisition ?? '');
            $this->autres_informations = $bien->autres_informations ?? '';

            return;
        }

        // Pré-sélection de l'état le plus fréquent pour accélérer la saisie
        $this->etat_id = (string) (Etat::where('code', 'BON')->value('id') ?? '');
        $this->annee_acquisition = (string) now()->year;
    }

    /**
     * La catégorie suit celle de la désignation choisie.
     */
    public function updatedDesignationId($value): void
    {
        $categorieId = $value ? Designation::whereKey($value)->value('categorie_id') : null;
        $this->categorie_id = $categorieId ? (string) $categorieId : '';
    }

    /**
     * Change de localisation : le site doit lui appartenir.
     */
    public function updatedLocalisationId($value): void
    {
        if ($this->site_id && (string) Site::whereKey($this->site_id)->value('localisation_id') !== (string) $value) {
            $this->site_id = '';
        }
    }

    #[On('referentiels-importes')]
    public function rafraichirReferentiels(): void
    {
        // Les modèles invalident déjà le cache partagé via leur observer.
        // Une nouvelle clé Livewire est néanmoins nécessaire, car chaque
        // SearchableSelect conserve sa copie des options depuis son montage.
        $this->referencesVersion++;
    }

    public function getDesignationOptionsProperty(): array
    {
        return Designation::with('categorie:id,categorie')
            ->orderBy('designation')
            ->get()
            ->map(fn (Designation $designation): array => [
                'value' => (string) $designation->id,
                'text' => $designation->designation.($designation->categorie ? ' ('.$designation->categorie->categorie.')' : ''),
            ])
            ->toArray();
    }

    public function getCategorieOptionsProperty(): array
    {
        return Categorie::orderBy('categorie')
            ->get()
            ->map(fn (Categorie $categorie): array => [
                'value' => (string) $categorie->id,
                'text' => $categorie->categorie.($categorie->imputation_comptable ? ' — '.$categorie->imputation_comptable : ''),
            ])
            ->toArray();
    }

    public function getEtatOptionsProperty(): array
    {
        return Etat::orderBy('id')
            ->get()
            ->map(fn (Etat $etat): array => [
                'value' => (string) $etat->id,
                'text' => $etat->etat,
            ])
            ->toArray();
    }

    public function getLocalisationOptionsProperty(): array
    {
        return $this->getCachedLocalisationOptions();
    }

    public function getSiteOptionsProperty(): array
    {
        return $this->getCachedSiteOptions($this->localisation_id !== '' ? (int) $this->localisation_id : null);
    }

    /**
     * Responsable du site sélectionné, affiché à titre d'information.
     */
    public function getResponsableProperty(): ?string
    {
        return $this->site_id ? Site::whereKey($this->site_id)->value('responsable') : null;
    }

    public function getIsEditProperty(): bool
    {
        return $this->bien !== null;
    }

    protected function rules(): array
    {
        $rules = [
            'site_id' => 'required|exists:sites,id',
            'categorie_id' => 'required|exists:categories,id',
            'designation_id' => 'required|exists:designations,id',
            'descriptif' => 'nullable|string|max:255',
            'marque' => 'nullable|string|max:100',
            'modele' => 'nullable|string|max:100',
            'numero_chassis' => 'nullable|string|max:100',
            'numero_attm' => 'nullable|string|max:100',
            'code_immo' => 'nullable|string|max:100',
            'etat_id' => 'required|exists:etats,id',
            'code_dernier_inventaire' => 'nullable|string|max:40',
            'code_premier_inventaire' => 'nullable|string|max:40',
            'annee_acquisition' => 'required|integer|min:1950|max:'.((int) now()->year + 1),
            'autres_informations' => 'nullable|string|max:2000',
        ];

        if (! $this->isEdit) {
            $rules['quantite'] = 'required|integer|min:1|max:1000';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'site_id.required' => 'Le site est obligatoire.',
            'site_id.exists' => 'Le site sélectionné n\'existe pas.',
            'categorie_id.required' => 'La catégorie est obligatoire.',
            'categorie_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'designation_id.required' => 'La désignation est obligatoire.',
            'designation_id.exists' => 'La désignation sélectionnée n\'existe pas.',
            'descriptif.max' => 'Le descriptif ne peut pas dépasser 255 caractères.',
            'marque.max' => 'La marque ne peut pas dépasser 100 caractères.',
            'modele.max' => 'Le modèle ne peut pas dépasser 100 caractères.',
            'numero_chassis.max' => 'Le numéro de châssis ne peut pas dépasser 100 caractères.',
            'numero_attm.max' => 'Le numéro ATTM ne peut pas dépasser 100 caractères.',
            'code_immo.max' => 'Le code immo ne peut pas dépasser 100 caractères.',
            'etat_id.required' => 'L\'état est obligatoire.',
            'etat_id.exists' => 'L\'état sélectionné n\'existe pas.',
            'code_dernier_inventaire.max' => 'Le code ne peut pas dépasser 40 caractères.',
            'code_premier_inventaire.max' => 'Le code ne peut pas dépasser 40 caractères.',
            'quantite.required' => 'La quantité est obligatoire.',
            'quantite.integer' => 'La quantité doit être un nombre entier.',
            'quantite.min' => 'La quantité doit être au moins de 1.',
            'quantite.max' => 'La quantité ne peut pas dépasser 1000.',
        ];
    }

    public function save()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $this->validate();
        $donnees = [
            'site_id' => $validated['site_id'],
            'categorie_id' => $validated['categorie_id'],
            'designation_id' => $validated['designation_id'],
            'descriptif' => trim((string) $validated['descriptif']) ?: null,
            'marque' => trim((string) $validated['marque']) ?: null,
            'modele' => trim((string) $validated['modele']) ?: null,
            'numero_chassis' => trim((string) $validated['numero_chassis']) ?: null,
            'numero_attm' => trim((string) $validated['numero_attm']) ?: null,
            'code_immo' => trim((string) $validated['code_immo']) ?: null,
            'etat_id' => $validated['etat_id'],
            'code_dernier_inventaire' => trim((string) $validated['code_dernier_inventaire']) ?: null,
            'code_premier_inventaire' => trim((string) $validated['code_premier_inventaire']) ?: null,
            'annee_acquisition' => (int) $validated['annee_acquisition'],
            'autres_informations' => trim((string) $validated['autres_informations']) ?: null,
        ];

        if ($this->isEdit) {
            $this->bien->update($donnees);
            session()->flash('success', 'Immobilisation modifiée avec succès');

            return redirect()->route('biens.show', $this->bien);
        }

        $quantite = (int) $validated['quantite'];

        $bien = DB::transaction(function () use ($donnees, $quantite): Immobilisation {
            $dernier = null;

            for ($i = 0; $i < $quantite; $i++) {
                $dernier = Immobilisation::create($donnees);
            }

            return $dernier;
        });

        session()->flash('success', $quantite > 1
            ? $quantite.' immobilisations créées avec succès'
            : 'Immobilisation créée avec succès');

        return redirect()->route('biens.show', $bien);
    }

    public function cancel()
    {
        return redirect()->route('biens.index');
    }

    public function render()
    {
        return view('livewire.biens.form-bien');
    }
}
