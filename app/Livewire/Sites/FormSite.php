<?php

namespace App\Livewire\Sites;

use App\Livewire\Traits\WithCachedOptions;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FormSite extends Component
{
    use WithCachedOptions;

    /**
     * Site en cours d'édition (null si création)
     */
    public ?Site $siteModel = null;

    #[Locked]
    public $siteId = null;

    /**
     * Propriétés du formulaire (colonnes « Site » et « Responsable » du fichier Excel)
     */
    public $site = '';

    public $code = '';

    public $responsable = '';

    public $localisation_id = '';

    public function mount(?Site $site = null): void
    {
        if ($site?->exists) {
            $this->siteModel = $site;
            $this->siteId = $site->id;
            $this->site = $site->site;
            $this->code = $site->code ?? '';
            $this->responsable = $site->responsable ?? '';
            $this->localisation_id = (string) $site->localisation_id;
        }
    }

    /**
     * Options pour SearchableSelect (localisations)
     */
    public function getLocalisationOptionsProperty(): array
    {
        return $this->getCachedLocalisationOptions();
    }

    /**
     * Génère une suggestion de code à partir des initiales du site
     */
    public function generateCodeSuggestion(): void
    {
        $initiales = collect(preg_split('/\s+/', trim((string) $this->site)))
            ->filter()
            ->map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
            ->implode('');
        $prefixe = mb_substr($initiales, 0, 3) ?: 'SIT';

        $this->code = $prefixe.'-'.str_pad((string) (Site::max('id') + 1), 3, '0', STR_PAD_LEFT);
    }

    public function getIsEditProperty(): bool
    {
        return $this->siteModel !== null;
    }

    protected function rules(): array
    {
        return [
            'site' => [
                'required', 'string', 'max:150',
                Rule::unique('sites', 'site')
                    ->where('localisation_id', $this->localisation_id)
                    ->ignore($this->siteId),
            ],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('sites', 'code')->ignore($this->siteId)],
            'responsable' => 'nullable|string|max:150',
            'localisation_id' => 'required|exists:localisations,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'site.required' => 'Le site est obligatoire.',
            'site.max' => 'Le site ne peut pas dépasser 150 caractères.',
            'site.unique' => 'Ce site existe déjà dans cette localisation.',
            'code.max' => 'Le code ne peut pas dépasser 30 caractères.',
            'code.unique' => 'Ce code est déjà utilisé.',
            'responsable.max' => 'Le responsable ne peut pas dépasser 150 caractères.',
            'localisation_id.required' => 'La localisation est obligatoire.',
            'localisation_id.exists' => 'La localisation sélectionnée n\'existe pas.',
        ];
    }

    public function save()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->site = trim((string) $this->site);
        $this->code = trim((string) $this->code);
        $this->responsable = trim((string) $this->responsable);

        $validated = $this->validate();
        $data = [
            'site' => $validated['site'],
            'code' => $validated['code'] ?: null,
            'responsable' => $validated['responsable'] ?: null,
            'localisation_id' => $validated['localisation_id'],
        ];

        try {
            if ($this->isEdit) {
                $this->siteModel->update($data);
                $message = 'Site modifié avec succès';
            } else {
                Site::create($data);
                $message = 'Site créé avec succès';
            }
        } catch (\Exception $e) {
            Log::error('Erreur sauvegarde site', ['site' => $this->site]);
            report($e);
            session()->flash('error', 'Une erreur est survenue lors de la sauvegarde.');

            return;
        }

        Cache::forget('sites_total_count');
        session()->flash('success', $message);

        return $this->redirect(route('sites.index'), navigate: true);
    }

    public function cancel()
    {
        return $this->redirect(route('sites.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.sites.form-site');
    }
}
