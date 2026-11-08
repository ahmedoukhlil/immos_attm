<?php

use App\Http\Middleware\CanManageInventaire;
use App\Http\Middleware\CheckSessionTimeout;
use App\Http\Middleware\EnsureApiInventoryAccess;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Immobilisation;
use App\Models\Localisation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Configuration des route model bindings personnalisés
            Route::bind('bien', function ($value) {
                return Immobilisation::findOrFail($value);
            });

            Route::bind('localisation', function ($value) {
                return Localisation::findOrFail($value);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Derrière un tunnel ou un proxy HTTPS (ngrok, test sur téléphone) : TRUSTED_PROXIES=* dans .env.
        // Sans cette variable, aucun proxy n'est approuvé.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Enregistrement des middlewares personnalisés
        $middleware->alias([
            'admin' => IsAdmin::class,
            'inventory' => CanManageInventaire::class,
            'session.timeout' => CheckSessionTimeout::class,
            'api.inventory' => EnsureApiInventoryAccess::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
