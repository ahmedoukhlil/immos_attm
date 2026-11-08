<?php

namespace App\Livewire\Categories;

use App\Models\Categorie;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Catégorie d'immobilisation et son imputation comptable (feuille « Code compta »).
 */
#[Layout('components.layouts.app')]
class FormCategorie extends Component
{
    public ?Categorie $categorieModel = null;

    #[Locked]
    public $categorieId = null;

    public $code = '';

    public $categorie = '';

    public $imputation_comptable = '';

    public function mount(?Categorie $categorie = null): void
    {
        if ($categorie?->exists) {
            $this->categorieModel = $categorie;
            $this->categorieId = $categorie->id;
            $this->code = $categorie->code;
            $this->categorie = $categorie->categorie;
            $this->imputation_comptable = $categorie->imputation_comptable ?? '';
        }
    }

    public function getIsEditProperty(): bool
    {
        return $this->categorieModel !== null;
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('categories', 'code')->ignore($this->categorieId)],
            'categorie' => 'required|string|max:150',
            'imputation_comptable' => [
                'nullable', 'digits:8',
                Rule::unique('categories', 'imputation_comptable')->ignore($this->categorieId),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required' => 'Le code est obligatoire.',
            'code.max' => 'Le code ne peut pas dépasser 10 caractères.',
            'code.unique' => 'Ce code est déjà utilisé par une autre catégorie.',
            'categorie.required' => 'Le libellé est obligatoire.',
            'categorie.max' => 'Le libellé ne peut pas dépasser 150 caractères.',
            'imputation_comptable.digits' => 'L\'imputation comptable doit comporter 8 chiffres (ex : 21800000).',
            'imputation_comptable.unique' => 'Cette imputation comptable est déjà associée à une autre catégorie.',
        ];
    }

    public function save()
    {
        abort_unless(auth()->user()?->canManageOperations(), 403);

        $validated = $this->validate();
        $data = [
            'code' => strtoupper($validated['code']),
            'categorie' => $validated['categorie'],
            'imputation_comptable' => $validated['imputation_comptable'] ?: null,
        ];

        try {
            if ($this->isEdit) {
                $this->categorieModel->update($data);
                session()->flash('success', 'Catégorie modifiée avec succès.');
            } else {
                Categorie::create($data);
                session()->flash('success', 'Catégorie créée avec succès.');
            }
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'Une erreur est survenue lors de la sauvegarde.');

            return;
        }

        return redirect()->route('categories.index');
    }

    public function cancel()
    {
        return redirect()->route('categories.index');
    }

    public function render()
    {
        return view('livewire.categories.form-categorie');
    }
}
