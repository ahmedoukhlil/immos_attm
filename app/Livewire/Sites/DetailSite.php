<?php

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Services\EtiquetteService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DetailSite extends Component
{
    public Site $site;

    public function mount(Site $site): void
    {
        $this->site = $site->load([
            'localisation',
            'immobilisations.designation.composants',
            'immobilisations.categorie',
            'immobilisations.etat',
        ]);
    }

    /** @return array{immobilisations: int, composants: int, total: int, nouvelles: int} */
    public function getResumeEtiquettesProperty(): array
    {
        return app(EtiquetteService::class)->resume($this->site);
    }

    public function render()
    {
        return view('livewire.sites.detail-site');
    }
}
