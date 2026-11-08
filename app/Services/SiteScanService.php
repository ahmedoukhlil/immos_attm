<?php

namespace App\Services;

use App\Exceptions\InventaireWorkflowException;
use App\Models\AuditLog;
use App\Models\Etat;
use App\Models\Immobilisation;
use App\Models\Inventaire;
use App\Models\InventaireImmobilisationAttendue;
use App\Models\InventaireLocalisation;
use App\Models\InventaireScan;
use App\Models\InventaireSite;
use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SiteScanService
{
    public function __construct(private ScanPhotoService $scanPhotoService) {}

    public function finaliser(
        Inventaire $inventaire,
        InventaireLocalisation $inventaireLocalisation,
        InventaireSite $inventaireSite,
        Site $site,
        User $user,
        array $scanData,
        string $finalizationKey = '',
    ): array {
        $finalizationKey = $finalizationKey !== ''
            ? $finalizationKey
            : "inventaire:{$inventaire->id}:site:{$inventaireSite->id}:user:{$user->idUser}";
        $utiliseReferentielFige = $inventaire->hasFrozenReference();
        $referentielSite = $utiliseReferentielFige
            ? InventaireImmobilisationAttendue::query()
                ->where('inventaire_id', $inventaire->id)
                ->where(function ($query) use ($inventaireSite, $site): void {
                    $query->where('inventaire_site_id', $inventaireSite->id)
                        ->orWhere(function ($legacyQuery) use ($site): void {
                            $legacyQuery->whereNull('inventaire_site_id')
                                ->where('site_id', $site->id);
                        });
                })
                ->get()
            : collect();
        $biensAttendusIds = $utiliseReferentielFige
            ? $referentielSite->pluck('immobilisation_id')->map(fn ($id) => (int) $id)->all()
            : Immobilisation::where('site_id', $site->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        $biensAttendus = Immobilisation::whereIn('id', $biensAttendusIds)
            ->with(['designation', 'categorie'])
            ->get();

        $scanItems = collect($scanData)->keyBy(fn (array $item) => (int) $item['num_ordre']);
        $biensScannesIds = $scanItems->keys()->all();
        $etatIds = $scanItems->pluck('etat_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $etats = $etatIds->isEmpty()
            ? collect()
            : Etat::query()->whereIn('id', $etatIds)->get()->keyBy('id');

        $photoErrors = collect($scanData)
            ->mapWithKeys(function (array $scanItem, int $index) use ($etats): array {
                if ($this->resolveEtatConstate($scanItem, $etats) !== 'defectueux' || ! empty($scanItem['photo'])) {
                    return [];
                }

                return ["biens_scannes.{$index}.photo" => 'Une photo est obligatoire pour un bien déclaré défectueux.'];
            })
            ->all();

        if ($photoErrors !== []) {
            throw ValidationException::withMessages($photoErrors);
        }
        $biensExistants = Immobilisation::whereIn('id', $biensScannesIds)->get()->keyBy('id');
        $sitesAttendusParBien = $utiliseReferentielFige
            ? InventaireImmobilisationAttendue::query()
                ->where('inventaire_id', $inventaire->id)
                ->whereIn('immobilisation_id', $biensScannesIds)
                ->pluck('site_id', 'immobilisation_id')
            : $biensExistants->mapWithKeys(
                fn (Immobilisation $bien): array => [(int) $bien->id => (int) $bien->site_id],
            );
        $idsExistants = $biensExistants->keys()->map(fn ($id) => (int) $id)->all();
        $biensIntrouvables = array_values(array_diff($biensScannesIds, $idsExistants));
        $biensEnTrop = array_values(array_intersect(
            array_diff($biensScannesIds, $biensAttendusIds),
            $idsExistants,
        ));
        $detailsBiensEnTrop = $biensExistants->whereIn('id', $biensEnTrop);
        if ($detailsBiensEnTrop->isNotEmpty()) {
            $detailsBiensEnTrop->load([
                'designation',
                'categorie',
                'site.localisation',
            ]);
        }
        $dejaTrouves = InventaireScan::query()
            ->where('inventaire_id', $inventaire->id)
            ->whereIn('immobilisation_id', $biensAttendusIds)
            ->where('statut_scan', '!=', 'absent')
            ->pluck('immobilisation_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $biensManquants = array_values(array_diff($biensAttendusIds, $biensScannesIds, $dejaTrouves));
        $photosCreees = [];

        try {
            DB::transaction(function () use (
                $inventaire,
                $inventaireLocalisation,
                $inventaireSite,
                $site,
                $user,
                $scanItems,
                $etats,
                $biensExistants,
                $sitesAttendusParBien,
                $biensManquants,
                $biensAttendusIds,
                $finalizationKey,
                &$photosCreees,
            ): void {
                $lockedInventaire = Inventaire::whereKey($inventaire->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedLocalisation = InventaireLocalisation::whereKey($inventaireLocalisation->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedSite = InventaireSite::whereKey($inventaireSite->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedInventaire->statut !== 'en_cours') {
                    throw ValidationException::withMessages([
                        'inventaire' => "L'inventaire n'est plus en cours.",
                    ]);
                }

                if ($lockedLocalisation->inventaire_id !== $lockedInventaire->id
                    || $lockedSite->inventaire_localisation_id !== $lockedLocalisation->id
                    || (int) $lockedSite->site_id !== (int) $site->id
                    || (int) $lockedLocalisation->localisation_id !== (int) $site->localisation_id) {
                    throw ValidationException::withMessages([
                        'site' => "Ce site ne fait pas partie de ce périmètre d'inventaire.",
                    ]);
                }

                if (! $lockedLocalisation->isAssignedTo($user)) {
                    throw new AuthorizationException("Vous n'êtes pas assigné à cette localisation.");
                }

                if ($lockedSite->statut === 'termine') {
                    throw ValidationException::withMessages([
                        'site' => 'Ce site est déjà terminé.',
                    ]);
                }

                if ($lockedLocalisation->statut === 'en_attente') {
                    $lockedLocalisation->update([
                        'statut' => 'en_cours',
                        'date_debut_scan' => now(),
                    ]);
                }

                $lockedSite->update([
                    'statut' => 'en_cours',
                    'date_debut_scan' => $lockedSite->date_debut_scan ?? now(),
                ]);

                $affectedLocalisationIds = [$lockedLocalisation->id];
                $existingScans = InventaireScan::query()
                    ->where('inventaire_id', $inventaire->id)
                    ->whereIn('immobilisation_id', $scanItems->keys())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('immobilisation_id');
                $scanRows = [];
                $writeTime = now();

                foreach ($scanItems as $numOrdre => $scanItem) {
                    $bien = $biensExistants->get($numOrdre);

                    if (! $bien) {
                        continue;
                    }

                    $scan = $existingScans->get($numOrdre);

                    if ($scan && $scan->statut_scan !== 'absent') {
                        continue;
                    }

                    if ($scan) {
                        $affectedLocalisationIds[] = $scan->inventaire_localisation_id;
                    }

                    $photoPath = null;
                    if (! empty($scanItem['photo'])) {
                        $photoPath = $this->scanPhotoService->storeBase64($scanItem['photo'], 'GS'.$numOrdre);
                        $photosCreees[] = $photoPath;
                    }

                    $scanRows[] = [
                        'inventaire_id' => $inventaire->id,
                        'inventaire_localisation_id' => $lockedLocalisation->id,
                        'immobilisation_id' => $numOrdre,
                        'date_scan' => $writeTime,
                        'statut_scan' => (int) $sitesAttendusParBien->get($numOrdre) === (int) $site->id
                            ? 'present'
                            : 'deplace',
                        'localisation_reelle_id' => $site->localisation_id,
                        'site_reel_id' => $site->id,
                        'etat_constate' => $this->resolveEtatConstate($scanItem, $etats),
                        'photo_path' => $photoPath,
                        'user_id' => $user->idUser,
                        'created_at' => $scan?->created_at ?? $writeTime,
                        'updated_at' => $writeTime,
                    ];
                }

                foreach (array_chunk($scanRows, 500) as $chunk) {
                    InventaireScan::query()->upsert(
                        $chunk,
                        ['inventaire_id', 'immobilisation_id'],
                        [
                            'inventaire_localisation_id',
                            'date_scan',
                            'statut_scan',
                            'localisation_reelle_id',
                            'site_reel_id',
                            'etat_constate',
                            'photo_path',
                            'user_id',
                            'updated_at',
                        ],
                    );
                }

                if ($biensManquants !== []) {
                    $now = now();
                    $absentRows = array_map(
                        fn (int $numOrdre): array => [
                            'inventaire_id' => $inventaire->id,
                            'inventaire_localisation_id' => $lockedLocalisation->id,
                            'immobilisation_id' => $numOrdre,
                            'date_scan' => $now,
                            'statut_scan' => 'absent',
                            'localisation_reelle_id' => null,
                            'site_reel_id' => null,
                            'etat_constate' => 'bon_etat',
                            'user_id' => $user->idUser,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $biensManquants,
                    );

                    foreach (array_chunk($absentRows, 500) as $chunk) {
                        InventaireScan::query()->insertOrIgnore($chunk);
                    }
                }

                $nombreBiensTraites = InventaireScan::query()
                    ->where('inventaire_id', $inventaire->id)
                    ->whereIn('immobilisation_id', $biensAttendusIds)
                    ->distinct()
                    ->count('immobilisation_id');

                $lockedSite->update([
                    'nombre_biens_attendus' => count($biensAttendusIds),
                    'nombre_biens_scannes' => $nombreBiensTraites,
                    'statut' => 'termine',
                    'date_fin_scan' => now(),
                    'finalization_key' => $finalizationKey,
                ]);

                $biensModifiesIds = collect($scanRows)
                    ->pluck('immobilisation_id')
                    ->merge($biensManquants)
                    ->unique()
                    ->values();
                if ($inventaire->hasFrozenReference()
                    && $biensModifiesIds->isNotEmpty()) {
                    $affectedLocalisationIds = array_merge(
                        $affectedLocalisationIds,
                        InventaireImmobilisationAttendue::query()
                            ->where('inventaire_id', $inventaire->id)
                            ->whereIn('immobilisation_id', $biensModifiesIds)
                            ->pluck('inventaire_localisation_id')
                            ->all(),
                    );
                }

                $this->synchroniserCompteursLocalisations($inventaire, $affectedLocalisationIds);

                $sitesRestants = $lockedLocalisation->inventaireSites()
                    ->where('statut', '!=', 'termine')
                    ->count();

                $lockedLocalisation->update([
                    'statut' => $sitesRestants === 0 ? 'termine' : 'en_cours',
                    'date_fin_scan' => $sitesRestants === 0 ? now() : null,
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($photosCreees as $photoPath) {
                $this->scanPhotoService->delete($photoPath);
            }

            throw $exception;
        }

        return [
            'emplacement' => [
                'id' => $site->id,
                'nom' => $site->site,
                'code' => $site->code,
            ],
            'statistiques' => [
                'total_attendu' => count($biensAttendusIds),
                'total_scanne' => count($biensScannesIds) - count($biensIntrouvables),
                'total_manquant' => count($biensManquants),
                'total_en_trop' => count($biensEnTrop),
                'total_introuvables' => count($biensIntrouvables),
                'taux_conformite' => count($biensAttendusIds) > 0
                    ? round(count(array_intersect($biensScannesIds, $biensAttendusIds)) / count($biensAttendusIds) * 100, 2)
                    : 0,
            ],
            'biens_manquants' => $utiliseReferentielFige
                ? $this->detailsBiensManquantsFiges($referentielSite->whereIn('immobilisation_id', $biensManquants))
                : $this->detailsBiensManquants($biensAttendus->whereIn('id', $biensManquants)),
            'biens_en_trop' => $this->detailsBiensEnTrop($detailsBiensEnTrop),
            'biens_introuvables' => $biensIntrouvables,
        ];
    }

    /**
     * Rouvre un site terminé par erreur (administrateur uniquement).
     *
     * Les lignes « absent » générées à sa finalisation sont supprimées pour
     * que ses biens puissent être scannés à nouveau ; les scans physiques
     * déjà enregistrés sont conservés et renvoyés à la PWA comme déjà scannés.
     * Un inventaire terminé (mais non clôturé) repasse en cours.
     */
    public function rouvrir(InventaireSite $inventaireSite, User $user): void
    {
        if (! $user->canManageOperations()) {
            throw new AuthorizationException('Seul un administrateur peut rouvrir un site.');
        }

        DB::transaction(function () use ($inventaireSite, $user): void {
            $lockedSite = InventaireSite::whereKey($inventaireSite->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedLocalisation = InventaireLocalisation::whereKey($lockedSite->inventaire_localisation_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedInventaire = Inventaire::whereKey($lockedLocalisation->inventaire_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedInventaire->statut, ['en_cours', 'termine'], true)) {
                throw new InventaireWorkflowException('Un site ne peut être rouvert que pendant un inventaire en cours ou terminé (non clôturé).');
            }

            if ($lockedSite->statut !== 'termine') {
                throw new InventaireWorkflowException('Seul un site terminé peut être rouvert.');
            }

            $biensAttendusIds = $lockedInventaire->hasFrozenReference()
                ? InventaireImmobilisationAttendue::query()
                    ->where('inventaire_id', $lockedInventaire->id)
                    ->where(function ($query) use ($lockedSite): void {
                        $query->where('inventaire_site_id', $lockedSite->id)
                            ->orWhere(function ($legacyQuery) use ($lockedSite): void {
                                $legacyQuery->whereNull('inventaire_site_id')
                                    ->where('site_id', $lockedSite->site_id);
                            });
                    })
                    ->pluck('immobilisation_id')
                : Immobilisation::where('site_id', $lockedSite->site_id)->pluck('id');

            $absentsSupprimes = InventaireScan::query()
                ->where('inventaire_id', $lockedInventaire->id)
                ->whereIn('immobilisation_id', $biensAttendusIds)
                ->where('statut_scan', 'absent')
                ->delete();

            $lockedSite->update([
                'statut' => 'en_cours',
                'date_fin_scan' => null,
                'finalization_key' => null,
                'nombre_biens_scannes' => InventaireScan::query()
                    ->where('inventaire_id', $lockedInventaire->id)
                    ->whereIn('immobilisation_id', $biensAttendusIds)
                    ->distinct()
                    ->count('immobilisation_id'),
            ]);

            $lockedLocalisation->update([
                'statut' => 'en_cours',
                'date_debut_scan' => $lockedLocalisation->date_debut_scan ?? now(),
                'date_fin_scan' => null,
            ]);

            $this->synchroniserCompteursLocalisations($lockedInventaire, [$lockedLocalisation->id]);

            $inventaireRouvert = $lockedInventaire->statut === 'termine';
            if ($inventaireRouvert) {
                $lockedInventaire->update([
                    'statut' => 'en_cours',
                    'date_fin' => null,
                    'finished_at' => null,
                ]);
            }

            AuditLog::query()->create([
                'event' => 'site_reopened',
                'auditable_type' => InventaireSite::class,
                'auditable_id' => (string) $lockedSite->id,
                'actor_id' => $user->idUser,
                'old_values' => ['statut' => 'termine'],
                'new_values' => [
                    'statut' => 'en_cours',
                    'inventaire_id' => $lockedInventaire->id,
                    'absents_supprimes' => $absentsSupprimes,
                    'inventaire_rouvert' => $inventaireRouvert,
                ],
            ]);
        });

        $inventaireSite->refresh();
    }

    private function resolveEtatConstate(array $scanItem, Collection $etats): string
    {
        if (! empty($scanItem['etat_constate'])) {
            return $scanItem['etat_constate'];
        }

        if (empty($scanItem['etat_id'])) {
            return 'bon_etat';
        }

        return $etats->get((int) $scanItem['etat_id'])?->etatConstate() ?? 'bon_etat';
    }

    private function synchroniserCompteursLocalisations(Inventaire $inventaire, array $localisationIds): void
    {
        $localisations = InventaireLocalisation::query()
            ->where('inventaire_id', $inventaire->id)
            ->whereIn('id', array_unique($localisationIds))
            ->get();

        if ($localisations->isEmpty()) {
            return;
        }

        $utiliseReferentielFige = $inventaire->hasFrozenReference();

        if ($utiliseReferentielFige) {
            $processedCounts = InventaireScan::query()
                ->join('inventaire_immobilisations_attendues as attendus', function ($join): void {
                    $join->on('inventaire_scans.inventaire_id', '=', 'attendus.inventaire_id')
                        ->on('inventaire_scans.immobilisation_id', '=', 'attendus.immobilisation_id');
                })
                ->where('inventaire_scans.inventaire_id', $inventaire->id)
                ->whereIn('attendus.inventaire_localisation_id', $localisations->pluck('id'))
                ->groupBy('attendus.inventaire_localisation_id')
                ->selectRaw('attendus.inventaire_localisation_id, COUNT(DISTINCT inventaire_scans.immobilisation_id) as aggregate')
                ->pluck('aggregate', 'inventaire_localisation_id');
        } else {
            $processedCounts = InventaireScan::query()
                ->join('immobilisations', 'inventaire_scans.immobilisation_id', '=', 'immobilisations.id')
                ->join('sites', 'immobilisations.site_id', '=', 'sites.id')
                ->where('inventaire_scans.inventaire_id', $inventaire->id)
                ->whereIn('sites.localisation_id', $localisations->pluck('localisation_id'))
                ->groupBy('sites.localisation_id')
                ->selectRaw('sites.localisation_id as localisation_id, COUNT(DISTINCT inventaire_scans.immobilisation_id) as aggregate')
                ->pluck('aggregate', 'localisation_id');
        }
        $now = now();
        $rows = $localisations->map(function (InventaireLocalisation $localisation) use ($processedCounts, $now, $utiliseReferentielFige): array {
            $attributes = $localisation->getAttributes();
            $counterKey = $utiliseReferentielFige ? $localisation->id : $localisation->localisation_id;
            $attributes['nombre_biens_scannes'] = (int) $processedCounts->get($counterKey, 0);
            $attributes['updated_at'] = $now;

            return $attributes;
        })->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            InventaireLocalisation::query()->upsert(
                $chunk,
                ['id'],
                ['nombre_biens_scannes', 'updated_at'],
            );
        }
    }

    private function detailsBiensManquants(Collection $biens): Collection
    {
        return $biens->map(fn ($bien) => [
            'num_ordre' => $bien->id,
            'code_inventaire' => $bien->code_formate,
            'designation' => $bien->libelle_article ?? 'N/A',
            'categorie' => $bien->categorie->categorie ?? 'N/A',
        ])->values();
    }

    private function detailsBiensManquantsFiges(Collection $biens): Collection
    {
        return $biens->map(fn (InventaireImmobilisationAttendue $bien) => [
            'num_ordre' => $bien->immobilisation_id,
            'code_inventaire' => $bien->code_affiche,
            'designation' => $bien->designation ?? 'N/A',
            'categorie' => $bien->categorie ?? 'N/A',
        ])->values();
    }

    private function detailsBiensEnTrop(Collection $biens): Collection
    {
        return $biens->map(fn ($bien) => [
            'num_ordre' => $bien->id,
            'code_inventaire' => $bien->code_formate,
            'designation' => $bien->libelle_article ?? 'N/A',
            'categorie' => $bien->categorie->categorie ?? 'N/A',
            'statut' => 'deplace',
            'emplacement_initial' => $bien->site ? [
                'id' => $bien->site->id,
                'nom' => $bien->site->site,
                'code' => $bien->site->code ?? null,
                'localisation' => $bien->site->localisation->localisation ?? null,
                'responsable' => $bien->site->responsable,
            ] : null,
        ])->values();
    }
}
