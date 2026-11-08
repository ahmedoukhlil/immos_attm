<?php

namespace App\Providers;

use App\Http\Middleware\CanManageInventaire;
use App\Http\Middleware\CheckSessionTimeout;
use App\Http\Middleware\IsAdmin;
use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Immobilisation;
use App\Models\Localisation;
use App\Models\Site;
use App\Observers\ImmobilisationObserver;
use App\Observers\ReferenceDataObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix pour MySQL : limite la longueur des chaînes pour les index
        // MySQL avec utf8mb4 a une limite de 767 bytes pour les index
        // Utilisation de 100 caractères pour être sûr (100 * 4 = 400 bytes < 1000)
        Schema::defaultStringLength(100);

        Livewire::addPersistentMiddleware([
            IsAdmin::class,
            CanManageInventaire::class,
            CheckSessionTimeout::class,
        ]);

        Immobilisation::observe(ImmobilisationObserver::class);
        Localisation::observe(ReferenceDataObserver::class);
        Site::observe(ReferenceDataObserver::class);
        Categorie::observe(ReferenceDataObserver::class);
        Designation::observe(ReferenceDataObserver::class);
    }
}
