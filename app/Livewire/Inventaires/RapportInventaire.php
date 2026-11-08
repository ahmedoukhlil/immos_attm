<?php

namespace App\Livewire\Inventaires;

use App\Models\Inventaire;
use App\Models\InventaireExportRequest;
use App\Services\InventaireService;
use App\Services\RapportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RapportInventaire extends Component
{
    /**
     * Instance de l'inventaire
     */
    public Inventaire $inventaire;

    /**
     * Onglet actif
     */
    public $activeTab = 'resume';

    /**
     * Filtres pour les tableaux
     */
    public $filterEmplacement = 'all';

    public $filterStatut = 'all';

    public $showPhotos = false;

    /**
     * Initialisation du composant
     */
    public function mount(Inventaire $inventaire): void
    {
        abort_unless(Auth::user() && $inventaire->isAccessibleTo(Auth::user()), 403);

        // Vérifier que l'inventaire est terminé ou clôturé
        if (! in_array($inventaire->statut, ['termine', 'cloture'])) {
            session()->flash('error', 'Le rapport n\'est disponible que pour les inventaires terminés ou clôturés.');
            redirect()->route('inventaires.show', $inventaire);

            return;
        }

        // Eager load des relations nécessaires
        $this->inventaire = $inventaire->load([
            'creator',
            'closer',
            'inventaireLocalisations.localisation',
            'inventaireLocalisations.agent',
            'inventaireScans.bien.designation',
            'inventaireScans.bien.categorie',
            'inventaireScans.bien.site.localisation',
            'inventaireScans.localisationReelle',
            'inventaireScans.agent',
        ]);
    }

    /**
     * Propriété calculée : Retourne les statistiques complètes
     */
    public function getStatistiquesProperty(): array
    {
        $service = app(InventaireService::class);

        return $service->calculerStatistiques($this->inventaire);
    }

    /**
     * Propriété calculée : Détail des immobilisations par site
     */
    public function getDetailParSiteProperty(): array
    {
        return app(RapportService::class)->getDetailParSite($this->inventaire);
    }

    public function getExportsProperty()
    {
        return InventaireExportRequest::query()
            ->where('inventaire_id', $this->inventaire->id)
            ->when(! Auth::user()?->isAdmin(), fn ($query) => $query->where('requested_by', Auth::id()))
            ->latest('id')
            ->limit(6)
            ->get();
    }

    /**
     * Propriété calculée : Retourne les biens présents
     */
    public function getBiensPresentsProperty()
    {
        $query = $this->inventaire->inventaireScans()
            ->where('statut_scan', 'present')
            ->with(['bien.designation', 'bien.site.localisation', 'agent', 'inventaire.biensAttendus']);

        if ($this->filterEmplacement !== 'all') {
            $query->where('site_reel_id', $this->filterEmplacement);
        }

        return $query->orderBy('date_scan', 'desc')->get();
    }

    /**
     * Propriété calculée : Retourne les biens déplacés
     */
    public function getBiensDeplacesProperty()
    {
        $query = $this->inventaire->inventaireScans()
            ->where('statut_scan', 'deplace')
            ->with(['bien.site.localisation', 'bien.designation', 'localisationReelle', 'agent', 'inventaire.biensAttendus']);

        if ($this->filterEmplacement !== 'all') {
            $query->where('site_reel_id', $this->filterEmplacement);
        }

        return $query->orderBy('date_scan', 'desc')->get();
    }

    /**
     * Propriété calculée : Retourne les biens absents
     */
    public function getBiensAbsentsProperty()
    {
        $query = $this->inventaire->inventaireScans()
            ->where('statut_scan', 'absent')
            ->with(['bien.site.localisation', 'bien.designation', 'agent', 'inventaire.biensAttendus']);

        if ($this->filterEmplacement !== 'all') {
            $query->whereHas('bien', fn ($q) => $q->where('site_id', $this->filterEmplacement));
        }

        return $query->orderBy('date_scan', 'desc')->get();
    }

    /**
     * Propriété calculée : Retourne les biens détériorés
     */
    public function getBiensDeterioresProperty()
    {
        return $this->inventaire->inventaireScans()
            ->where('etat_constate', 'defectueux')
            ->where('statut_scan', '!=', 'absent')
            ->with(['bien.designation', 'bien.site.localisation', 'agent', 'inventaire.biensAttendus'])
            ->orderBy('date_scan', 'desc')
            ->get();
    }

    /**
     * Propriété calculée : Retourne les biens défectueux (etat_constate = defectueux, signalés via PWA)
     */
    public function getBiensDefectueuxProperty()
    {
        $query = $this->inventaire->inventaireScans()
            ->where('etat_constate', 'defectueux')
            ->with(['bien.designation', 'bien.site.localisation', 'localisationReelle', 'agent', 'inventaire.biensAttendus']);

        if ($this->filterEmplacement !== 'all') {
            $query->where('site_reel_id', $this->filterEmplacement);
        }

        return $query->orderBy('date_scan', 'desc')->get();
    }

    /**
     * Propriété calculée : Retourne les biens non scannés
     */
    public function getBiensNonScannesProperty()
    {
        return app(RapportService::class)->getBiensNonScannes($this->inventaire);
    }

    /**
     * Propriété calculée : Retourne les anomalies détectées
     */
    public function getAnomaliesProperty(): array
    {
        $service = app(InventaireService::class);

        return $service->detecterAnomalies($this->inventaire);
    }

    /**
     * Change l'onglet actif
     */
    public function setActiveTab($tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Ouvre le dialogue d'impression
     */
    public function imprimerRapport(): void
    {
        $this->dispatch('print-report');
    }

    /**
     * Rendu du composant
     */
    public function render()
    {
        return view('livewire.inventaires.rapport-inventaire');
    }
}
