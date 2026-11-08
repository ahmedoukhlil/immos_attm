<?php

namespace App\Livewire\Biens;

use App\Services\InventaireExcelImporter;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Import d'immobilisations depuis le modèle Excel : analyse (simulation) puis
 * import réel limité aux créations de référentiels cochées par l'utilisateur.
 */
class ImportBiens extends Component
{
    use WithFileUploads;

    private const ANOMALIES_AFFICHEES = 200;

    public $fichier;

    /** Résultat de l'analyse (simulation). */
    public ?array $analyse = null;

    /** Créations cochées : [type => [index => bool]]. */
    public array $choix = [];

    /** Immobilisations cochées : [index de l'analyse => bool]. */
    public array $retenues = [];

    /** Résultat de l'import réel. */
    public ?array $resultat = null;

    public function updatedFichier(): void
    {
        $this->reset(['analyse', 'choix', 'retenues', 'resultat']);
        $this->resetErrorBag();
    }

    public function analyser(InventaireExcelImporter $importer): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validerFichier();
        $this->resultat = null;

        try {
            $rapport = $this->executer(fn (string $chemin, string $source) => $importer->importer($chemin, true, source: $source));
        } catch (RuntimeException $e) {
            $this->addError('fichier', $e->getMessage());

            return;
        }

        $this->analyse = $this->resumer($rapport);
        $this->choix = collect($this->analyse['propositions'])
            ->map(fn (array $propositions) => array_fill(0, count($propositions), true))
            ->all();
        $this->toutCocher(true);
    }

    public function toutCocher(bool $valeur): void
    {
        // Les lignes déjà importées en totalité n'ont rien à créer : jamais cochées.
        $this->retenues = array_map(
            fn (array $immobilisation) => $valeur && $immobilisation['quantite'] > 0,
            $this->analyse['immobilisations'] ?? [],
        );
    }

    public function importer(InventaireExcelImporter $importer): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        if ($this->analyse === null) {
            return;
        }

        $this->validerFichier();

        // Les cases cochées désignent les clés des propositions de l'analyse.
        $autorisations = collect($this->analyse['propositions'])
            ->map(fn (array $propositions, string $type) => collect($propositions)
                ->filter(fn (array $proposition, int $index) => (bool) ($this->choix[$type][$index] ?? false))
                ->pluck('cle')
                ->all())
            ->all();

        // Seules les lignes Excel cochées sont importées.
        $lignes = collect($this->analyse['immobilisations'])
            ->filter(fn (array $immobilisation, int $index) => $immobilisation['quantite'] > 0 && ($this->retenues[$index] ?? false))
            ->pluck('ligne')
            ->all();

        if ($lignes === []) {
            $this->addError('retenues', 'Cochez au moins une immobilisation à créer.');

            return;
        }

        try {
            $rapport = $this->executer(fn (string $chemin, string $source) => $importer->importer($chemin, false, autorisations: $autorisations, source: $source, lignesRetenues: $lignes));
        } catch (RuntimeException $e) {
            $this->addError('fichier', $e->getMessage());

            return;
        }

        $this->resultat = $this->resumer($rapport);
        $this->reset(['fichier', 'analyse', 'choix', 'retenues']);

        // Le composant d'import est imbriqué dans le formulaire de création.
        // Prévenir le parent afin qu'il remonte immédiatement les référentiels
        // créés ou modifiés par l'import (sans rechargement complet de la page).
        $this->dispatch('referentiels-importes');
    }

    public function annuler(): void
    {
        $this->reset(['fichier', 'analyse', 'choix', 'retenues', 'resultat']);
        $this->resetErrorBag();
    }

    private function validerFichier(): void
    {
        $this->validate(
            ['fichier' => 'required|file|extensions:xlsx,xls|max:10240'],
            [
                'fichier.required' => 'Choisissez un fichier Excel.',
                'fichier.extensions' => 'Le fichier doit être un classeur Excel (.xlsx ou .xls).',
                'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            ],
        );
    }

    /**
     * Copie le fichier téléversé sous un nom local en .xlsx (le fichier temporaire
     * de Livewire n'a pas d'extension exploitable) puis lance l'import.
     * Les références d'import reposent sur le contenu : réimporter le même fichier
     * ne crée pas de doublons, quel que soit son nom.
     */
    private function executer(callable $import): array
    {
        $contenu = $this->fichier->get();
        $extension = strtolower($this->fichier->getClientOriginalExtension()) === 'xls' ? 'xls' : 'xlsx';
        $chemin = tempnam(sys_get_temp_dir(), 'import').'.'.$extension;
        file_put_contents($chemin, $contenu);

        try {
            return $import($chemin, 'upload:'.substr(sha1($contenu), 0, 16));
        } finally {
            @unlink($chemin);
        }
    }

    /**
     * Rapport allégé pour l'affichage (propositions en listes, anomalies limitées).
     */
    private function resumer(array $rapport): array
    {
        return [
            'compteurs' => $rapport['compteurs'],
            'categories' => $rapport['categories'],
            'propositions' => collect($rapport['propositions'])
                ->map(fn (array $propositions) => collect($propositions)
                    ->map(fn (array $details, string $cle) => $details + ['cle' => $cle])
                    ->values()
                    ->all())
                ->all(),
            'immobilisations' => $rapport['immobilisations'],
            'anomalies_total' => count($rapport['anomalies']),
            'anomalies' => array_slice($rapport['anomalies'], 0, self::ANOMALIES_AFFICHEES),
        ];
    }

    public function render()
    {
        return view('livewire.biens.import-biens');
    }
}
