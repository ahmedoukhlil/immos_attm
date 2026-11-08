<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventaire;
use App\Models\InventaireLocalisation;
use App\Services\InventaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller API pour la gestion des inventaires depuis la PWA
 * Gère les opérations d'inventaire via l'API
 */
class InventaireController extends Controller
{
    protected $inventaireService;

    /**
     * Constructeur avec injection de dépendance
     */
    public function __construct(InventaireService $inventaireService)
    {
        $this->inventaireService = $inventaireService;
    }

    /**
     * Récupérer l'inventaire actuellement en cours
     *
     * @return JsonResponse
     */
    public function current(Request $request)
    {
        $query = Inventaire::where('statut', 'en_cours');

        if (! $request->user()->canManageOperations()) {
            $query->whereHas('inventaireLocalisations', fn ($localisations) => $localisations
                ->byAgent($request->user()->idUser));
        }

        $inventaire = $query
            ->with(['creator'])
            ->withCount(['inventaireLocalisations', 'inventaireScans'])
            ->orderBy('annee', 'desc')
            ->first();

        if (! $inventaire) {
            return response()->json([
                'message' => 'Aucun inventaire en cours',
            ], 404);
        }

        // Calculer les statistiques
        $stats = $this->inventaireService->calculerStatistiques($inventaire);

        return response()->json([
            'inventaire' => [
                'id' => $inventaire->id,
                'annee' => $inventaire->annee,
                'date_debut' => $inventaire->date_debut->format('Y-m-d'),
                'date_fin' => $inventaire->date_fin?->format('Y-m-d'),
                'statut' => $inventaire->statut,
                'observation' => $inventaire->observation,
                'created_by' => $inventaire->created_by,
                'creator_name' => $inventaire->creator->users ?? null,
            ],
            'statistiques' => $stats,
        ]);
    }

    /**
     * Récupérer les localisations assignées à l'utilisateur pour cet inventaire
     *
     * @return JsonResponse
     */
    public function mesLocalisations(Request $request, Inventaire $inventaire)
    {
        $userId = $request->user()->idUser;

        abort_unless($inventaire->statut === 'en_cours', 404);

        $query = InventaireLocalisation::where('inventaire_id', $inventaire->id);

        if (! $request->user()->canManageOperations()) {
            $query->byAgent($userId);
        }

        $localisations = $query
            ->with(['localisation.sites', 'inventaireSites'])
            ->get()
            ->map(function ($invLoc) {
                $inventaireSites = $invLoc->inventaireSites->keyBy('site_id');

                return [
                    'id' => $invLoc->id,
                    'inventaire_id' => $invLoc->inventaire_id,
                    'localisation_id' => $invLoc->localisation_id,
                    'date_debut_scan' => $invLoc->date_debut_scan?->format('Y-m-d H:i:s'),
                    'date_fin_scan' => $invLoc->date_fin_scan?->format('Y-m-d H:i:s'),
                    'statut' => $invLoc->statut,
                    'nombre_biens_attendus' => $invLoc->nombre_biens_attendus,
                    'nombre_biens_scannes' => $invLoc->nombre_biens_scannes,
                    'user_id' => $invLoc->user_id,
                    'localisation' => $invLoc->localisation ? [
                        'id' => $invLoc->localisation->id,
                        'idLocalisation' => $invLoc->localisation->id,
                        'code' => $invLoc->localisation->code,
                        'designation' => $invLoc->localisation->localisation,
                        'localisation' => $invLoc->localisation->localisation,
                        'CodeLocalisation' => $invLoc->localisation->code,
                        // Contrat PWA : les sites sont exposés sous la clé « emplacements ».
                        'emplacements' => $invLoc->localisation->sites->map(fn ($site) => [
                            'id' => $site->id,
                            'code' => $site->code,
                            'nom' => $site->site,
                            'responsable' => $site->responsable,
                            'statut' => $inventaireSites->get($site->id)?->statut,
                            'nombre_biens_attendus' => (int) $inventaireSites->get($site->id)?->nombre_biens_attendus,
                            'nombre_biens_scannes' => (int) $inventaireSites->get($site->id)?->nombre_biens_scannes,
                        ])->values(),
                    ] : null,
                ];
            });

        return response()->json([
            'localisations' => $localisations,
        ]);
    }
}
