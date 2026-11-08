<?php

namespace App\Livewire\Biens;

use App\Livewire\Traits\WithCachedOptions;
use App\Models\HistoriqueTransfert;
use App\Models\Immobilisation;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Transfert groupé d'immobilisations vers un autre site, avec historique.
 */
#[Layout('components.layouts.app')]
class TransfertBien extends Component
{
    use WithCachedOptions;

    public $bienIds = []; // Identifiants sélectionnés

    public $biensSelectionnes = []; // Détails des immobilisations sélectionnées

    public $searchBien = '';

    public $raison = '';

    /**
     * Destination
     */
    public $localisation_id = '';

    public $site_id = '';

    public function mount()
    {
        abort_unless(auth()->user()?->canManageOperations(), 403, 'Accès non autorisé.');
    }

    public function ajouterBien($bienId)
    {
        $bienId = (int) $bienId;

        if (! in_array($bienId, $this->bienIds)) {
            $this->bienIds[] = $bienId;
            $this->chargerBiensSelectionnes();
        }
    }

    public function retirerBien($bienId)
    {
        $this->bienIds = array_values(array_filter($this->bienIds, fn ($id) => $id != $bienId));
        $this->chargerBiensSelectionnes();
    }

    public function updatedBienIds()
    {
        $this->chargerBiensSelectionnes();
    }

    public function chargerBiensSelectionnes()
    {
        $this->biensSelectionnes = Immobilisation::with(['designation', 'site'])
            ->whereIn('id', $this->bienIds)
            ->get()
            ->mapWithKeys(fn (Immobilisation $bien): array => [$bien->id => [
                'id' => $bien->id,
                'code' => $bien->code_formate,
                'designation' => $bien->libelle_article ?? 'N/A',
                'site' => $bien->site?->site ?? 'Sans site',
            ]])
            ->all();
    }

    /**
     * Change de localisation : le site de destination doit lui appartenir.
     */
    public function updatedLocalisationId($value)
    {
        if ($this->site_id && (string) Site::whereKey($this->site_id)->value('localisation_id') !== (string) $value) {
            $this->site_id = '';
        }
    }

    /**
     * Immobilisations proposées à la sélection
     */
    public function getBienOptionsProperty()
    {
        $search = trim((string) $this->searchBien);
        $identifiant = preg_replace('/^GS/i', '', $search);

        $query = Immobilisation::with(['designation', 'site.localisation'])
            ->when($search !== '', function ($query) use ($search, $identifiant): void {
                $query->where(function ($q) use ($search, $identifiant): void {
                    if (ctype_digit($identifiant)) {
                        $q->whereKey((int) $identifiant)
                            ->orWhere('id', 'like', $identifiant.'%');
                    }

                    $q->orWhere('code_propose', 'like', '%'.$search.'%')
                        ->orWhere('descriptif', 'like', '%'.$search.'%')
                        ->orWhereHas('designation', fn ($d) => $d->where('designation', 'like', '%'.$search.'%'))
                        ->orWhereHas('site', fn ($s) => $s->where('site', 'like', '%'.$search.'%')
                            ->orWhere('responsable', 'like', '%'.$search.'%'))
                        ->orWhereHas('site.localisation', fn ($l) => $l->where('localisation', 'like', '%'.$search.'%'));
                });

                if (ctype_digit($identifiant)) {
                    $query->orderByRaw('CASE WHEN immobilisations.id = ? THEN 0 ELSE 1 END', [(int) $identifiant]);
                }
            })
            ->orderBy('id');

        $biens = $query->limit(100)->get();

        // Ajouter les biens déjà sélectionnés s'ils ne sont pas dans les résultats
        if (! empty($this->bienIds)) {
            $biens = $biens->merge(
                Immobilisation::with(['designation', 'site.localisation'])
                    ->whereIn('id', $this->bienIds)
                    ->whereNotIn('id', $biens->pluck('id'))
                    ->get()
            );
        }

        return $biens->map(function (Immobilisation $bien): array {
            $designation = $bien->libelle_article ?? 'N/A';
            $site = $bien->site?->site ?? 'Sans site';
            $localisation = $bien->site?->localisation?->localisation ?? 'N/A';

            return [
                'value' => (string) $bien->id,
                'text' => "{$designation} ({$bien->code_formate}) - {$site} [{$localisation}]",
                'selected' => in_array($bien->id, $this->bienIds),
                'numOrdre' => $bien->id,
                'code' => $bien->code_formate,
                'designation' => $designation,
                'site' => $site,
                'responsable' => $bien->site?->responsable,
                'localisation' => $localisation,
            ];
        })->toArray();
    }

    public function getLocalisationOptionsProperty()
    {
        return $this->getCachedLocalisationOptions();
    }

    public function getSiteOptionsProperty()
    {
        return $this->getCachedSiteOptions($this->localisation_id !== '' ? (int) $this->localisation_id : null);
    }

    protected function rules()
    {
        return [
            'bienIds' => 'required|array|min:1',
            'bienIds.*' => 'required|exists:immobilisations,id',
            'site_id' => 'required|exists:sites,id',
            'raison' => 'nullable|string|max:500',
        ];
    }

    protected function messages()
    {
        return [
            'bienIds.required' => 'Veuillez sélectionner au moins une immobilisation.',
            'bienIds.min' => 'Veuillez sélectionner au moins une immobilisation.',
            'site_id.required' => 'Le site de destination est obligatoire.',
            'site_id.exists' => 'Le site de destination n\'existe pas.',
        ];
    }

    /**
     * Effectue le transfert groupé et l'enregistre dans l'historique
     */
    public function transferer()
    {
        abort_unless(auth()->user()?->canManageOperations(), 403);

        $validated = $this->validate();
        $site = Site::with('localisation')->findOrFail($validated['site_id']);
        $groupeId = 'TRF-'.date('YmdHis').'-'.Str::random(6);

        try {
            [$reussis, $echoues] = DB::transaction(function () use ($validated, $site, $groupeId): array {
                $reussis = 0;
                $echoues = 0;

                $biens = Immobilisation::with('site.localisation')
                    ->whereIn('id', $validated['bienIds'])
                    ->lockForUpdate()
                    ->get();

                $echoues += count($validated['bienIds']) - $biens->count();

                foreach ($biens as $bien) {
                    if ((int) $bien->site_id === (int) $site->id) {
                        $echoues++;

                        continue;
                    }

                    $ancienSite = $bien->site;
                    $bien->update(['site_id' => $site->id]);

                    HistoriqueTransfert::create([
                        'immobilisation_id' => $bien->id,
                        'ancien_site_id' => $ancienSite?->id,
                        'nouveau_site_id' => $site->id,
                        'ancien_site_libelle' => $ancienSite?->site ?? 'Sans site',
                        'nouveau_site_libelle' => $site->site,
                        'ancien_localisation_libelle' => $ancienSite?->localisation?->localisation ?? 'N/A',
                        'nouveau_localisation_libelle' => $site->localisation?->localisation ?? 'N/A',
                        'transfert_par' => auth()->user()->idUser,
                        'date_transfert' => now(),
                        'raison' => $validated['raison'] ?? null,
                        'groupe_transfert_id' => $groupeId,
                    ]);

                    $reussis++;
                }

                return [$reussis, $echoues];
            });
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'Erreur lors du transfert.');

            return;
        }

        $message = "Transfert effectué : {$reussis} immobilisation(s) transférée(s) vers « {$site->site} ».";
        if ($echoues > 0) {
            $message .= " {$echoues} transfert(s) ignoré(s) (déjà sur ce site ou introuvable).";
        }
        session()->flash('success', $message);

        $this->reset(['bienIds', 'biensSelectionnes', 'localisation_id', 'site_id', 'searchBien', 'raison']);
    }

    public function cancel()
    {
        return redirect()->route('biens.index');
    }

    public function render()
    {
        return view('livewire.biens.transfert-bien');
    }
}
