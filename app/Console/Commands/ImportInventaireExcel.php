<?php

namespace App\Console\Commands;

use App\Services\InventaireExcelImporter;
use Illuminate\Console\Command;

class ImportInventaireExcel extends Command
{
    protected $signature = 'immos:import-excel
        {fichier : Chemin du fichier .xlsx « Inventaire Immo »}
        {--simulation : Exécute tout l\'import puis annule la transaction, sans rien écrire}
        {--feuille=Immo : Nom de la feuille contenant les immobilisations}
        {--details : Affiche chaque anomalie au lieu du résumé par type}
        {--force : N\'affiche pas la demande de confirmation avant un import réel}';

    protected $description = 'Importe les immobilisations du fichier Excel d\'inventaire dans la localisation « Siège »';

    public function handle(InventaireExcelImporter $importer): int
    {
        $simulation = (bool) $this->option('simulation');

        if (! $simulation && ! $this->option('force')
            && ! $this->confirm('Import réel : les données seront écrites en base. Continuer ?')) {
            $this->warn('Import annulé.');

            return self::FAILURE;
        }

        try {
            $rapport = $importer->importer($this->argument('fichier'), $simulation, $this->option('feuille'));
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->afficherRapport($rapport);

        return self::SUCCESS;
    }

    private function afficherRapport(array $rapport): void
    {
        $this->newLine();
        $this->line($rapport['simulation']
            ? '<fg=yellow;options=bold>SIMULATION : aucune donnée n\'a été enregistrée.</>'
            : '<fg=green;options=bold>Import terminé.</>');

        $libelles = [
            'lignes_lues' => 'Lignes lues',
            'lignes_ignorees' => 'Lignes ignorées',
            'deja_importees' => 'Biens déjà importés (ignorés)',
            'biens_crees' => $rapport['simulation'] ? 'Biens à créer' : 'Biens créés',
            'sites_crees' => $rapport['simulation'] ? 'Sites à créer' : 'Sites créés',
            'designations_creees' => $rapport['simulation'] ? 'Désignations à créer' : 'Désignations créées',
        ];

        $this->table(['Indicateur', 'Valeur'], collect($rapport['compteurs'])
            ->map(fn ($valeur, $cle) => [$libelles[$cle] ?? $cle, $valeur])
            ->values());

        $this->table(['Catégorie', 'Biens'], $this->lignes($rapport['categories']));
        $this->table(['État', 'Biens'], $this->lignes($rapport['etats']));

        $anomalies = collect($rapport['anomalies']);

        if ($anomalies->isEmpty()) {
            $this->info('Aucune anomalie.');

            return;
        }

        $this->warn("{$anomalies->count()} anomalie(s) :");

        if ($this->option('details')) {
            $this->table(['Ligne', 'Type', 'Message'], $anomalies->map(fn ($a) => [$a['ligne'], $a['type'], $a['message']]));

            return;
        }

        $this->table(['Type', 'Nombre', 'Exemple'], $anomalies->groupBy('type')
            ->map(fn ($groupe, $type) => [
                $type,
                $groupe->count(),
                'L'.$groupe->first()['ligne'].' : '.$groupe->first()['message'],
            ])
            ->values());
        $this->line('Relancez avec --details pour la liste complète.');
    }

    private function lignes(array $comptes): array
    {
        arsort($comptes);

        return collect($comptes)->map(fn ($nombre, $libelle) => [$libelle, $nombre])->values()->all();
    }
}
