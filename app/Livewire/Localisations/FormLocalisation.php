<?php

namespace App\Livewire\Localisations;

use App\Livewire\Traits\WithCachedOptions;
use App\Models\Localisation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FormLocalisation extends Component
{
    use WithCachedOptions;

    /**
     * Localisation en cours d'édition (null si création)
     */
    public ?Localisation $localisationModel = null;

    #[Locked]
    public $localisationId = null;

    public $localisation = '';

    public $code = '';

    public function mount(?Localisation $localisation = null): void
    {
        if ($localisation?->exists) {
            $this->localisationModel = $localisation;
            $this->localisationId = $localisation->id;
            $this->localisation = $localisation->localisation;
            $this->code = $localisation->code ?? '';
        }
    }

    /**
     * Génère une suggestion de code à partir du nom
     */
    public function generateCodeSuggestion(): void
    {
        $base = mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', Str::ascii((string) $this->localisation)), 0, 5)) ?: 'LOC';
        $code = $base;

        for ($suffixe = 2; Localisation::where('code', $code)->whereKeyNot($this->localisationId)->exists(); $suffixe++) {
            $code = $base.$suffixe;
        }

        $this->code = $code;
    }

    public function getIsEditProperty(): bool
    {
        return $this->localisationModel !== null;
    }

    protected function rules(): array
    {
        return [
            'localisation' => 'required|string|max:150',
            'code' => ['nullable', 'string', 'max:30', Rule::unique('localisations', 'code')->ignore($this->localisationId)],
        ];
    }

    protected function messages(): array
    {
        return [
            'localisation.required' => 'La localisation est obligatoire.',
            'localisation.max' => 'La localisation ne peut pas dépasser 150 caractères.',
            'code.max' => 'Le code ne peut pas dépasser 30 caractères.',
            'code.unique' => 'Ce code est déjà utilisé.',
        ];
    }

    public function save()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->localisation = trim((string) $this->localisation);
        $this->code = trim((string) $this->code);

        $validated = $this->validate();
        $data = [
            'localisation' => $validated['localisation'],
            'code' => $validated['code'] ?: null,
        ];

        try {
            if ($this->isEdit) {
                $this->localisationModel->update($data);
                $message = 'Localisation modifiée avec succès';
            } else {
                Localisation::create($data);
                $message = 'Localisation créée avec succès';
            }
        } catch (\Exception $e) {
            Log::error('Erreur sauvegarde localisation', ['localisation' => $this->localisation]);
            report($e);
            session()->flash('error', 'Une erreur est survenue lors de la sauvegarde.');

            return;
        }

        $this->invalidateLocalisationCache();
        Cache::forget('localisations_total_count');
        session()->flash('success', $message);

        return $this->redirect(route('localisations.index'), navigate: true);
    }

    public function cancel()
    {
        return $this->redirect(route('localisations.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.localisations.form-localisation');
    }
}
