<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventaireController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes pour Progressive Web App (PWA)
|--------------------------------------------------------------------------
|
| Ces routes sont utilisées par l'application mobile PWA pour scanner
| les QR codes et effectuer les inventaires.
|
| Toutes les routes sont préfixées par /api/v1
|
| NOTE IMPORTANTE: Les contrôleurs API doivent être créés dans
| app/Http/Controllers/Api/ avant d'activer ces routes.
|
*/

Route::prefix('v1')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Authentification API
    |----------------------------------------------------------------------
    |
    | Routes pour l'authentification via l'API (utilise Sanctum)
    |
    */
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('api.login');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('api.logout');

    /*
    |----------------------------------------------------------------------
    | Routes Protégées (nécessitent authentification Sanctum)
    |----------------------------------------------------------------------
    |
    | Toutes les routes ci-dessous nécessitent un token d'authentification
    | valide (Sanctum).
    |
    */
    Route::middleware(['auth:sanctum', 'api.inventory', 'abilities:inventory:read'])->group(function () {

        /*
        |------------------------------------------------------------------
        | Inventaires API
        |------------------------------------------------------------------
        |
        | Routes pour gérer les inventaires depuis l'application mobile
        |
        */
        Route::prefix('inventaires')->name('api.inventaires.')->group(function () {
            // Récupérer l'inventaire en cours pour l'utilisateur
            Route::get('/current', [InventaireController::class, 'current'])->name('current');

            // Récupérer les localisations assignées à l'utilisateur pour un inventaire
            Route::get('/{inventaire}/mes-localisations', [InventaireController::class, 'mesLocalisations'])
                ->name('mes-localisations');
        });

        /*
        |------------------------------------------------------------------
        | États API (pour PWA modal)
        |------------------------------------------------------------------
        */
        Route::get('/etats', [ScanController::class, 'getEtats'])
            ->name('api.etats.index');

        /*
        |------------------------------------------------------------------
        | Emplacements API (Nouveau Workflow)
        |------------------------------------------------------------------
        |
        | Routes pour le nouveau workflow : scan par emplacement
        |
        */
        Route::prefix('emplacements')->name('api.emplacements.')->group(function () {
            // Récupérer tous les biens affectés à un emplacement
            Route::get('/{idEmplacement}/biens', [ScanController::class, 'getBiensByEmplacement'])
                ->name('biens');

            // Terminer le scan d'un emplacement et calculer les écarts
            Route::post('/{idEmplacement}/terminer', [ScanController::class, 'terminerScanEmplacement'])
                ->middleware('abilities:inventory:scan')
                ->name('terminer');
        });

        /*
        |------------------------------------------------------------------
        | Scans unitaires (compatibilité)
        |------------------------------------------------------------------
        |
        | Conservée uniquement pour rejouer les scans hors ligne des anciennes
        | versions de la PWA (migrateLegacyPendingScans dans app-v2.js).
        |
        */
        Route::prefix('inventaires')->name('api.scans.')->group(function () {
            Route::post('/{inventaire}/scan', [ScanController::class, 'store'])
                ->middleware('abilities:inventory:scan')
                ->name('store');
        });
    });
});
