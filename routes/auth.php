<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes d'Authentification
|--------------------------------------------------------------------------
|
| Ces routes gèrent l'authentification des utilisateurs.
| Pour l'instant, seules les routes de base (login/logout) sont activées.
| Les autres routes (inscription, réinitialisation de mot de passe, etc.)
| peuvent être ajoutées plus tard si nécessaire.
|
*/

// Routes d'authentification de base
Route::middleware('guest')->group(function () {
    // Route de connexion
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // Protection contre les attaques par force brute : 5 tentatives par minute
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');

    // Routes d'inscription (désactivées pour l'instant)
    // Route::get('register', [RegisteredUserController::class, 'create'])
    //     ->name('register');
    // Route::post('register', [RegisteredUserController::class, 'store']);

    // Routes de réinitialisation de mot de passe (désactivées pour l'instant)
    // Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
    //     ->name('password.request');
    // Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    //     ->name('password.email');
    // Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
    //     ->name('password.reset');
    // Route::post('reset-password', [NewPasswordController::class, 'store'])
    //     ->name('password.store');
});

Route::middleware('auth')->group(function () {
    // Vérification d'email (désactivée pour l'instant)
    // Route::get('verify-email', EmailVerificationPromptController::class)
    //     ->name('verification.notice');
    // Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    //     ->middleware(['signed', 'throttle:6,1'])
    //     ->name('verification.verify');
    // Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    //     ->middleware('throttle:6,1')
    //     ->name('verification.send');

    // Confirmation de mot de passe (désactivée pour l'instant)
    // Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
    //     ->name('password.confirm');
    // Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Mise à jour du mot de passe (désactivée pour l'instant)
    // Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // Déconnexion (POST uniquement pour la sécurité)
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
