<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InventaireWorkflowException;
use App\Http\Controllers\Controller;
use App\Models\Etat;
use App\Models\Immobilisation;
use App\Models\Inventaire;
use App\Models\InventaireImmobilisationAttendue;
use App\Models\InventaireLocalisation;
use App\Models\InventaireScan;
use App\Models\InventaireSite;
use App\Models\Site;
use App\Services\InventaireService;
use App\Services\ScanPhotoService;
use App\Services\SiteScanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controller API pour l'enregistrement des scans depuis la PWA
 * Gère les opérations de scan via l'API
 */
class ScanController extends Controller
{
    public function __construct(
        protected InventaireService $inventaireService,
        protected ScanPhotoService $scanPhotoService,
        protected SiteScanService $siteScanService,
    ) {}

    /**
     * Enregistrer un nouveau scan
     *
     * @return JsonResponse
     */
    public function store(Request $request, Inventaire $inventaire)
    {
        // Valider les données
        // Contrat PWA : « bien_id » contient l'identifiant de l'immobilisation.
        $validated = $request->validate([
            'inventaire_localisation_id' => 'required|exists:inventaire_localisations,id',
            'bien_id' => 'required|integer',
            'statut_scan' => 'sometimes|in:present,deplace,absent',
            'localisation_reelle_id' => 'required|exists:localisations,id',
            'etat_constate' => 'required|in:bon_etat,neuf,defectueux',
            'commentaire' => 'nullable|string|max:1000',
            'photo' => 'nullable|string|max:'.ScanPhotoService::MAX_BASE64_LENGTH, // Base64 image
        ]);

        // Vérifier que l'inventaire est en_cours
        if ($inventaire->statut !== 'en_cours') {
            return response()->json([
                'message' => 'L\'inventaire n\'est pas en cours',
            ], 400);
        }

        $inventaireLocalisation = InventaireLocalisation::findOrFail($validated['inventaire_localisation_id']);

        if ($inventaireLocalisation->inventaire_id !== $inventaire->id
            || $inventaireLocalisation->statut !== 'en_cours'
            || ! $inventaireLocalisation->isAssignedTo($request->user())) {
            return response()->json(['message' => 'Localisation d\'inventaire non autorisée.'], 403);
        }

        if ((int) $validated['localisation_reelle_id'] !== (int) $inventaireLocalisation->localisation_id) {
            throw ValidationException::withMessages([
                'localisation_reelle_id' => 'La localisation réelle ne correspond pas à la zone de scan autorisée.',
            ]);
        }

        $bien = Immobilisation::find($validated['bien_id']);
        if (! $bien) {
            return response()->json([
                'message' => 'Bien non trouvé (identifiant : '.$validated['bien_id'].')',
            ], 404);
        }
        $bienCode = $bien->scan_code;

        // Vérifier que le bien n'a pas déjà été scanné dans cet inventaire
        $statutExistant = InventaireScan::where('inventaire_id', $inventaire->id)
            ->where('immobilisation_id', $validated['bien_id'])
            ->value('statut_scan');

        if ($statutExistant !== null && $statutExistant !== 'absent') {
            return response()->json([
                'message' => 'Ce bien a déjà été scanné dans cet inventaire',
            ], 409);
        }

        $photoPath = null;

        try {
            // Traiter la photo si présente
            if (! empty($validated['photo'])) {
                $photoPath = $this->scanPhotoService->storeBase64($validated['photo'], $bienCode);
            }

            $scan = $this->inventaireService->enregistrerScan([
                'inventaire_id' => $inventaire->id,
                'inventaire_localisation_id' => $validated['inventaire_localisation_id'],
                'immobilisation_id' => $validated['bien_id'],
                'localisation_reelle_id' => $validated['localisation_reelle_id'],
                'etat_constate' => $validated['etat_constate'],
                'commentaire' => $validated['commentaire'] ?? null,
                'photo_path' => $photoPath,
            ], $request->user());

            return response()->json([
                'message' => 'Scan enregistré avec succès',
                'scan' => [
                    'id' => $scan->id,
                    'inventaire_id' => $scan->inventaire_id,
                    'bien_id' => $scan->immobilisation_id,
                    'statut_scan' => $scan->statut_scan,
                    'date_scan' => $scan->date_scan->format('Y-m-d H:i:s'),
                ],
            ], 201);

        } catch (AuthorizationException $e) {
            $this->scanPhotoService->delete($photoPath);

            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            $this->scanPhotoService->delete($photoPath);

            throw $e;
        } catch (InventaireWorkflowException $e) {
            $this->scanPhotoService->delete($photoPath);

            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Throwable $e) {
            $this->scanPhotoService->delete($photoPath);

            report($e);

            return response()->json([
                'message' => 'Erreur lors de l\'enregistrement du scan',
            ], 500);
        }
    }

    /**
     * Récupérer la liste des états (pour PWA modal)
     * Utilise la table etat sans modifier la BD
     *
     * @return JsonResponse
     */
    public function getEtats()
    {
        $etats = Etat::orderBy('etat')
            ->get(['id', 'etat', 'code'])
            ->map(fn (Etat $etat) => [
                'id' => $etat->id,
                'label' => $etat->etat,
                'code' => $etat->code,
                'require_photo' => $etat->etatConstate() === 'defectueux',
            ]);

        return response()->json(['etats' => $etats]);
    }

    /**
     * Récupérer tous les biens d'un site.
     * Contrat PWA : la route et les clés JSON gardent le vocabulaire « emplacement ».
     *
     * @param  int  $idEmplacement  Identifiant du site
     * @return JsonResponse
     */
    public function getBiensByEmplacement(Request $request, $idEmplacement)
    {
        $site = Site::with('localisation')->find($idEmplacement);

        if (! $site) {
            return response()->json(['message' => 'Site non trouvé'], 404);
        }

        // Une requête GET ne doit jamais créer ou réouvrir un workflow.
        $inventaire = $this->getInventaireActif();

        if (! $inventaire) {
            return response()->json(['message' => 'Aucun inventaire en cours pour ce site.'], 404);
        }

        $biensDejaScannes = collect();
        $biensRestants = null;

        $inventaireLocalisation = InventaireLocalisation::where('inventaire_id', $inventaire->id)
            ->where('localisation_id', $site->localisation_id)
            ->first();

        if (! $inventaireLocalisation || ! $inventaireLocalisation->isAssignedTo($request->user())) {
            return response()->json(['message' => 'Vous n\'êtes pas assigné à cette localisation.'], 403);
        }

        $inventaireSite = InventaireSite::query()
            ->where('inventaire_localisation_id', $inventaireLocalisation->id)
            ->where('site_id', $site->id)
            ->first();

        if (! $inventaireSite) {
            return response()->json(['message' => 'Ce site ne fait pas partie du périmètre préparé.'], 409);
        }

        if ($inventaire->hasFrozenReference()) {
            $references = InventaireImmobilisationAttendue::query()
                ->where('inventaire_id', $inventaire->id)
                ->where(function ($query) use ($inventaireSite, $site): void {
                    $query->where('inventaire_site_id', $inventaireSite->id)
                        ->orWhere(function ($sansSite) use ($site): void {
                            $sansSite->whereNull('inventaire_site_id')
                                ->where('site_id', $site->id);
                        });
                })
                ->orderBy('immobilisation_id')
                ->get();
            $biensCourants = Immobilisation::query()
                ->whereIn('id', $references->pluck('immobilisation_id'))
                ->with('etat')
                ->get()
                ->keyBy('id');
            $biens = $references->map(function (InventaireImmobilisationAttendue $reference) use ($biensCourants): array {
                $bien = $biensCourants->get($reference->immobilisation_id);

                return [
                    'num_ordre' => $reference->immobilisation_id,
                    'code' => $reference->code_propose,
                    'code_barre_128' => (string) $reference->immobilisation_id,
                    'designation' => $reference->designation ?? 'N/A',
                    'categorie' => $reference->categorie ?? 'N/A',
                    'etat' => $bien?->etat?->etat ?? 'N/A',
                    'date_acquisition' => null,
                    'descriptif' => $bien?->descriptif,
                    'marque' => $bien?->marque,
                    'modele' => $bien?->modele,
                    'numero_chassis' => $bien?->numero_chassis,
                    'numero_attm' => $bien?->numero_attm,
                    'code_immo' => $bien?->code_immo,
                    'observations' => $bien?->autres_informations,
                ];
            });
        } else {
            $biens = Immobilisation::where('site_id', $site->id)
                ->with(['designation', 'categorie', 'etat'])
                ->orderBy('id')
                ->get()
                ->map(fn (Immobilisation $bien): array => [
                    'num_ordre' => $bien->id,
                    'code' => $bien->code_propose,
                    'code_barre_128' => (string) $bien->id,
                    'designation' => $bien->libelle_article ?? 'N/A',
                    'categorie' => $bien->categorie->categorie ?? 'N/A',
                    'etat' => $bien->etat->etat ?? 'N/A',
                    'date_acquisition' => null,
                    'descriptif' => $bien->descriptif,
                    'marque' => $bien->marque,
                    'modele' => $bien->modele,
                    'numero_chassis' => $bien->numero_chassis,
                    'numero_attm' => $bien->numero_attm,
                    'code_immo' => $bien->code_immo,
                    'observations' => $bien->autres_informations,
                ]);
        }

        // Précharger les biens déjà scannés de ce site dans l'inventaire actif
        if ($biens->isNotEmpty()) {
            $biensDejaScannes = InventaireScan::where('inventaire_id', $inventaire->id)
                ->whereIn('immobilisation_id', $biens->pluck('num_ordre')->map(fn ($id) => (int) $id)->all())
                ->where('statut_scan', '!=', 'absent')
                ->get(['immobilisation_id', 'etat_constate'])
                ->map(fn (InventaireScan $scan): array => [
                    'num_ordre' => (int) $scan->immobilisation_id,
                    'etat_constate' => $scan->etat_constate,
                ]);

            $biensRestants = max(0, $biens->count() - $biensDejaScannes->count());
        }

        return response()->json([
            'inventaire_id' => $inventaire->id,
            'emplacement' => [
                'id' => $site->id,
                'code' => $site->code,
                'nom' => $site->site,
                'responsable' => $site->responsable,
                'localisation' => $site->localisation ? [
                    'id' => $site->localisation->id,
                    'nom' => $site->localisation->localisation,
                    'code' => $site->localisation->code,
                ] : null,
                'affectation' => null,
            ],
            'biens' => $biens,
            'total' => $biens->count(),
            'biens_deja_scannes' => $biensDejaScannes->values(),
            'inventaire_localisation' => [
                'id' => $inventaireLocalisation->id,
                'statut' => $inventaireLocalisation->statut,
                'nombre_biens_attendus' => (int) $inventaireLocalisation->nombre_biens_attendus,
                'nombre_biens_scannes' => (int) $inventaireLocalisation->nombre_biens_scannes,
                'biens_restants' => (int) ($biensRestants ?? 0),
            ],
            'inventaire_emplacement' => [
                'id' => $inventaireSite->id,
                'statut' => $inventaireSite->statut,
                'nombre_biens_attendus' => $inventaireSite->nombre_biens_attendus,
                'nombre_biens_scannes' => $inventaireSite->nombre_biens_scannes,
            ],
        ], 200);
    }

    /**
     * Terminer le scan d'un site : calcule les écarts entre biens attendus
     * et biens scannés, et enregistre les scans.
     *
     * @param  int  $idEmplacement  Identifiant du site
     * @return JsonResponse
     */
    public function terminerScanEmplacement(Request $request, $idEmplacement)
    {
        $validated = $request->validate([
            'biens_scannes' => 'present|array|max:2000',
            'biens_scannes.*.num_ordre' => 'required|integer|distinct',
            'biens_scannes.*.etat_id' => 'nullable|integer',
            'biens_scannes.*.etat_constate' => 'nullable|string|in:bon_etat,neuf,defectueux',
            'biens_scannes.*.photo' => 'nullable|string|max:'.ScanPhotoService::MAX_BASE64_LENGTH,
            'inventaire_id' => 'sometimes|integer',
            'idempotency_key' => ['sometimes', 'string', 'max:150', 'regex:/^[A-Za-z0-9:_-]+$/'],
        ]);

        $etatIds = collect($validated['biens_scannes'])
            ->pluck('etat_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($etatIds->isNotEmpty()) {
            $validEtatIds = Etat::query()
                ->whereIn('id', $etatIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $errors = collect($validated['biens_scannes'])
                ->mapWithKeys(function (array $scan, int $index) use ($validEtatIds): array {
                    if (empty($scan['etat_id']) || in_array((int) $scan['etat_id'], $validEtatIds, true)) {
                        return [];
                    }

                    return ["biens_scannes.{$index}.etat_id" => 'L’état sélectionné est invalide.'];
                })
                ->all();

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $user = $request->user();
        $site = Site::with('localisation')->find($idEmplacement);

        if (! $site) {
            return response()->json(['message' => 'Site non trouvé'], 404);
        }

        $inventaire = $this->getInventaireActif();

        if (! $inventaire) {
            return response()->json(['message' => 'Aucun inventaire en cours'], 404);
        }

        if (isset($validated['inventaire_id']) && (int) $validated['inventaire_id'] !== (int) $inventaire->id) {
            return response()->json([
                'message' => 'Cette opération hors ligne appartient à un autre inventaire.',
            ], 409);
        }

        $inventaireLocalisation = InventaireLocalisation::where('inventaire_id', $inventaire->id)
            ->where('localisation_id', $site->localisation_id)
            ->first();

        if (! $inventaireLocalisation || ! $inventaireLocalisation->isAssignedTo($user)) {
            return response()->json(['message' => 'Vous n\'êtes pas assigné à cette localisation.'], 403);
        }

        $inventaireSite = InventaireSite::query()
            ->where('inventaire_localisation_id', $inventaireLocalisation->id)
            ->where('site_id', $site->id)
            ->first();

        if (! $inventaireSite) {
            return response()->json(['message' => 'Ce site ne fait pas partie du périmètre préparé.'], 409);
        }

        $idempotencyKey = $validated['idempotency_key']
            ?? "inventaire:{$inventaire->id}:site:{$inventaireSite->id}:user:{$user->idUser}";

        if ($inventaireSite->statut === 'termine') {
            if ($inventaireSite->finalization_key
                && hash_equals((string) $inventaireSite->finalization_key, $idempotencyKey)) {
                return response()->json([
                    'message' => 'Ce site est déjà terminé avec cette opération.',
                    'already_finalized' => true,
                ]);
            }

            return response()->json(['message' => 'Ce site est déjà terminé.'], 409);
        }

        try {
            $result = $this->siteScanService->finaliser(
                $inventaire,
                $inventaireLocalisation,
                $inventaireSite,
                $site,
                $user,
                $validated['biens_scannes'],
                $idempotencyKey,
            );

            return response()->json($result);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Le scan du site n\'a pas pu être finalisé.',
            ], 500);
        }
    }

    /**
     * Retourne l'inventaire actif en priorisant un inventaire en cours.
     */
    private function getInventaireActif(): ?Inventaire
    {
        return Inventaire::query()
            ->where('statut', 'en_cours')
            ->orderByDesc('id')
            ->first();
    }
}
