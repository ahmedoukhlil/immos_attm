<?php

namespace App\Livewire\Biens;

use App\Models\AuditLog;
use App\Models\Immobilisation;
use App\Services\EtiquetteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DetailBien extends Component
{
    public Immobilisation $bien;

    public function mount(Immobilisation $bien): void
    {
        $this->bien = $bien->load([
            'designation',
            'categorie',
            'etat',
            'site.localisation',
        ]);
    }

    /**
     * Code affiché : code proposé, sinon identifiant de scan
     */
    public function getCodeFormateProperty(): string
    {
        return $this->bien->code_formate;
    }

    /**
     * Étiquettes réelles du bien (une par composant pour un bien composé),
     * rendues avec le gabarit d'impression au format individuel.
     *
     * @return array<int, string>
     */
    public function getApercusEtiquettesProperty(): array
    {
        $service = app(EtiquetteService::class);
        $configuration = $service->configurationImpression(['page_format' => 'individuelle']);

        if (! $this->bien->site) {
            return [];
        }

        return $service->etiquettesPourBien($this->bien)
            // Pas de barres de défilement dans le cadre d'aperçu (arrondis du rendu en mm).
            ->map(fn (array $etiquette): string => str_replace(
                '</head>',
                '<style>html, body { overflow: hidden; }</style></head>',
                $service->html(collect([$etiquette]), $this->bien->site, $configuration),
            ))
            ->all();
    }

    /**
     * Dix derniers mouvements affichés directement sur la fiche.
     */
    public function getHistoriqueTransfertsProperty()
    {
        return $this->bien->historiqueTransferts()
            ->with('utilisateur')
            ->limit(10)
            ->get();
    }

    /**
     * Dernières opérations sensibles, visibles uniquement par les administrateurs.
     */
    public function getAuditLogsProperty()
    {
        if (! auth()->user()?->isAdmin()) {
            return collect();
        }

        return AuditLog::query()
            ->where('auditable_type', Immobilisation::class)
            ->where('auditable_id', (string) $this->bien->getKey())
            ->with('actor')
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * Archive l'immobilisation
     */
    public function supprimer()
    {
        if (! Auth::user()->isAdmin()) {
            session()->flash('error', 'Vous n\'avez pas les permissions nécessaires pour archiver une immobilisation.');

            return;
        }

        try {
            $this->bien->delete();
            session()->flash('success', 'L\'immobilisation a été archivée avec succès.');

            return $this->redirect(route('biens.index'));
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', 'Erreur lors de l\'archivage.');
        }
    }

    public function render()
    {
        return view('livewire.biens.detail-bien');
    }
}
