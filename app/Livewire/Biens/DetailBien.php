<?php

namespace App\Livewire\Biens;

use App\Models\AuditLog;
use App\Models\Immobilisation;
use App\Services\EtiquetteService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class DetailBien extends Component
{
    use WithFileUploads;

    public Immobilisation $bien;

    public $nouvellePhoto;

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
        if (! auth()->user()?->canManageOperations()) {
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

    public function updatedNouvellePhoto(): void
    {
        $this->authorizePhotoManagement();
        $this->validateOnly('nouvellePhoto', $this->photoRules(), $this->photoMessages());
    }

    /**
     * Ajoute ou remplace la photo d'identification du bien.
     */
    public function enregistrerPhoto(): void
    {
        $this->authorizePhotoManagement();
        $this->validate($this->photoRules(), $this->photoMessages());

        $anciennePhoto = $this->bien->photo_path;
        $nouveauChemin = null;

        try {
            $nouveauChemin = $this->nouvellePhoto->store('immobilisations/'.$this->bien->getKey(), 'public');
            $this->bien->update(['photo_path' => $nouveauChemin]);
        } catch (\Throwable $e) {
            if ($nouveauChemin) {
                Storage::disk('public')->delete($nouveauChemin);
            }

            report($e);
            session()->flash('error', 'La photo n’a pas pu être enregistrée.');

            return;
        }

        if ($anciennePhoto && $anciennePhoto !== $nouveauChemin) {
            try {
                Storage::disk('public')->delete($anciennePhoto);
            } catch (\Throwable $e) {
                // La nouvelle photo reste valide même si le nettoyage de l'ancien fichier échoue.
                report($e);
            }
        }

        $this->reset('nouvellePhoto');
        $this->bien->refresh();
        session()->flash('success', 'La photo du bien a été enregistrée.');
    }

    /**
     * Supprime uniquement la photo de la fiche, sans supprimer le bien.
     */
    public function supprimerPhoto(): void
    {
        $this->authorizePhotoManagement();

        $chemin = $this->bien->photo_path;
        if (! $chemin) {
            return;
        }

        try {
            $this->bien->update(['photo_path' => null]);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'La photo n’a pas pu être supprimée.');

            return;
        }

        try {
            Storage::disk('public')->delete($chemin);
        } catch (\Throwable $e) {
            // La fiche ne référence plus le fichier ; son nettoyage pourra être repris séparément.
            report($e);
        }

        $this->reset('nouvellePhoto');
        $this->bien->refresh();
        session()->flash('success', 'La photo du bien a été supprimée.');
    }

    private function authorizePhotoManagement(): void
    {
        abort_unless(Auth::user()?->canManageOperations(), 403);
    }

    /** @return array<string, string> */
    private function photoRules(): array
    {
        return [
            'nouvellePhoto' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    /** @return array<string, string> */
    private function photoMessages(): array
    {
        return [
            'nouvellePhoto.required' => 'Sélectionnez une photo.',
            'nouvellePhoto.image' => 'Le fichier sélectionné doit être une image valide.',
            'nouvellePhoto.mimes' => 'Utilisez une image JPG, PNG ou WebP.',
            'nouvellePhoto.max' => 'La photo ne doit pas dépasser 5 Mo.',
        ];
    }

    /**
     * Archive l'immobilisation
     */
    public function supprimer()
    {
        if (! Auth::user()->canManageOperations()) {
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
