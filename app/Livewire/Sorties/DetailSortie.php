<?php

namespace App\Livewire\Sorties;

use App\Models\Sortie;
use App\Services\SortieService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Décision de sortie : en brouillon on la complète (n°, date, biens) ;
 * la validation retire les biens du registre actif et fige le procès-verbal.
 */
#[Layout('components.layouts.app')]
class DetailSortie extends Component
{
    public Sortie $sortie;

    public string $numero = '';

    public string $date_decision = '';

    public string $motif = '';

    public string $observations = '';

    public string $codeAjout = '';

    public function mount(Sortie $sortie): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403, 'Accès non autorisé.');

        $this->sortie = $sortie;
        $this->numero = $sortie->numero;
        $this->date_decision = $sortie->date_decision?->format('Y-m-d') ?? '';
        $this->motif = $sortie->motif;
        $this->observations = (string) $sortie->observations;
    }

    public function enregistrer(): void
    {
        abort_unless($this->sortie->estBrouillon(), 403);

        $donnees = $this->validate([
            'numero' => ['required', 'string', 'max:50', Rule::unique('sorties', 'numero')->ignore($this->sortie->id)],
            'date_decision' => ['nullable', 'date'],
            'motif' => ['required', Rule::in(array_keys(Sortie::MOTIFS))],
            'observations' => ['nullable', 'string', 'max:2000'],
        ], [
            'numero.unique' => 'Ce numéro de décision est déjà utilisé.',
        ]);

        $this->sortie->update([
            ...$donnees,
            'date_decision' => $donnees['date_decision'] ?: null,
            'observations' => $donnees['observations'] ?: null,
        ]);

        $this->dispatch('toast', message: 'Décision enregistrée.', type: 'success');
    }

    public function ajouterParCode(SortieService $service): void
    {
        $this->validate(['codeAjout' => ['required', 'string', 'max:60']], ['codeAjout.required' => 'Saisissez un code.']);

        $bien = $service->trouverBien($this->codeAjout);

        if (! $bien) {
            $this->addError('codeAjout', 'Aucune immobilisation en service ne porte ce code.');

            return;
        }

        $service->ajouterBiens($this->sortie, [$bien->id]);
        $this->codeAjout = '';
        $this->dispatch('toast', message: "{$bien->code_formate} ajouté à la décision.", type: 'success');
    }

    public function retirer(int $ligneId, SortieService $service): void
    {
        $service->retirerBien($this->sortie, $ligneId);
    }

    public function valider(SortieService $service): void
    {
        $this->enregistrer();
        $service->valider($this->sortie, Auth::user());
        $this->sortie->refresh();

        session()->flash('success', 'Décision validée : les biens sont sortis du registre actif.');
    }

    public function annuler(SortieService $service): void
    {
        $service->annuler($this->sortie, Auth::user());
        $this->sortie->refresh();

        session()->flash('success', 'Décision annulée : les biens restent au registre.');
    }

    public function render()
    {
        return view('livewire.sorties.detail-sortie', [
            'lignes' => $this->sortie->lignes()->get(),
        ]);
    }
}
