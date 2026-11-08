<?php

namespace App\Livewire\Inventaires;

use App\Models\AuditLog;
use App\Models\Inventaire;
use App\Models\Sortie;
use App\Services\SortieService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Report de l'inventaire sur le registre : bilan de ce que la clôture a
 * appliqué, puis biens absents ou hors d'usage à proposer à la sortie.
 */
#[Layout('components.layouts.app')]
class ReportInventaire extends Component
{
    public Inventaire $inventaire;

    /** @var array<int, string> */
    public array $selection = [];

    public string $motif = 'reforme';

    public string $filtreConstat = '';

    public function mount(Inventaire $inventaire): void
    {
        abort_unless(Auth::user()?->canManageOperations(), 403, 'Accès non autorisé.');

        $this->inventaire = $inventaire;
    }

    /**
     * Ce que la clôture a reporté automatiquement (transferts, états, codes).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function bilan(): array
    {
        $cloture = AuditLog::query()
            ->where('auditable_type', Inventaire::class)
            ->where('auditable_id', (string) $this->inventaire->id)
            ->where('event', 'closed')
            ->latest('id')
            ->first();

        return [
            'biens_transferes' => (int) ($cloture?->new_values['biens_transferes'] ?? 0),
            'etats_mis_a_jour' => (int) ($cloture?->new_values['etats_mis_a_jour'] ?? 0),
            'codes_reportes' => (int) ($cloture?->new_values['codes_reportes'] ?? 0),
        ];
    }

    #[Computed]
    public function candidats(): Collection
    {
        if ($this->inventaire->statut !== 'cloture') {
            return collect();
        }

        return app(SortieService::class)->candidats($this->inventaire);
    }

    #[Computed]
    public function sorties(): Collection
    {
        return Sortie::query()
            ->where('inventaire_id', $this->inventaire->id)
            ->withCount('lignes')
            ->latest('id')
            ->get();
    }

    /**
     * Pré-sélectionne les biens d'un même constat et leur motif habituel.
     */
    public function selectionnerConstat(string $constat): void
    {
        $lignes = $this->candidats->filter(fn (array $ligne): bool => $ligne['constat'] === $constat && ! $ligne['sortie_brouillon']);

        $this->selection = $lignes->map(fn (array $ligne): string => (string) $ligne['bien']->id)->values()->all();
        $this->motif = $lignes->first()['motif_propose'] ?? $this->motif;
    }

    public function proposer(SortieService $service)
    {
        $this->validate([
            'selection' => ['required', 'array', 'min:1'],
            'motif' => ['required', Rule::in(array_keys(Sortie::MOTIFS))],
        ], [
            'selection.required' => 'Sélectionnez au moins un bien.',
        ]);

        $sortie = $service->proposer($this->selection, $this->motif, Auth::user(), $this->inventaire);

        session()->flash('success', count($this->selection).' bien(s) inscrit(s) dans la décision '.$sortie->numero.'.');

        return $this->redirectRoute('sorties.show', $sortie, navigate: true);
    }

    public function render()
    {
        $candidats = $this->candidats;

        return view('livewire.inventaires.report-inventaire', [
            'lignes' => $this->filtreConstat === ''
                ? $candidats
                : $candidats->where('constat', $this->filtreConstat)->values(),
            'compteurs' => $candidats->countBy('constat'),
        ]);
    }
}
