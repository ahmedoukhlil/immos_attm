<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\EtiquetteController;
use App\Http\Controllers\InventaireController;
use App\Http\Controllers\LocalisationController;
use App\Http\Controllers\QRCodeSiteController;
use App\Http\Controllers\SortieController;
use App\Livewire\Biens\DetailBien;
use App\Livewire\Biens\FormBien;
use App\Livewire\Biens\HistoriqueTransferts;
use App\Livewire\Biens\ListeBiens;
use App\Livewire\Biens\TransfertBien;
use App\Livewire\Categories\FormCategorie;
use App\Livewire\Categories\ListeCategories;
use App\Livewire\Dashboard;
use App\Livewire\Designations\FormDesignation;
use App\Livewire\Designations\ListeDesignations;
use App\Livewire\Inventaires\DashboardInventaire;
use App\Livewire\Inventaires\DemarrerInventaire;
use App\Livewire\Inventaires\ListeInventaires;
use App\Livewire\Inventaires\RapportInventaire;
use App\Livewire\Inventaires\ReportInventaire;
use App\Livewire\Localisations\DetailLocalisation;
use App\Livewire\Localisations\FormLocalisation;
use App\Livewire\Localisations\ListeLocalisations;
use App\Livewire\Profil;
use App\Livewire\Sites\DetailSite;
use App\Livewire\Sites\FormSite;
use App\Livewire\Sites\ListeSites;
use App\Livewire\Sorties\DetailSortie;
use App\Livewire\Sorties\ListeSorties;
use App\Livewire\Users\FormUser;
use App\Livewire\Users\GestionRoles;
use App\Livewire\Users\ListeUsers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Publiques
|--------------------------------------------------------------------------
*/

// La page d'accueil affiche directement le formulaire de login
Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('home');

Route::view('/pwa/index-v2.html', 'pwa.index')->name('pwa.scanner');

/*
|--------------------------------------------------------------------------
| Routes d'Authentification
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}

/*
|--------------------------------------------------------------------------
| Routes Authentifiées
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'session.timeout'])->group(function () {

    // Accessible à tous les utilisateurs authentifiés (admin et agent).
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Modification du mot de passe.
    Route::get('/profil', Profil::class)->name('profil');

    /*
    |----------------------------------------------------------------------
    | Routes avec Middleware 'inventory' (Admin + Agent)
    |----------------------------------------------------------------------
    */
    Route::middleware(['inventory'])->group(function () {

        /*
        |------------------------------------------------------------------
        | Localisations (bâtiments regroupant des sites)
        |------------------------------------------------------------------
        */
        Route::prefix('localisations')->name('localisations.')->group(function () {
            Route::get('/', ListeLocalisations::class)->name('index');

            // Doit être avant les routes avec paramètres
            Route::get('/export/excel', [LocalisationController::class, 'exportExcel'])->middleware('admin')->name('export-excel');
            Route::post('/imprimer-etiquettes', [LocalisationController::class, 'imprimerEtiquettes'])->middleware('admin')->name('imprimer-etiquettes');
            Route::get('/create', FormLocalisation::class)->middleware('admin')->name('create');
            Route::get('/{localisation}/qr-code', [LocalisationController::class, 'generateQRCode'])->middleware('admin')->name('qr-code');
            Route::get('/{localisation}/etiquette', [LocalisationController::class, 'downloadEtiquette'])->middleware('admin')->name('etiquette');
            Route::get('/{localisation}/edit', FormLocalisation::class)->middleware('admin')->name('edit');

            // Doit être en dernier car il capture toutes les routes
            Route::get('/{localisation}', DetailLocalisation::class)->name('show');
        });

        /*
        |------------------------------------------------------------------
        | Sites (bureaux, services, salles) et leur responsable
        |------------------------------------------------------------------
        */
        Route::prefix('sites')->name('sites.')->middleware('admin')->group(function () {
            Route::get('/', ListeSites::class)->name('index');
            Route::get('/create', FormSite::class)->name('create');
            Route::get('/{site}/etiquettes', [EtiquetteController::class, 'apercu'])->name('etiquettes.apercu');
            Route::post('/{site}/etiquettes/pdf', [EtiquetteController::class, 'generer'])->name('etiquettes.pdf');
            Route::post('/{site}/etiquettes/{generation}/confirmer', [EtiquetteController::class, 'confirmerImpression'])->name('etiquettes.confirmer');
            Route::get('/{site}', DetailSite::class)->name('show');
            Route::get('/{site}/edit', FormSite::class)->name('edit');
        });

        /*
        |------------------------------------------------------------------
        | Catégories (et leur imputation comptable)
        |------------------------------------------------------------------
        */
        Route::prefix('categories')->name('categories.')->middleware('admin')->group(function () {
            Route::get('/', ListeCategories::class)->name('index');
            Route::get('/create', FormCategorie::class)->name('create');
            Route::get('/{categorie}/edit', FormCategorie::class)->name('edit');
        });

        /*
        |------------------------------------------------------------------
        | Désignations
        |------------------------------------------------------------------
        */
        Route::prefix('designations')->name('designations.')->middleware('admin')->group(function () {
            Route::get('/', ListeDesignations::class)->name('index');
            Route::get('/create', FormDesignation::class)->name('create');
            Route::get('/{designation}/edit', FormDesignation::class)->name('edit');
        });

        /*
        |------------------------------------------------------------------
        | QR Codes des Sites (Pour PWA Scanner)
        |------------------------------------------------------------------
        */
        Route::prefix('qrcodes/sites')->name('qrcodes.')->middleware('admin')->group(function () {
            Route::get('/', [QRCodeSiteController::class, 'index'])->name('sites');
            Route::get('/pdf', [QRCodeSiteController::class, 'downloadPdf'])->name('sites.pdf');
            Route::post('/print', [QRCodeSiteController::class, 'printSelected'])->name('print-selected');
            Route::get('/{site}/generate', [QRCodeSiteController::class, 'generate'])->name('generate');
        });

        /*
        |------------------------------------------------------------------
        | Biens (immobilisations)
        |------------------------------------------------------------------
        */
        Route::prefix('biens')->name('biens.')->group(function () {
            Route::get('/', ListeBiens::class)->name('index');
            Route::get('/create', FormBien::class)->middleware('admin')->name('create');
            Route::get('/transfert', TransfertBien::class)->middleware('admin')->name('transfert');
            Route::get('/transfert/historique', HistoriqueTransferts::class)->middleware('admin')->name('transfert.historique');

            // Doivent être avant /{bien}
            // Impression groupée par site (33 étiquettes par page A4)
            // Impression de toutes les étiquettes, groupées par site (un seul fichier)
            // Impression groupée par intervalle d'identifiants (33 étiquettes par page A4)
            Route::get('/export/excel', [BienController::class, 'exportExcel'])->middleware('admin')->name('export-excel');
            Route::get('/import/modele', [BienController::class, 'modeleImport'])->middleware('admin')->name('import.modele');

            Route::get('/{bien}/edit', FormBien::class)->middleware('admin')->name('edit');
            Route::get('/{bien}', DetailBien::class)->name('show');
            // Étiquette avec code-barres généré côté client
            // Étiquette avec code-barres de la base
        });

        /*
        |------------------------------------------------------------------
        | Inventaires
        |------------------------------------------------------------------
        */
        Route::prefix('inventaires')->name('inventaires.')->group(function () {
            Route::get('/', ListeInventaires::class)->name('index');
            Route::get('/create', DemarrerInventaire::class)->middleware('admin')->name('create');
            Route::get('/exports/{exportRequest}/download', [InventaireController::class, 'downloadExport'])
                ->name('exports.download');
            Route::get('/{inventaire}', DashboardInventaire::class)->name('show');
            Route::get('/{inventaire}/rapport', RapportInventaire::class)->name('rapport');
            Route::get('/{inventaire}/report', ReportInventaire::class)->middleware('admin')->name('report');
            Route::post('/{inventaire}/export-pdf', [InventaireController::class, 'exportPDF'])->name('export-pdf');
            Route::post('/{inventaire}/export-excel', [InventaireController::class, 'exportExcel'])->name('export-excel');
            Route::post('/{inventaire}/cloturer', [InventaireController::class, 'cloturer'])->middleware('admin')->name('cloturer');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Routes avec Middleware 'admin' (Administrateur uniquement)
    |----------------------------------------------------------------------
    */
    Route::middleware(['admin'])->group(function () {
        // Sorties d'immobilisations (réforme, rebut, cession, perte, vol…)
        Route::prefix('sorties')->name('sorties.')->group(function () {
            Route::get('/', ListeSorties::class)->name('index');
            Route::get('/export', [SortieController::class, 'export'])->name('export');
            Route::get('/{sortie}', DetailSortie::class)->name('show');
            Route::get('/{sortie}/pv', [SortieController::class, 'pv'])->name('pv');
        });
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', ListeUsers::class)->name('index');
            Route::get('/create', FormUser::class)->name('create');
            Route::get('/roles', GestionRoles::class)->name('roles');
            Route::get('/{user}/edit', FormUser::class)->name('edit');
        });
    });
});
