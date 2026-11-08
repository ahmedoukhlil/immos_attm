<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Controller d'authentification pour l'API PWA
 * Gère l'authentification via Laravel Sanctum
 */
class AuthController extends Controller
{
    /**
     * Authentifier un utilisateur et retourner un token Sanctum
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        // Valider les données
        $request->validate([
            'users' => 'required|string',
            'mdp' => 'required|string',
        ]);

        // Récupérer l'utilisateur (utilise la méthode helper qui gère les différences d'environnement)
        $user = User::findByUsername($request->users);

        // Vérifier l'utilisateur
        if (! $user) {
            throw ValidationException::withMessages([
                'users' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        if (! Hash::isHashed($user->mdp) || ! Hash::check($request->mdp, $user->mdp)) {
            throw ValidationException::withMessages([
                'users' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Vérifier que l'utilisateur possède l'un des rôles métier autorisés.
        if (! in_array($user->role, ['agent', 'gestionnaire', 'admin'], true)) {
            return response()->json([
                'message' => 'Accès non autorisé pour ce type de compte.',
            ], 403);
        }

        $user->tokens()->where('name', 'pwa-scanner')->delete();

        // Créer un nouveau token Sanctum
        $token = $user->createToken(
            'pwa-scanner',
            ['inventory:read', 'inventory:scan'],
            now()->addHours(8),
        )->plainTextToken;

        // Retourner les données (avec aliases pour compatibilité PWA v1/v2)
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->idUser,           // Alias pour PWA v1
                'idUser' => $user->idUser,
                'name' => $user->users,          // Alias pour PWA v2
                'users' => $user->users,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Déconnecter l'utilisateur (révoquer le token actuel)
     *
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        // Vérifier que l'utilisateur est authentifié
        if (! $request->user()) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        // Révoquer le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * Obtenir les informations de l'utilisateur authentifié
     *
     * @return JsonResponse
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->idUser,
                'idUser' => $user->idUser,
                'name' => $user->users,
                'users' => $user->users,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Rafraîchir le token (optionnel)
     * Crée un nouveau token et révoque l'ancien
     *
     * @return JsonResponse
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        // Révoquer l'ancien token
        $request->user()->currentAccessToken()->delete();

        // Créer un nouveau token
        $token = $user->createToken(
            'pwa-scanner',
            ['inventory:read', 'inventory:scan'],
            now()->addHours(8),
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'message' => 'Token rafraîchi avec succès',
        ]);
    }
}
