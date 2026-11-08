<?php

namespace App\Services;

use App\Events\BienScanne;
use App\Events\InventaireCloture;
use App\Events\InventaireDemarre;
use App\Events\InventaireTermine;
use App\Exceptions\InventaireWorkflowException;
use App\Jobs\GenerateInventaireExport;
use App\Models\AuditLog;
use App\Models\Etat;
use App\Models\HistoriqueTransfert;
use App\Models\Immobilisation;
use App\Models\Inventaire;
use App\Models\InventaireExportRequest;
use App\Models\InventaireImmobilisationAttendue;
use App\Models\InventaireLocalisation;
use App\Models\InventaireScan;
use App\Models\InventaireSite;
use App\Models\Localisation;
use App\Models\Site;
use App\Models\User;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Service pour centraliser la logique métier des inventaires
 *
 * Ce service gère toutes les opérations liées aux inventaires :
 * - Création et démarrage d'inventaires
 * - Gestion des localisations et scans
 * - Calcul de statistiques
 * - Détection d'anomalies
 * - Génération de rapports
 */
class InventaireService
{
    /**
     * Crée un nouvel inventaire avec ses localisations
     *
     * @param  array  $data  Données de l'inventaire
     *                       - 'annee' : Année de l'inventaire
     *                       - 'date_debut' : Date de début
     *                       - 'observation' : Observation optionnelle
     *                       - 'localisations' : Array d'IDs de localisations
     *                       - 'assignations' : Array [localisation_id => user_id]
     * @param  User  $user  Administrateur qui crée l'inventaire
     * @return Inventaire L'inventaire créé
     *
     * @throws Exception Si un inventaire est déjà en cours ou en préparation
     */
    public function creerInventaire(array $data, User $user): Inventaire
    {
        return DB::transaction(function () use ($data, $user) {
            if (! $user->canManageOperations()) {
                throw new AuthorizationException('Seul un administrateur peut créer un inventaire.');
            }

            Validator::make($data, [
                'annee' => ['required', 'integer'],
                'date_debut' => ['required', 'date'],
                'observation' => ['nullable', 'string', 'max:1000'],
                'localisations' => ['required', 'array', 'min:1'],
                'localisations.*' => ['required', 'integer', 'distinct', 'exists:localisations,id'],
                'assignations' => ['sometimes', 'array'],
            ])->validate();

            // Vérifier qu'aucun inventaire en_cours ou en_preparation n'existe
            $inventaireExistant = Inventaire::whereIn('statut', ['en_cours', 'en_preparation'])
                ->lockForUpdate()
                ->first();

            if ($inventaireExistant) {
                throw new InventaireWorkflowException("Un inventaire est déjà en cours ou en préparation pour l'année {$inventaireExistant->annee}.");
            }

            // Créer l'inventaire avec statut 'en_preparation'
            $inventaire = Inventaire::create([
                'annee' => $data['annee'],
                'date_debut' => $data['date_debut'],
                'statut' => 'en_preparation',
                'created_by' => $user->idUser,
                'observation' => $data['observation'] ?? null,
            ]);

            // Pour chaque localisation, créer InventaireLocalisation
            foreach ($data['localisations'] as $localisationId) {
                $localisation = Localisation::find($localisationId);

                if (! $localisation) {
                    throw new InventaireWorkflowException("Localisation {$localisationId} introuvable.");
                }

                // Calculer nombre_biens_attendus via les sites → immobilisations
                $nombreBiensAttendus = Immobilisation::whereHas('site', fn ($q) => $q->where('localisation_id', $localisationId))->count();

                $agentIds = collect($data['assignations'][$localisationId] ?? [])
                    ->filter()
                    ->map(fn ($userId) => (int) $userId)
                    ->unique()
                    ->values();

                if ($agentIds->isNotEmpty()) {
                    $validAgentIds = User::query()
                        ->whereIn('idUser', $agentIds)
                        ->whereIn('role', ['admin', 'gestionnaire', 'agent'])
                        ->pluck('idUser');

                    if ($validAgentIds->count() !== $agentIds->count()) {
                        throw new InventaireWorkflowException('Une assignation contient un utilisateur invalide.');
                    }
                }

                $inventaireLocalisation = InventaireLocalisation::create([
                    'inventaire_id' => $inventaire->id,
                    'localisation_id' => $localisationId,
                    'statut' => 'en_attente',
                    'user_id' => $agentIds->first(),
                    'nombre_biens_attendus' => $nombreBiensAttendus,
                    'nombre_biens_scannes' => 0,
                ]);

                if ($agentIds->isNotEmpty()) {
                    $inventaireLocalisation->agents()->sync($agentIds->all());
                }

                $this->synchroniserSites($inventaireLocalisation);
            }

            Log::info('Inventaire créé', [
                'inventaire_id' => $inventaire->id,
                'annee' => $inventaire->annee,
                'localisations' => count($data['localisations']),
                'user_id' => $user->idUser,
            ]);

            $this->auditerTransition('created', $inventaire, $user, [], [
                'statut' => 'en_preparation',
                'annee' => $inventaire->annee,
            ]);

            return $inventaire->fresh(['inventaireLocalisations']);
        });
    }

    /**
     * Démarre un inventaire (passe de 'en_preparation' à 'en_cours')
     *
     * @param  Inventaire  $inventaire  L'inventaire à démarrer
     * @return bool True si succès
     *
     * @throws Exception Si le statut n'est pas 'en_preparation' ou si aucune localisation n'est assignée
     */
    public function demarrerInventaire(Inventaire $inventaire, User $user): bool
    {
        $resultat = DB::transaction(function () use ($inventaire, $user) {
            $inventaireVerrouille = Inventaire::query()
                ->lockForUpdate()
                ->findOrFail($inventaire->id);

            if (! $user->canManageOperations()) {
                throw new AuthorizationException('Seul un administrateur peut démarrer un inventaire.');
            }

            // Vérifier statut = 'en_preparation'
            if ($inventaireVerrouille->statut !== 'en_preparation') {
                throw new InventaireWorkflowException("Seuls les inventaires en préparation peuvent être démarrés. Statut actuel : {$inventaireVerrouille->statut}");
            }

            $localisations = $inventaireVerrouille->inventaireLocalisations()->with('agents')->get();

            if ($localisations->isEmpty()) {
                throw new InventaireWorkflowException("L'inventaire doit contenir au moins une localisation.");
            }

            if ($localisations->contains(fn (InventaireLocalisation $localisation) => ! $localisation->user_id && $localisation->agents->isEmpty())) {
                throw new InventaireWorkflowException("Toutes les localisations doivent être assignées avant de démarrer l'inventaire.");
            }

            foreach ($localisations as $localisation) {
                $this->synchroniserSites($localisation);

                if (! $localisation->inventaireSites()->exists()) {
                    throw new InventaireWorkflowException('Chaque localisation doit contenir au moins un site avant le démarrage.');
                }
            }

            $this->figerReferentiel($inventaireVerrouille);

            // Changer statut à 'en_cours'
            $inventaireVerrouille->demarrer();

            $this->auditerTransition('started', $inventaireVerrouille, $user, [
                'statut' => 'en_preparation',
            ], [
                'statut' => 'en_cours',
                'started_at' => $inventaireVerrouille->started_at,
            ]);

            Log::info('Inventaire démarré', [
                'inventaire_id' => $inventaireVerrouille->id,
                'annee' => $inventaireVerrouille->annee,
                'user_id' => $user->idUser,
            ]);

            // Event : InventaireDemarre
            event(new InventaireDemarre($inventaireVerrouille));

            return true;
        });

        $inventaire->refresh();

        return $resultat;
    }

    /**
     * Enregistre un scan d'inventaire
     *
     * @param  array  $data  Données du scan
     *                       - 'inventaire_id' : ID de l'inventaire
     *                       - 'inventaire_localisation_id' : ID de la localisation
     *                       - 'immobilisation_id' : ID du bien scanné
     *                       - 'localisation_reelle_id' : ID de la localisation où le bien a été trouvé
     *                       - 'etat_constate' : État constaté
     *                       - 'commentaire' : Commentaire optionnel
     *                       - 'photo_path' : Chemin de la photo optionnelle
     * @return InventaireScan Le scan créé
     *
     * @throws InventaireWorkflowException Si les conditions métier ne permettent pas le scan
     */
    public function enregistrerScan(array $data, User $user, ?array $contexteAttendu = null): InventaireScan
    {
        return DB::transaction(function () use ($data, $user, $contexteAttendu) {
            $inventaire = Inventaire::query()->lockForUpdate()->find($data['inventaire_id']);
            if (! $inventaire || $inventaire->statut !== 'en_cours') {
                throw new InventaireWorkflowException("L'inventaire doit être en cours pour enregistrer un scan.");
            }

            $bien = Immobilisation::find($data['immobilisation_id']);
            if (! $bien) {
                throw new InventaireWorkflowException("Immobilisation {$data['immobilisation_id']} introuvable.");
            }

            // Vérifier que InventaireLocalisation est en_cours
            $invLoc = InventaireLocalisation::query()
                ->lockForUpdate()
                ->find($data['inventaire_localisation_id']);
            if (! $invLoc
                || $invLoc->inventaire_id !== $inventaire->id
                || $invLoc->statut !== 'en_cours') {
                throw new InventaireWorkflowException('La localisation doit être en cours pour enregistrer un scan.');
            }

            if (! $invLoc->isAssignedTo($user)) {
                throw new AuthorizationException("Vous n'êtes pas assigné à cette localisation.");
            }

            // Vérifier que le bien n'a pas déjà été scanné dans cet inventaire
            $scanExistant = InventaireScan::where('inventaire_id', $data['inventaire_id'])
                ->where('immobilisation_id', $data['immobilisation_id'])
                ->lockForUpdate()
                ->first();

            if ($scanExistant && $scanExistant->statut_scan !== 'absent') {
                throw new InventaireWorkflowException("Le bien GS{$bien->id} a déjà été scanné dans cet inventaire.");
            }

            [$statutScan, $inventaireLocalisationAttendueId] = $contexteAttendu === null
                ? $this->resoudreContexteAttendu(
                    $inventaire,
                    $bien,
                    (int) ($data['localisation_reelle_id'] ?? 0),
                    $invLoc,
                )
                : $this->formaterContexteAttendu(
                    $contexteAttendu['localisation_id'],
                    $contexteAttendu['inventaire_localisation_id'],
                    (int) ($data['localisation_reelle_id'] ?? 0),
                );

            $scanAttributes = [
                'inventaire_localisation_id' => $data['inventaire_localisation_id'],
                'date_scan' => now(),
                'statut_scan' => $statutScan,
                'localisation_reelle_id' => $data['localisation_reelle_id'] ?? null,
                'etat_constate' => $data['etat_constate'] ?? null,
                'commentaire' => $data['commentaire'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
                'user_id' => $user->idUser,
            ];
            if ($scanExistant) {
                $scanExistant->update($scanAttributes);
                $scan = $scanExistant->refresh();
            } else {
                $scan = InventaireScan::create([
                    'inventaire_id' => $data['inventaire_id'],
                    'immobilisation_id' => $data['immobilisation_id'],
                    ...$scanAttributes,
                ]);
            }

            if (! $scanExistant) {
                if ($inventaireLocalisationAttendueId === $invLoc->id) {
                    $invLoc->increment('nombre_biens_scannes');
                } elseif ($inventaireLocalisationAttendueId !== null) {
                    InventaireLocalisation::query()
                        ->whereKey($inventaireLocalisationAttendueId)
                        ->increment('nombre_biens_scannes');
                }
            }

            Log::info('Scan enregistré', [
                'scan_id' => $scan->id,
                'immobilisation_id' => $scan->immobilisation_id,
                'statut_scan' => $scan->statut_scan,
                'user_id' => $user->idUser,
            ]);

            // Event : BienScanne
            event(new BienScanne($scan));

            return $scan;
        });
    }

    /**
     * Détermine le statut et la localisation dont le compteur doit progresser.
     * Le référentiel figé prime sur la position courante du bien.
     *
     * @return array{0: string, 1: int|null}
     */
    private function resoudreContexteAttendu(
        Inventaire $inventaire,
        Immobilisation $bien,
        int $localisationReelleId,
        InventaireLocalisation $localisationDeScan,
    ): array {
        $reference = null;
        $utiliseReferentielFige = false;

        if ($inventaire->hasFrozenReference()) {
            $reference = InventaireImmobilisationAttendue::query()
                ->where('inventaire_id', $inventaire->id)
                ->where('immobilisation_id', $bien->id)
                ->first(['immobilisation_id', 'localisation_id', 'inventaire_localisation_id']);
            $utiliseReferentielFige = true;
        }

        if ($utiliseReferentielFige) {
            $localisationAttendueId = $reference?->localisation_id;
            $inventaireLocalisationAttendueId = $reference?->inventaire_localisation_id;
        } else {
            $localisationAttendueId = $bien->site?->localisation_id;
            $inventaireLocalisationAttendueId = (int) $localisationAttendueId === (int) $localisationDeScan->localisation_id
                ? $localisationDeScan->id
                : InventaireLocalisation::query()
                    ->where('inventaire_id', $inventaire->id)
                    ->where('localisation_id', $localisationAttendueId)
                    ->value('id');
        }

        return $this->formaterContexteAttendu(
            $localisationAttendueId,
            $inventaireLocalisationAttendueId,
            $localisationReelleId,
        );
    }

    /** @return array{0: string, 1: int|null} */
    private function formaterContexteAttendu(
        ?int $localisationAttendueId,
        ?int $inventaireLocalisationAttendueId,
        int $localisationReelleId,
    ): array {
        $statut = $localisationAttendueId !== null
            && $localisationAttendueId === $localisationReelleId
                ? 'present'
                : 'deplace';

        return [$statut, $inventaireLocalisationAttendueId];
    }

    /**
     * Termine un inventaire
     *
     * @param  Inventaire  $inventaire  L'inventaire à terminer
     * @return bool True si succès
     *
     * @throws Exception Si les conditions ne sont pas remplies
     */
    public function terminerInventaire(Inventaire $inventaire, User $user): bool
    {
        $resultat = DB::transaction(function () use ($inventaire, $user) {
            $inventaireVerrouille = Inventaire::query()
                ->lockForUpdate()
                ->findOrFail($inventaire->id);

            if (! $user->canManageOperations()) {
                throw new AuthorizationException('Seul un administrateur peut terminer un inventaire.');
            }

            // Vérifier statut = 'en_cours'
            if ($inventaireVerrouille->statut !== 'en_cours') {
                throw new InventaireWorkflowException("Seuls les inventaires en cours peuvent être terminés. Statut actuel : {$inventaireVerrouille->statut}");
            }

            // Vérifier que toutes les localisations sont terminées
            $localisationsNonTerminees = $inventaireVerrouille->inventaireLocalisations()
                ->where('statut', '!=', 'termine')
                ->count();

            if ($localisationsNonTerminees > 0) {
                throw new InventaireWorkflowException("Toutes les localisations doivent être terminées avant de terminer l'inventaire. {$localisationsNonTerminees} localisation(s) restante(s).");
            }

            // Set statut = 'termine', date_fin = now()
            $inventaireVerrouille->update([
                'statut' => 'termine',
                'date_fin' => now(),
                'finished_at' => now(),
            ]);

            $this->auditerTransition('finished', $inventaireVerrouille, $user, [
                'statut' => 'en_cours',
            ], [
                'statut' => 'termine',
                'finished_at' => $inventaireVerrouille->finished_at,
            ]);

            Log::info('Inventaire terminé', [
                'inventaire_id' => $inventaireVerrouille->id,
                'annee' => $inventaireVerrouille->annee,
                'user_id' => $user->idUser,
            ]);

            // Event : InventaireTermine
            event(new InventaireTermine($inventaireVerrouille));

            return true;
        });

        $inventaire->refresh();

        return $resultat;
    }

    /**
     * Clôture définitivement un inventaire
     *
     * @param  Inventaire  $inventaire  L'inventaire à clôturer
     * @param  User  $user  L'utilisateur qui clôture
     * @return bool True si succès
     *
     * @throws Exception Si le statut n'est pas 'termine'
     */
    public function cloturerInventaire(Inventaire $inventaire, User $user): bool
    {
        $resultat = DB::transaction(function () use ($inventaire, $user) {
            $inventaireVerrouille = Inventaire::query()
                ->lockForUpdate()
                ->findOrFail($inventaire->id);

            if (! $user->canManageOperations()) {
                throw new AuthorizationException('Seul un administrateur peut clôturer un inventaire.');
            }

            // Vérifier statut = 'termine'
            if ($inventaireVerrouille->statut !== 'termine') {
                throw new InventaireWorkflowException("Seuls les inventaires terminés peuvent être clôturés. Statut actuel : {$inventaireVerrouille->statut}");
            }

            $constatsAppliques = $this->appliquerConstatsAuRegistre($inventaireVerrouille, $user);

            // Set statut = 'cloture', closed_by = $user->idUser
            $inventaireVerrouille->cloturer($user->idUser);

            $this->auditerTransition('closed', $inventaireVerrouille, $user, [
                'statut' => 'termine',
            ], [
                'statut' => 'cloture',
                'closed_at' => $inventaireVerrouille->closed_at,
                'closed_by' => $user->idUser,
                ...$constatsAppliques,
            ]);

            Log::info('Inventaire clôturé', [
                'inventaire_id' => $inventaireVerrouille->id,
                'annee' => $inventaireVerrouille->annee,
                'closed_by' => $user->idUser,
            ]);

            // Event : InventaireCloture
            event(new InventaireCloture($inventaireVerrouille, $user));

            return true;
        });

        $inventaire->refresh();

        $this->planifierRapportsFinaux($inventaire, $user);

        return $resultat;
    }

    /**
     * Met le registre en conformité avec les constats physiques :
     * - un bien trouvé dans un autre site y est transféré (avec historique) ;
     * - l'état est mis à jour quand l'état constaté diffère de l'état connu ;
     * - le code porté par l'étiquette devient le « code dernier inventaire ».
     * Les biens absents ne sont pas modifiés : leur sortie relève d'une
     * décision de gestion distincte (voir SortieService).
     *
     * @return array{biens_transferes: int, etats_mis_a_jour: int, codes_reportes: int}
     */
    private function appliquerConstatsAuRegistre(Inventaire $inventaire, User $user): array
    {
        $scans = InventaireScan::query()
            ->where('inventaire_id', $inventaire->id)
            ->where('statut_scan', '!=', 'absent')
            ->with(['bien.site.localisation', 'bien.etat'])
            ->get()
            ->filter(fn (InventaireScan $scan): bool => $scan->bien !== null);

        $sitesReels = Site::query()
            ->with('localisation')
            ->whereIn('id', $scans->pluck('site_reel_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $etatsParConstat = Etat::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Etat $etat): string => $etat->etatConstate())
            ->map(fn (Collection $etats): Etat => $etats->first());

        $now = now();
        $raison = "Constat de l'inventaire {$inventaire->annee}";
        $groupeTransfert = "inventaire-{$inventaire->id}";
        $biensTransferes = 0;
        $etatsMisAJour = 0;
        $codesReportes = 0;

        foreach ($scans as $scan) {
            $bien = $scan->bien;
            $modifications = [];
            $siteReel = $sitesReels->get($scan->site_reel_id);

            if ($scan->statut_scan === 'deplace' && $siteReel && (int) $bien->site_id !== (int) $siteReel->id) {
                HistoriqueTransfert::create([
                    'immobilisation_id' => $bien->id,
                    'ancien_site_id' => $bien->site?->id,
                    'nouveau_site_id' => $siteReel->id,
                    'ancien_site_libelle' => $bien->site?->site ?? 'Sans site',
                    'nouveau_site_libelle' => $siteReel->site,
                    'ancien_localisation_libelle' => $bien->site?->localisation?->localisation ?? 'N/A',
                    'nouveau_localisation_libelle' => $siteReel->localisation?->localisation ?? 'N/A',
                    'transfert_par' => $user->idUser,
                    'date_transfert' => $now,
                    'raison' => $raison,
                    'groupe_transfert_id' => $groupeTransfert,
                ]);
                $modifications['site_id'] = $siteReel->id;
                $biensTransferes++;
            }

            $etatConstate = $etatsParConstat->get($scan->etat_constate);
            if ($etatConstate && $bien->etat?->etatConstate() !== $scan->etat_constate) {
                $modifications['etat_id'] = $etatConstate->id;
                $etatsMisAJour++;
            }

            if ($bien->code_propose && $bien->code_dernier_inventaire !== $bien->code_propose) {
                $modifications['code_dernier_inventaire'] = $bien->code_propose;
                $codesReportes++;
            }

            if ($modifications !== []) {
                $bien->update($modifications);
            }
        }

        return [
            'biens_transferes' => $biensTransferes,
            'etats_mis_a_jour' => $etatsMisAJour,
            'codes_reportes' => $codesReportes,
        ];
    }

    /**
     * Crée les deux rapports réglementaires d'une clôture. La création est
     * idempotente afin qu'une reprise ne produise pas plusieurs rapports.
     */
    private function planifierRapportsFinaux(Inventaire $inventaire, User $user): void
    {
        foreach (['pdf', 'xlsx'] as $format) {
            $export = InventaireExportRequest::query()->firstOrCreate(
                [
                    'final_key' => "inventaire:{$inventaire->id}:final:{$format}",
                ],
                [
                    'inventaire_id' => $inventaire->id,
                    'format' => $format,
                    'automatic' => true,
                    'requested_by' => $user->idUser,
                    'status' => 'pending',
                    'disk' => 'local',
                ],
            );

            if ($export->wasRecentlyCreated || $export->status === 'failed') {
                if ($export->status === 'failed') {
                    $export->update(['status' => 'pending', 'error_message' => null]);
                }

                GenerateInventaireExport::dispatch($export)->afterCommit();
            }
        }
    }

    private function auditerTransition(
        string $event,
        Inventaire $inventaire,
        User $user,
        array $oldValues,
        array $newValues,
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $request = app()->runningInConsole() ? null : request();

        AuditLog::query()->create([
            'event' => $event,
            'auditable_type' => Inventaire::class,
            'auditable_id' => (string) $inventaire->id,
            'actor_id' => $user->idUser,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent()
                ? Str::limit($request->userAgent(), 500, '')
                : null,
        ]);
    }

    public function synchroniserSites(InventaireLocalisation $inventaireLocalisation): void
    {
        $sites = Site::query()
            ->where('localisation_id', $inventaireLocalisation->localisation_id)
            ->withCount('immobilisations')
            ->get();

        foreach ($sites as $site) {
            InventaireSite::updateOrCreate(
                [
                    'inventaire_localisation_id' => $inventaireLocalisation->id,
                    'site_id' => $site->id,
                ],
                ['nombre_biens_attendus' => $site->immobilisations_count],
            );
        }

        $inventaireLocalisation->inventaireSites()
            ->whereNotIn('site_id', $sites->pluck('id'))
            ->where('statut', 'en_attente')
            ->delete();

        $inventaireLocalisation->update([
            'nombre_biens_attendus' => $sites->sum('immobilisations_count'),
        ]);
    }

    /**
     * Fige le référentiel attendu juste avant le démarrage de l'inventaire.
     * Les rapports clôturés ne dépendent ainsi plus des modifications futures
     * apportées aux biens, aux sites ou aux référentiels.
     */
    public function figerReferentiel(Inventaire $inventaire): void
    {
        DB::transaction(function () use ($inventaire): void {
            $inventaire->biensAttendus()->delete();

            $localisations = $inventaire->inventaireLocalisations()
                ->with([
                    'localisation',
                    'inventaireSites.site.immobilisations.designation',
                    'inventaireSites.site.immobilisations.categorie',
                ])
                ->get();

            $now = now();
            $lignes = [];

            foreach ($localisations as $inventaireLocalisation) {
                foreach ($inventaireLocalisation->inventaireSites as $inventaireSite) {
                    $site = $inventaireSite->site;

                    foreach ($site?->immobilisations ?? [] as $bien) {
                        $lignes[] = [
                            'inventaire_id' => $inventaire->id,
                            'inventaire_localisation_id' => $inventaireLocalisation->id,
                            'inventaire_site_id' => $inventaireSite->id,
                            'immobilisation_id' => $bien->id,
                            'code_propose' => $bien->code_propose,
                            'designation' => $bien->libelle_article,
                            'categorie' => $bien->categorie?->categorie,
                            'site_id' => $site->id,
                            'site_code' => $site->code,
                            'site_nom' => $site->site,
                            'localisation_id' => $inventaireLocalisation->localisation_id,
                            'localisation_code' => $inventaireLocalisation->localisation?->code,
                            'localisation_nom' => $inventaireLocalisation->localisation?->localisation,

                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            foreach (array_chunk($lignes, 500) as $chunk) {
                InventaireImmobilisationAttendue::query()->insert($chunk);
            }

            $inventaire->update(['referentiel_frozen_at' => $now]);
        });
    }

    /**
     * Calcule les statistiques complètes d'un inventaire
     *
     * @param  Inventaire  $inventaire  L'inventaire à analyser
     * @return array Tableau avec toutes les statistiques
     */
    public function calculerStatistiques(
        Inventaire $inventaire,
        ?Collection $inventaireLocalisations = null,
        ?Collection $scans = null,
        ?Collection $referentielFige = null,
    ): array {
        $inventaireLocalisations ??= $inventaire->inventaireLocalisations()
            ->with(['localisation', 'agent'])
            ->get();
        $scans ??= $inventaire->inventaireScans()
            ->with('bien.categorie')
            ->get();

        $totalLocalisations = $inventaireLocalisations->count();
        $localisationsTerminees = $inventaireLocalisations->where('statut', 'termine')->count();
        $localisationsEnCours = $inventaireLocalisations->where('statut', 'en_cours')->count();
        $localisationsEnAttente = $inventaireLocalisations->where('statut', 'en_attente')->count();

        $localisationIds = $inventaireLocalisations->pluck('localisation_id')->unique()->toArray();
        $referentielFige ??= $inventaire->biensAttendus()->get();
        $utiliseReferentielFige = $inventaire->hasFrozenReference();
        $totalBiensAttendus = $utiliseReferentielFige
            ? $referentielFige->count()
            : $inventaireLocalisations->sum('nombre_biens_attendus');
        $biensAttendusIds = $utiliseReferentielFige
            ? $referentielFige->pluck('immobilisation_id')
            : Immobilisation::query()
                ->whereHas('site', fn ($query) => $query->whereIn('localisation_id', $localisationIds))
                ->pluck('id');
        // Une ligne "absent" est créée automatiquement à la finalisation d'un
        // site. Elle représente un bien traité, mais pas un scan physique.
        $scansAttendus = $scans->whereIn('immobilisation_id', $biensAttendusIds);
        $scansPhysiques = $scansAttendus->where('statut_scan', '!=', 'absent');
        $totalBiensTraites = $scansAttendus->unique('immobilisation_id')->count();
        $totalBiensScannes = $scansPhysiques->unique('immobilisation_id')->count();

        $biensPresents = $scansAttendus->where('statut_scan', 'present')->count();
        $biensDeplaces = $scansAttendus->where('statut_scan', 'deplace')->count();
        $biensAbsents = $scansAttendus->where('statut_scan', 'absent')->count();
        $biensDefectueux = $scansPhysiques->where('etat_constate', 'defectueux')->count();

        // Répartition par état physique (Bon Etat, Neuf, Defectueux)
        $biensNeufs = $scansPhysiques->where('etat_constate', 'neuf')->count();
        $biensBonEtat = $scansPhysiques->where('etat_constate', 'bon_etat')->count();

        $progressionGlobale = $totalLocalisations > 0
            ? round(($localisationsTerminees / $totalLocalisations) * 100, 2)
            : 0;

        // La conformité mesure le patrimoine attendu retrouvé à sa place.
        $tauxConformite = $totalBiensAttendus > 0
            ? round(($biensPresents / $totalBiensAttendus) * 100, 2)
            : 0;

        // Couverture physique : seuls les biens réellement constatés sont comptés.
        $tauxCouverture = $totalBiensAttendus > 0
            ? round(($totalBiensScannes / $totalBiensAttendus) * 100, 2)
            : 0;

        $tauxRapprochement = $totalBiensAttendus > 0
            ? round(($totalBiensTraites / $totalBiensAttendus) * 100, 2)
            : 0;

        $tauxAbsence = $totalBiensAttendus > 0
            ? round(($biensAbsents / $totalBiensAttendus) * 100, 2)
            : 0;

        // Biens qui n'ont encore fait l'objet d'aucun rapprochement.
        $biensNonScannes = max(0, $totalBiensAttendus - $totalBiensTraites);

        // Un bien déplacé et défectueux ne doit compter qu'une fois.
        $biensAnormauxIds = $scansAttendus
            ->filter(fn (InventaireScan $scan): bool => in_array($scan->statut_scan, ['deplace', 'absent'], true)
                || $scan->etat_constate === 'defectueux')
            ->pluck('immobilisation_id')
            ->unique();
        $totalAnomalies = $biensAnormauxIds->count();
        $tauxAnomalies = $totalBiensAttendus > 0
            ? round(($totalAnomalies / $totalBiensAttendus) * 100, 2)
            : 0;

        // Nombre d'agents ayant participé
        $primaryAgentIds = $inventaireLocalisations->whereNotNull('user_id')->pluck('user_id');
        $additionalAgentIds = DB::table('inventaire_localisation_user')
            ->whereIn('inventaire_localisation_id', $inventaireLocalisations->pluck('id'))
            ->pluck('user_id');
        $nombreAgents = $primaryAgentIds->merge($additionalAgentIds)->unique()->count();

        $dureeJours = $inventaire->duree ?? 0;

        // Statistiques par site
        $calculerSite = function (
            int $siteId,
            string $code,
            string $designation,
            string $localisation,
            string $responsable,
            $biensAttendusIds
        ) use ($scansAttendus): array {
            $biensAttendus = $biensAttendusIds->count();
            $scansSite = $scansAttendus->whereIn('immobilisation_id', $biensAttendusIds);
            $biensTraites = $scansSite->unique('immobilisation_id')->count();
            $biensScannes = $scansSite->where('statut_scan', '!=', 'absent')->unique('immobilisation_id')->count();
            $biensPresents = $scansSite->where('statut_scan', 'present')->count();
            $progression = $biensAttendus > 0 ? round(($biensTraites / $biensAttendus) * 100, 2) : 0;
            $tauxCouverture = $biensAttendus > 0 ? round(($biensScannes / $biensAttendus) * 100, 2) : 0;
            $tauxConformite = $biensAttendus > 0 ? round(($biensPresents / $biensAttendus) * 100, 2) : 0;

            return [
                'site_id' => $siteId,
                'code' => $code,
                'designation' => $designation,
                'localisation' => $localisation,
                'responsable' => $responsable,
                'biens_attendus' => $biensAttendus,
                'biens_traites' => $biensTraites,
                'biens_scannes' => $biensScannes,
                'biens_presents' => $biensPresents,
                'progression' => $progression,
                'taux_couverture' => $tauxCouverture,
                'taux_conformite' => $tauxConformite,
            ];
        };

        $responsables = Site::query()
            ->whereIn('localisation_id', $localisationIds)
            ->pluck('responsable', 'id');

        if ($utiliseReferentielFige) {
            $parSite = $referentielFige
                ->groupBy('site_id')
                ->map(function ($biens) use ($calculerSite, $responsables): array {
                    $premier = $biens->first();

                    return $calculerSite(
                        (int) $premier->site_id,
                        $premier->site_code ?? $premier->site_nom ?? 'N/A',
                        $premier->site_nom ?? $premier->site_code ?? 'N/A',
                        $premier->localisation_code ?? $premier->localisation_nom ?? 'N/A',
                        $responsables->get($premier->site_id) ?? 'N/A',
                        $biens->pluck('immobilisation_id'),
                    );
                })
                ->values()
                ->all();
        } else {
            $sites = Site::whereIn('localisation_id', $localisationIds)
                ->with(['localisation', 'immobilisations:id,site_id'])
                ->orderBy('site')
                ->get();

            $parSite = $sites->map(fn (Site $site): array => $calculerSite(
                (int) $site->id,
                $site->code ?? $site->site,
                $site->site,
                $site->localisation?->libelle_court ?? 'N/A',
                $site->responsable ?? 'N/A',
                $site->immobilisations->pluck('id'),
            ))->toArray();
        }

        // Synthèse par localisation
        $scansParLocalisation = $scansAttendus->groupBy('inventaire_localisation_id');
        $parLocalisation = $inventaireLocalisations->map(function ($invLoc) use ($scansParLocalisation) {
            $loc = $invLoc->localisation;
            $scansLocalisation = $scansParLocalisation->get($invLoc->id, collect());
            $biensPresents = $scansLocalisation->where('statut_scan', 'present')->count();
            $tauxConformiteLocal = $scansLocalisation->isNotEmpty()
                ? round(($biensPresents / $scansLocalisation->count()) * 100, 2)
                : 0;

            return [
                'localisation_id' => $invLoc->localisation_id,
                'code' => $loc->code ?? $loc->localisation ?? 'N/A',
                'designation' => $loc->localisation ?? $loc->code ?? 'N/A',
                'statut' => $invLoc->statut,
                'biens_attendus' => $invLoc->nombre_biens_attendus,
                'biens_scannes' => $invLoc->nombre_biens_scannes,
                'biens_presents' => $biensPresents,
                'progression' => $invLoc->progression,
                'taux_conformite' => $tauxConformiteLocal,
            ];
        })->toArray();

        // Statistiques par agent
        // Par agent : biens vus physiquement par chaque utilisateur (les lignes « absent »
        // créées à la finalisation d'un site ne sont pas des scans), y compris les biens
        // trouvés hors de leur site attendu.
        $scansParAgent = $scans->where('statut_scan', '!=', 'absent')->whereNotNull('user_id')->groupBy('user_id');
        $localisationsParAgent = $inventaireLocalisations->whereNotNull('user_id')->groupBy('user_id');
        $nomsAgents = User::query()
            ->whereIn('idUser', $scansParAgent->keys()->merge($localisationsParAgent->keys())->unique())
            ->pluck('users', 'idUser');
        $parAgent = $scansParAgent->keys()->merge($localisationsParAgent->keys())->unique()
            ->map(fn ($userId): array => [
                'user_id' => $userId,
                'agent_name' => $nomsAgents->get($userId, 'N/A'),
                'localisations' => $localisationsParAgent->get($userId)?->count() ?? 0,
                'biens_scannes' => $scansParAgent->get($userId)?->unique('immobilisation_id')->count() ?? 0,
            ])
            ->sortByDesc('biens_scannes')
            ->values()
            ->toArray();

        // Statistiques par catégorie
        $scansParBien = $scansAttendus->keyBy('immobilisation_id');
        $sourceParNature = $utiliseReferentielFige
            ? $referentielFige->groupBy(fn ($bien) => $bien->categorie ?? 'Non renseigné')
            : $scansAttendus->groupBy(fn ($scan) => $scan->bien?->categorie?->categorie ?? 'Non renseigné');
        $parNature = $sourceParNature->map(function ($group, $categorie) use ($utiliseReferentielFige, $scansParBien) {
            if ($utiliseReferentielFige) {
                $total = $group->count();
                $scansCategorie = $group->map(fn ($bien) => $scansParBien->get($bien->immobilisation_id))->filter();
            } else {
                $total = $group->count();
                $scansCategorie = $group;
            }
            $presents = $scansCategorie->where('statut_scan', 'present')->count();

            return [
                'nature' => $categorie,
                'total' => $total,
                'presents' => $presents,
                'deplaces' => $scansCategorie->where('statut_scan', 'deplace')->count(),
                'absents' => $scansCategorie->where('statut_scan', 'absent')->count(),
                'conformite' => $total > 0 ? round(($presents / $total) * 100, 2) : 0,
            ];
        })->toArray();

        return [
            'total_localisations' => $totalLocalisations,
            'localisations_terminees' => $localisationsTerminees,
            'localisations_en_cours' => $localisationsEnCours,
            'localisations_en_attente' => $localisationsEnAttente,
            'total_biens_attendus' => $totalBiensAttendus,
            'total_biens_traites' => $totalBiensTraites,
            'total_biens_scannes' => $totalBiensScannes,
            'biens_presents' => $biensPresents,
            'biens_deplaces' => $biensDeplaces,
            'biens_absents' => $biensAbsents,
            // « détériorés » et « défectueux » désignent le même constat ;
            // les deux clés restent exposées pour les exports existants.
            'biens_deteriores' => $biensDefectueux,
            'biens_defectueux' => $biensDefectueux,
            'biens_neufs' => $biensNeufs,
            'biens_bon_etat' => $biensBonEtat,
            'biens_non_scannes' => $biensNonScannes,
            'progression_globale' => $progressionGlobale,
            'taux_conformite' => $tauxConformite,
            'taux_couverture' => $tauxCouverture,
            'taux_rapprochement' => $tauxRapprochement,
            'taux_absence' => $tauxAbsence,
            'total_anomalies' => $totalAnomalies,
            'taux_anomalies' => $tauxAnomalies,
            'nombre_agents' => $nombreAgents,

            'duree_jours' => $dureeJours,
            'par_localisation' => $parLocalisation,
            'par_site' => $parSite,
            'par_agent' => $parAgent,
            'par_nature' => $parNature,
        ];
    }

    /**
     * Détecte les anomalies dans un inventaire
     *
     * @param  Inventaire  $inventaire  L'inventaire à analyser
     * @return array Tableau des alertes détectées
     */
    public function detecterAnomalies(
        Inventaire $inventaire,
        ?Collection $localisations = null,
        ?Collection $scans = null,
        ?Collection $referentielFige = null,
    ): array {
        $referentielFige ??= $inventaire->biensAttendus()->get()->keyBy('immobilisation_id');
        $localisations ??= $inventaire->inventaireLocalisations()
            ->with(['localisation', 'agents:idUser'])
            ->get();
        $scans ??= $inventaire->inventaireScans()
            ->with(['bien.designation', 'localisationReelle'])
            ->orderBy('date_scan')
            ->get();
        $scansParLocalisation = $scans->groupBy('inventaire_localisation_id');

        $alertes = [
            'localisations_non_demarrees' => [],
            'localisations_bloquees' => [],
            'biens_absents' => [],
            'localisations_non_assignees' => [],
            'biens_deteriores' => [],
            'biens_defectueux' => [],
            'taux_absence_eleve' => [],
        ];

        foreach ($localisations->where('statut', 'en_attente') as $invLoc) {
            $alertes['localisations_non_demarrees'][] = [
                'id' => $invLoc->id,
                'code' => $invLoc->localisation?->code,
                'designation' => $invLoc->localisation?->localisation,
            ];
        }

        $ilY24h = now()->subDay();
        $bloquees = $localisations
            ->where('statut', 'en_cours')
            ->filter(fn ($invLoc): bool => ! $invLoc->date_debut_scan || $invLoc->date_debut_scan->lt($ilY24h));

        foreach ($bloquees as $invLoc) {
            $dernierScan = $scansParLocalisation->get($invLoc->id, collect())
                ->sortByDesc('date_scan')
                ->first();
            $joursSansScan = $dernierScan
                ? $dernierScan->date_scan->diffInDays(now())
                : ($invLoc->date_debut_scan ? $invLoc->date_debut_scan->diffInDays(now()) : 0);

            $alertes['localisations_bloquees'][] = [
                'id' => $invLoc->id,
                'code' => $invLoc->localisation?->code,
                'designation' => $invLoc->localisation?->localisation,
                'jours' => $joursSansScan,
            ];
        }

        foreach ($scans->where('statut_scan', 'absent')->take(20) as $scan) {
            $bienFige = $referentielFige->get($scan->immobilisation_id);
            $alertes['biens_absents'][] = [
                'immobilisation_id' => $scan->immobilisation_id,
                'code' => $bienFige?->code_affiche ?? $scan->code_inventaire,
                'designation' => $bienFige?->designation ?? $scan->designation,
            ];
        }

        foreach ($localisations->filter(
            fn ($invLoc): bool => ! $invLoc->user_id && $invLoc->agents->isEmpty(),
        ) as $invLoc) {
            $alertes['localisations_non_assignees'][] = [
                'id' => $invLoc->id,
                'code' => $invLoc->localisation?->code,
                'designation' => $invLoc->localisation?->localisation,
            ];
        }

        foreach ($scans->where('etat_constate', 'defectueux') as $scan) {
            $bienFige = $referentielFige->get($scan->immobilisation_id);
            $alertes['biens_deteriores'][] = [
                'immobilisation_id' => $scan->immobilisation_id,
                'code' => $bienFige?->code_affiche ?? $scan->code_inventaire,
                'designation' => $bienFige?->designation ?? $scan->designation,
                'etat_constate' => $scan->etat_constate,
            ];
        }

        foreach ($scans->where('etat_constate', 'defectueux') as $scan) {
            $bienFige = $referentielFige->get($scan->immobilisation_id);
            $photoAbsolutePath = null;

            if ($scan->photo_path) {
                $fullPath = storage_path('app/public/'.ltrim(str_replace('\\', '/', $scan->photo_path), '/'));
                if (file_exists($fullPath)) {
                    $photoAbsolutePath = $fullPath;
                }
            }

            $alertes['biens_defectueux'][] = [
                'immobilisation_id' => $scan->immobilisation_id,
                'code' => $bienFige?->code_affiche ?? $scan->code_inventaire,
                'designation' => $bienFige?->designation ?? $scan->designation,
                'localisation' => $scan->localisationReelle?->code
                    ?? $scan->localisationReelle?->localisation
                    ?? $bienFige?->localisation_code
                    ?? $bienFige?->localisation_nom
                    ?? 'N/A',
                'photo_path' => $scan->photo_path,
                'photo_absolute' => $photoAbsolutePath,
            ];
        }

        foreach ($localisations->where('statut', '!=', 'en_attente') as $invLoc) {
            if ($invLoc->nombre_biens_attendus === 0) {
                continue;
            }

            $absents = $scansParLocalisation->get($invLoc->id, collect())
                ->where('statut_scan', 'absent')
                ->count();
            $tauxAbsence = round(($absents / $invLoc->nombre_biens_attendus) * 100, 1);

            if ($tauxAbsence <= 20) {
                continue;
            }

            $alertes['taux_absence_eleve'][] = [
                'id' => $invLoc->id,
                'code' => $invLoc->localisation?->code,
                'designation' => $invLoc->localisation?->localisation,
                'taux_absence' => $tauxAbsence,
                'biens_absents' => $absents,
            ];
        }

        return $alertes;
    }

    /**
     * Réassigne une localisation à un nouvel agent
     *
     * @param  InventaireLocalisation  $invLoc  La localisation à réassigner
     * @param  User|null  $newAgent  Le nouvel agent (null pour désassigner)
     * @param  User  $user  Administrateur qui effectue la réassignation
     * @return bool True si succès
     */
    public function reassignerLocalisation(InventaireLocalisation $invLoc, ?User $newAgent, User $user): bool
    {
        if (! $user->canManageOperations()) {
            throw new AuthorizationException('Seul un administrateur peut réassigner une localisation.');
        }

        return DB::transaction(function () use ($invLoc, $newAgent, $user) {
            $lockedInvLoc = InventaireLocalisation::query()
                ->lockForUpdate()
                ->findOrFail($invLoc->id);
            $inventaire = Inventaire::query()
                ->lockForUpdate()
                ->findOrFail($lockedInvLoc->inventaire_id);

            if (! in_array($inventaire->statut, ['en_preparation', 'en_cours'], true)) {
                throw new InventaireWorkflowException('Les assignations de cet inventaire ne sont plus modifiables.');
            }

            $lockedNewAgent = null;
            if ($newAgent) {
                $lockedNewAgent = User::query()
                    ->whereKey($newAgent->idUser)
                    ->whereIn('role', ['admin', 'gestionnaire', 'agent'])
                    ->first();

                if (! $lockedNewAgent) {
                    throw new InventaireWorkflowException('Le nouvel agent est introuvable ou ne peut pas gérer un inventaire.');
                }
            }

            $ancienAgentId = $lockedInvLoc->user_id;

            $lockedInvLoc->update([
                'user_id' => $lockedNewAgent?->idUser,
            ]);

            if ($lockedNewAgent) {
                $lockedInvLoc->agents()->syncWithoutDetaching([$lockedNewAgent->idUser]);
            }

            Log::info('Localisation réassignée', [
                'inventaire_localisation_id' => $lockedInvLoc->id,
                'ancien_agent_id' => $ancienAgentId,
                'nouvel_agent_id' => $lockedNewAgent?->idUser,
                'user_id' => $user->idUser,
            ]);

            $invLoc->setRawAttributes($lockedInvLoc->getAttributes(), true);

            return true;
        });
    }

    /**
     * Ajoute ou retire un agent d'une localisation d'inventaire.
     *
     * @return bool True lorsque l'agent est assigné après l'opération
     */
    public function basculerAgentLocalisation(
        InventaireLocalisation $invLoc,
        User $agent,
        User $user,
    ): bool {
        if (! $user->canManageOperations()) {
            throw new AuthorizationException('Seul un administrateur peut modifier les assignations.');
        }

        return DB::transaction(function () use ($invLoc, $agent, $user): bool {
            $lockedInvLoc = InventaireLocalisation::query()
                ->lockForUpdate()
                ->findOrFail($invLoc->id);
            $inventaire = Inventaire::query()
                ->lockForUpdate()
                ->findOrFail($lockedInvLoc->inventaire_id);
            $lockedAgent = User::query()
                ->whereKey($agent->idUser)
                ->whereIn('role', ['admin', 'gestionnaire', 'agent'])
                ->first();

            if (! in_array($inventaire->statut, ['en_preparation', 'en_cours'], true)) {
                throw new InventaireWorkflowException('Les assignations de cet inventaire ne sont plus modifiables.');
            }

            if (! $lockedAgent) {
                throw new InventaireWorkflowException("L'utilisateur sélectionné ne peut pas gérer un inventaire.");
            }

            $estAssigne = $lockedInvLoc->agents()
                ->whereKey($lockedAgent->idUser)
                ->exists();

            if ($estAssigne) {
                $lockedInvLoc->agents()->detach($lockedAgent->idUser);

                if ((int) $lockedInvLoc->user_id === (int) $lockedAgent->idUser) {
                    $lockedInvLoc->update([
                        'user_id' => $lockedInvLoc->agents()->value('users.idUser'),
                    ]);
                }
            } else {
                $lockedInvLoc->agents()->attach($lockedAgent->idUser);

                if (! $lockedInvLoc->user_id) {
                    $lockedInvLoc->update(['user_id' => $lockedAgent->idUser]);
                }
            }

            Log::info('Assignation de localisation modifiée', [
                'inventaire_localisation_id' => $lockedInvLoc->id,
                'agent_id' => $lockedAgent->idUser,
                'action' => $estAssigne ? 'retire' : 'ajoute',
                'user_id' => $user->idUser,
            ]);

            $invLoc->setRawAttributes($lockedInvLoc->fresh()->getAttributes(), true);

            return ! $estAssigne;
        });
    }

    /**
     * Assigne un agent à toutes les localisations d'un inventaire modifiable.
     *
     * @return int Nombre de localisations traitées
     */
    public function assignerAgentAToutesLesLocalisations(
        Inventaire $inventaire,
        User $agent,
        User $user,
    ): int {
        if (! $user->canManageOperations()) {
            throw new AuthorizationException('Seul un administrateur peut modifier les assignations.');
        }

        return DB::transaction(function () use ($inventaire, $agent, $user): int {
            $lockedInventaire = Inventaire::query()
                ->lockForUpdate()
                ->findOrFail($inventaire->id);
            $lockedAgent = User::query()
                ->whereKey($agent->idUser)
                ->whereIn('role', ['admin', 'gestionnaire', 'agent'])
                ->first();

            if (! in_array($lockedInventaire->statut, ['en_preparation', 'en_cours'], true)) {
                throw new InventaireWorkflowException('Les assignations de cet inventaire ne sont plus modifiables.');
            }

            if (! $lockedAgent) {
                throw new InventaireWorkflowException("L'utilisateur sélectionné ne peut pas gérer un inventaire.");
            }

            $localisations = $lockedInventaire->inventaireLocalisations()
                ->lockForUpdate()
                ->get();

            foreach ($localisations as $localisation) {
                $localisation->agents()->syncWithoutDetaching([$lockedAgent->idUser]);

                if (! $localisation->user_id) {
                    $localisation->update(['user_id' => $lockedAgent->idUser]);
                }
            }

            Log::info('Agent assigné à toutes les localisations', [
                'inventaire_id' => $lockedInventaire->id,
                'agent_id' => $lockedAgent->idUser,
                'localisations' => $localisations->count(),
                'user_id' => $user->idUser,
            ]);

            return $localisations->count();
        });
    }

    /**
     * Supprime un inventaire encore en préparation et sans scan.
     */
    public function supprimerInventaire(Inventaire $inventaire, User $user): bool
    {
        if (! $user->canManageOperations()) {
            throw new AuthorizationException('Seul un administrateur peut supprimer un inventaire.');
        }

        return DB::transaction(function () use ($inventaire, $user): bool {
            $lockedInventaire = Inventaire::query()
                ->lockForUpdate()
                ->findOrFail($inventaire->id);

            if ($lockedInventaire->statut !== 'en_preparation'
                || $lockedInventaire->inventaireScans()->exists()) {
                throw new InventaireWorkflowException('Seul un inventaire en préparation et sans scan peut être supprimé.');
            }

            Log::info('Inventaire supprimé', [
                'inventaire_id' => $lockedInventaire->id,
                'annee' => $lockedInventaire->annee,
                'user_id' => $user->idUser,
            ]);

            return (bool) $lockedInventaire->delete();
        });
    }

    /**
     * Génère un rapport PDF de l'inventaire
     *
     * @param  Inventaire  $inventaire  L'inventaire à documenter
     * @return string Chemin du fichier PDF généré
     *
     * @todo Implémenter la génération PDF avec DOMPDF
     */
    public function genererRapportPDF(Inventaire $inventaire): string
    {
        return app(RapportService::class)->genererRapportPDF($inventaire);
    }

    /**
     * Génère un rapport Excel de l'inventaire
     *
     * @param  Inventaire  $inventaire  L'inventaire à documenter
     * @return string Chemin du fichier Excel généré
     *
     * @todo Implémenter la génération Excel avec Laravel Excel
     */
    public function genererRapportExcel(Inventaire $inventaire): string
    {
        return app(RapportService::class)->genererRapportExcel($inventaire);
    }
}
