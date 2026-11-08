<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="api-base-url" content="{{ url('/api/v1') }}">
    
    <!-- PWA Configuration -->
    <meta name="theme-color" content="#383f7b" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#312E81" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Inventaire mobile">
    
    <!-- Android Chrome -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Inventaire mobile">
    
    <title>Inventaire mobile</title>
    
    <!-- Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icons/icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/icons/icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="167x167" href="{{ asset('images/icons/icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('images/icons/icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('images/icons/icon-192x192.png') }}">
    
    @vite(['resources/css/app.css', 'resources/js/pwa-dependencies.js'])
    
    <!-- Custom Styles -->
    <style>
        /* Support des Safe Areas iOS (notch) */
        :root {
            --safe-area-inset-top: env(safe-area-inset-top, 0px);
            --safe-area-inset-right: env(safe-area-inset-right, 0px);
            --safe-area-inset-bottom: env(safe-area-inset-bottom, 0px);
            --safe-area-inset-left: env(safe-area-inset-left, 0px);
        }
        
        /* Smooth scroll iOS */
        * {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Empêcher le double-tap zoom sur iOS */
        button, a, input, select {
            touch-action: manipulation;
        }
        
        /* Taille minimale tactile (44x44px pour iOS) */
        .touch-target {
            min-width: 44px;
            min-height: 44px;
        }
        
        /* Feedback visuel au toucher */
        .touch-feedback:active {
            opacity: 0.7;
            transform: scale(0.98);
        }
        
        /* Scanner container optimisé pour mobile */
        #scanner-container video {
            width: 100% !important;
            height: auto !important;
            object-fit: cover;
        }

        #barcode-scanner-container video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
        }

        .scan-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(
                to bottom,
                rgba(0, 0, 0, 0.22),
                rgba(0, 0, 0, 0.1) 20%,
                rgba(0, 0, 0, 0.1) 80%,
                rgba(0, 0, 0, 0.22)
            );
        }

        .scan-reticle {
            position: relative;
            width: 68%;
            aspect-ratio: 1 / 1;
            max-width: 240px;
            border: 2px solid rgba(255, 255, 255, 0.65);
            border-radius: 1rem;
            box-shadow: 0 0 0 999px rgba(0, 0, 0, 0.18);
            background: transparent;
        }

        .scan-reticle::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 2px;
            background: rgba(16, 185, 129, 0.9);
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.9);
            transform: translateY(-50%);
            animation: scan-line 2.2s ease-in-out infinite;
        }

        @keyframes scan-line {
            0% { transform: translateY(-48%); opacity: 0.7; }
            50% { transform: translateY(48%); opacity: 1; }
            100% { transform: translateY(-48%); opacity: 0.7; }
        }

        .scan-corner {
            position: absolute;
            width: 26px;
            height: 26px;
            border-color: #10b981;
            border-style: solid;
            border-width: 0;
        }

        .scan-corner.tl { top: -2px; left: -2px; border-top-width: 4px; border-left-width: 4px; border-top-left-radius: 0.75rem; }
        .scan-corner.tr { top: -2px; right: -2px; border-top-width: 4px; border-right-width: 4px; border-top-right-radius: 0.75rem; }
        .scan-corner.bl { bottom: -2px; left: -2px; border-bottom-width: 4px; border-left-width: 4px; border-bottom-left-radius: 0.75rem; }
        .scan-corner.br { bottom: -2px; right: -2px; border-bottom-width: 4px; border-right-width: 4px; border-bottom-right-radius: 0.75rem; }

        .scan-overlay-hint {
            position: absolute;
            bottom: 0.6rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.72rem;
            line-height: 1rem;
            color: rgba(255, 255, 255, 0.92);
            background: rgba(0, 0, 0, 0.35);
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            white-space: nowrap;
        }
        
        /* Prévenir le pull-to-refresh sur certaines zones */
        .no-pull-refresh {
            overscroll-behavior-y: contain;
        }

        #login-spinner.hidden {
            display: none !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased overflow-x-hidden no-pull-refresh">

    <!-- Container principal -->
    <div id="app" class="min-h-screen flex flex-col">
        
        <!-- Header avec support Safe Area iOS -->
        <header id="app-header" class="hidden bg-indigo-600 text-white shadow-md fixed top-0 left-0 right-0 z-50" 
                style="padding-top: var(--safe-area-inset-top);">
            <div class="px-4 py-3" style="padding-left: max(1rem, var(--safe-area-inset-left)); padding-right: max(1rem, var(--safe-area-inset-right));">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        <div>
                            <h1 class="text-lg font-bold">Inventaire mobile</h1>
                            <p class="text-xs text-indigo-200" id="user-name">Agent</p>
                        </div>
                    </div>
                    <button id="menu-btn" class="touch-target touch-feedback p-2 rounded-lg hover:bg-indigo-700 active:bg-indigo-800 transition" aria-label="Menu">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- Menu latéral (drawer) avec Safe Area -->
        <div id="menu-drawer" class="fixed inset-0 z-40 hidden">
            <div id="menu-overlay" class="absolute inset-0 bg-black bg-opacity-50 touch-feedback"></div>
            <div class="absolute right-0 top-0 bottom-0 w-80 max-w-[85vw] bg-white shadow-xl transform transition-transform" 
                 style="padding-top: var(--safe-area-inset-top); padding-bottom: var(--safe-area-inset-bottom);">
                <div class="p-5" style="padding-right: max(1.25rem, var(--safe-area-inset-right));">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Menu</h2>
                        <button id="close-menu" class="touch-target touch-feedback p-2 rounded-lg hover:bg-gray-100 active:bg-gray-200" aria-label="Fermer le menu">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <p class="text-sm text-gray-600">Connecté en tant que</p>
                        <p class="font-semibold text-gray-800" id="menu-user-name">-</p>
                    </div>
                    
                    <nav class="space-y-2">
                        <button id="nav-logout" class="touch-target touch-feedback flex items-center space-x-3 p-4 rounded-lg hover:bg-red-50 active:bg-red-100 text-red-600 w-full transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span class="text-base font-medium">Déconnexion</span>
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Contenu principal avec Safe Area -->
        <main id="main-content" class="flex-1 pb-6 px-4" 
              style="padding-top: calc(5rem + var(--safe-area-inset-top)); 
                     padding-bottom: max(1.5rem, var(--safe-area-inset-bottom));
                     padding-left: max(1rem, var(--safe-area-inset-left));
                     padding-right: max(1rem, var(--safe-area-inset-right));">

            <div id="connectivity-status"
                 class="mb-4 rounded-lg border px-3 py-2 text-center text-xs font-semibold"
                 role="status"
                 aria-live="polite">
                Vérification de la connexion…
            </div>
            
            <!-- Vue transitoire: évite d'afficher brièvement la connexion pendant la restauration de session -->
            <div id="view-loading" class="max-w-sm mx-auto" role="status" aria-live="polite">
                <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100 text-center">
                    <svg class="animate-spin mx-auto h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-sm font-semibold text-gray-700">Ouverture de l’inventaire…</p>
                </div>
            </div>

            <!-- Vue: Login -->
            <div id="view-login" class="hidden max-w-sm mx-auto">
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl mb-4 shadow-lg">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-1">Inventaire mobile</h1>
                        <p class="text-gray-500 text-sm uppercase tracking-wide">Immobilisations</p>
                    </div>
                    
                    <form id="login-form" class="space-y-5">
                        <div>
                            <label for="login-users" class="block text-sm font-semibold text-gray-700 mb-2">Nom d'utilisateur</label>
                            <input type="text" id="login-users" required 
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        
                        <div>
                            <label for="login-mdp" class="block text-sm font-semibold text-gray-700 mb-2">Mot de passe</label>
                            <input type="password" id="login-mdp" required 
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        
                        <div id="login-error" class="hidden p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                            <p class="text-sm text-red-700"></p>
                        </div>
                        
                        <button type="submit" id="login-btn" class="touch-target touch-feedback w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white py-4 rounded-xl font-semibold text-base hover:from-indigo-700 hover:to-indigo-800 active:from-indigo-800 active:to-indigo-900 transition-all shadow-lg">
                            <span id="login-btn-text">Se connecter</span>
                            <svg id="login-spinner" class="hidden animate-spin ml-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Vue: aucun inventaire disponible -->
            <div id="view-no-inventory" class="hidden max-w-sm mx-auto">
                <div class="rounded-2xl border border-amber-200 bg-white p-6 text-center shadow-sm">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-4 0a2 2 0 114 0m-4 0a2 2 0 004 0m-5 8h4" />
                        </svg>
                    </div>
                    <h2 class="mt-4 text-xl font-bold text-gray-900">Aucun inventaire actif</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        Le scan sera disponible lorsqu’un administrateur aura préparé et lancé un inventaire pour votre équipe.
                    </p>
                    <button id="retry-inventory-btn" type="button" class="touch-target touch-feedback mt-6 w-full rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Vérifier à nouveau
                    </button>
                </div>
            </div>

            <!-- Vue: Scanner Principal -->
            <div id="view-scanner" class="hidden">

                <!-- Sites assignés à l'agent -->
                <section id="mes-sites" class="hidden bg-white rounded-xl shadow-sm mb-4 border border-gray-100" aria-labelledby="mes-sites-titre">
                    <div class="flex items-center justify-between px-4 pt-3 pb-2">
                        <h2 id="mes-sites-titre" class="text-sm font-semibold text-gray-900">Mes sites</h2>
                        <span id="mes-sites-resume" class="text-xs text-gray-500"></span>
                    </div>
                    <p id="mes-sites-prechargement" class="hidden px-4 pb-2 text-xs text-gray-500" aria-live="polite"></p>
                    <div id="mes-sites-list" class="max-h-72 overflow-y-auto px-2 pb-2"></div>
                </section>

                <!-- Onglets: Scan / Saisie manuelle emplacement -->
                <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden border border-gray-100">
                    <div class="grid grid-cols-2">
                        <button id="tab-emp-scan" class="touch-target touch-feedback px-4 py-3 text-sm font-semibold bg-indigo-600 text-white">
                            Scanner QR
                        </button>
                        <button id="tab-emp-manual" class="touch-target touch-feedback px-4 py-3 text-sm font-semibold bg-white text-gray-700 border-l border-gray-100">
                            Saisie code
                        </button>
                    </div>
                </div>

                <!-- Mode Scan emplacement -->
                <div id="emp-mode-scan">
                    <div id="instruction-scan-emplacement" class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded mb-4">
                        <p class="text-blue-800 font-medium">
                            <x-icon name="location-marker" class="inline-block w-4 h-4 mr-1 align-[-3px]" /> Scannez le QR code sur la porte de l'emplacement
                        </p>
                    </div>

                    <div class="bg-black rounded-2xl overflow-hidden mb-4 shadow-lg mx-auto w-full max-w-[360px] aspect-square">
                        <div id="scanner-container" class="w-full h-full relative"></div>
                    </div>

                    <div id="scanner-status" class="hidden p-4 text-center bg-gray-900 rounded-xl">
                        <button id="start-scanner-btn" class="touch-target touch-feedback bg-indigo-600 text-white px-6 py-3 rounded-lg text-base font-semibold hover:bg-indigo-700 active:bg-indigo-800 transition shadow-md">
                            <x-icon name="camera" class="inline-block w-5 h-5 mr-1 align-[-4px]" /> Activer la caméra
                        </button>
                    </div>
                </div>

                <!-- Mode Saisie manuelle emplacement -->
                <div id="emp-mode-manual" class="hidden">
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded mb-4">
                        <p class="text-sm text-amber-800 font-medium"><x-icon name="pencil-alt" class="inline-block w-4 h-4 mr-1 align-[-3px]" /> Saisissez le code ou l'ID de l'emplacement (ex: EMP-12 ou 12)</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <label for="manual-emp-input" class="block text-sm font-semibold text-gray-700 mb-2">Code / ID emplacement</label>
                        <input
                            id="manual-emp-input"
                            type="text"
                            placeholder="Ex: EMP-12 ou 12"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition mb-3"
                        >
                        <button id="manual-emp-submit" class="touch-target touch-feedback w-full bg-indigo-600 text-white py-3 rounded-xl text-sm font-semibold hover:bg-indigo-700 active:bg-indigo-800 transition shadow-md">
                            Valider l'emplacement
                        </button>
                    </div>
                </div>
            </div>

            <!-- Vue: Liste des Biens d'un Emplacement -->
            <div id="view-emplacement-biens" class="hidden">
                
                <!-- Header Emplacement -->
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl shadow-lg p-5 mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex-1">
                            <p class="text-xs text-indigo-200 mb-1">Emplacement en cours</p>
                            <h3 id="emplacement-nom" class="text-lg font-bold">-</h3>
                            <p id="emplacement-details" class="text-xs text-indigo-200 mt-1">-</p>
                        </div>
                        <button id="btn-terminer-emplacement" class="touch-target touch-feedback bg-white text-indigo-600 px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-indigo-50 active:bg-indigo-100 shadow-md">
                            <x-icon name="check-circle" class="inline-block w-5 h-5 mr-1 align-[-4px]" /> Terminer
                        </button>
                    </div>
                    
                    <!-- Progression -->
                    <div class="mt-3">
                        <div class="flex justify-between text-xs mb-1.5">
                            <span id="progress-text" class="text-indigo-100">0/0 biens scannés</span>
                            <span id="progress-percent" class="text-indigo-100 font-semibold">0%</span>
                        </div>
                        <div class="w-full bg-indigo-800 rounded-full h-2">
                            <div id="progress-bar" class="bg-white h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Onglets: Scan / Saisie manuelle -->
                <div class="bg-white rounded-xl shadow-sm mb-4 overflow-hidden border border-gray-100">
                    <div class="grid grid-cols-2">
                        <button id="tab-bien-scan" class="touch-target touch-feedback px-4 py-3 text-sm font-semibold bg-indigo-600 text-white">
                            Scanner QR
                        </button>
                        <button id="tab-bien-manual" class="touch-target touch-feedback px-4 py-3 text-sm font-semibold bg-white text-gray-700 border-l border-gray-100">
                            Saisie N° ordre
                        </button>
                    </div>
                    <label for="toggle-scan-rapide" class="flex cursor-pointer items-center gap-3 border-t border-gray-100 px-4 py-3">
                        <input id="toggle-scan-rapide" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-gray-800">Scan rapide</span>
                            <span class="block text-xs text-gray-500">Les biens attendus sont enregistrés en « Bon état » sans confirmation. Les biens non attendus restent à confirmer.</span>
                        </span>
                    </label>
                </div>

                <!-- Mode Scan -->
                <div id="bien-mode-scan" class="mb-4">
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded mb-4">
                        <p class="text-sm text-green-800 font-medium"><x-icon name="qrcode" class="inline-block w-4 h-4 mr-1 align-[-3px]" /> Scannez les QR codes des étiquettes des biens</p>
                    </div>

                    <div class="bg-black rounded-2xl overflow-hidden mb-4 shadow-lg mx-auto w-full max-w-[360px] aspect-square">
                        <div id="barcode-scanner-container" class="w-full h-full relative">
                            <div class="scan-overlay">
                                <div class="scan-reticle">
                                    <span class="scan-corner tl"></span>
                                    <span class="scan-corner tr"></span>
                                    <span class="scan-corner bl"></span>
                                    <span class="scan-corner br"></span>
                                </div>
                                <span class="scan-overlay-hint">Placez le QR dans le cadre</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mode Saisie manuelle -->
                <div id="bien-mode-manual" class="hidden mb-4">
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded mb-4">
                        <p class="text-sm text-amber-800 font-medium"><x-icon name="pencil-alt" class="inline-block w-4 h-4 mr-1 align-[-3px]" /> Saisissez le numéro d'ordre si le QR est difficile à lire</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <label for="manual-num-ordre-input" class="block text-sm font-semibold text-gray-700 mb-2">Numéro d'ordre</label>
                        <input
                            id="manual-num-ordre-input"
                            type="text"
                            inputmode="numeric"
                            placeholder="Ex: 12345"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition mb-3"
                        >
                        <button id="manual-num-ordre-submit" class="touch-target touch-feedback w-full bg-indigo-600 text-white py-3 rounded-xl text-sm font-semibold hover:bg-indigo-700 active:bg-indigo-800 transition shadow-md">
                            Valider le numéro
                        </button>
                    </div>
                </div>

                <!-- Liste des Biens Attendus -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
                    <div class="p-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-800">Biens attendus</h3>
                        <p class="text-xs text-gray-600" id="biens-count">0 bien(s)</p>
                    </div>
                    <div id="biens-list" class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        <!-- Liste dynamique -->
                    </div>
                </div>
            </div>

            <!-- Vue: Résultats et Écarts -->
            <div id="view-resultats" class="hidden">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Résultats du Scan</h2>
                    
                    <!-- Statistiques -->
                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <div class="bg-green-50 p-4 rounded-lg">
                            <p class="text-xs text-green-600">Scannés</p>
                            <p id="stat-scannes" class="text-2xl font-bold text-green-700">0</p>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <p class="text-xs text-red-600">Manquants</p>
                            <p id="stat-manquants" class="text-2xl font-bold text-red-700">0</p>
                        </div>
                        <div class="bg-amber-50 p-4 rounded-lg">
                            <p class="text-xs text-amber-600">Déplacés</p>
                            <p id="stat-deplaces" class="text-2xl font-bold text-amber-700">0</p>
                        </div>
                    </div>

                    <!-- Taux de conformité -->
                    <div class="mb-6">
                        <p class="text-sm text-gray-600 mb-2">Taux de conformité</p>
                        <div class="flex items-center">
                            <div class="flex-1 bg-gray-200 rounded-full h-4">
                                <div id="conformite-bar" class="bg-green-500 h-4 rounded-full" style="width: 0%"></div>
                            </div>
                            <span id="conformite-text" class="ml-3 font-semibold text-gray-700">0%</span>
                        </div>
                    </div>

                    <!-- Biens Manquants -->
                    <div id="section-manquants" class="hidden mb-6">
                        <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2"><x-icon name="x-circle" class="w-5 h-5 text-red-500" /> Biens manquants</h3>
                        <div id="list-manquants" class="space-y-2">
                            <!-- Liste dynamique -->
                        </div>
                    </div>

                    <!-- Biens Déplacés -->
                    <div id="section-deplaces" class="hidden mb-6">
                        <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2"><x-icon name="switch-horizontal" class="w-5 h-5 text-amber-500" /> Biens déplacés</h3>
                        <p class="text-xs text-gray-500 mb-2">Biens trouvés ici mais enregistrés dans un autre emplacement</p>
                        <div id="list-deplaces" class="space-y-2">
                            <!-- Liste dynamique -->
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button id="btn-nouveau-scan" class="touch-target touch-feedback flex-1 bg-indigo-600 text-white py-4 rounded-xl text-base font-bold hover:bg-indigo-700 active:bg-indigo-800 shadow-lg">
                            <x-icon name="refresh" class="inline-block w-5 h-5 mr-1 align-[-4px]" /> Nouveau scan
                        </button>
                    </div>
                </div>
            </div>

        </main>

        <!-- Toast notifications avec Safe Area -->
        <div id="toast-container" class="fixed z-50 space-y-2 w-full px-4 max-w-md left-1/2 transform -translate-x-1/2" 
             style="top: calc(5rem + var(--safe-area-inset-top));"></div>

        <!-- Modal de confirmation avant de terminer un site -->
        <div id="modal-terminer" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true" aria-labelledby="modal-terminer-titre">
            <div id="modal-terminer-overlay" class="absolute inset-0 bg-black bg-opacity-50"></div>
            <div class="absolute inset-x-4 top-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-2xl p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 mb-1" id="modal-terminer-titre">Terminer ce site ?</h3>
                <p class="text-sm text-gray-600 mb-4" id="modal-terminer-site">-</p>

                <dl class="grid grid-cols-3 gap-2 mb-4 text-center">
                    <div class="rounded-xl bg-green-50 p-3">
                        <dt class="text-[11px] font-medium text-green-700">Scannés</dt>
                        <dd class="text-xl font-bold text-green-800" id="modal-terminer-scannes">0</dd>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3">
                        <dt class="text-[11px] font-medium text-amber-700">Non attendus</dt>
                        <dd class="text-xl font-bold text-amber-800" id="modal-terminer-non-attendus">0</dd>
                    </div>
                    <div class="rounded-xl bg-red-50 p-3">
                        <dt class="text-[11px] font-medium text-red-700">Non scannés</dt>
                        <dd class="text-xl font-bold text-red-800" id="modal-terminer-manquants">0</dd>
                    </div>
                </dl>

                <p id="modal-terminer-alerte" class="hidden mb-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"></p>
                <p class="mb-5 text-xs text-gray-500">Une fois terminé, seul un administrateur pourra rouvrir ce site.</p>

                <div class="flex gap-3">
                    <button type="button" id="modal-terminer-annuler" class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition touch-target">
                        Continuer le scan
                    </button>
                    <button type="button" id="modal-terminer-confirmer" class="flex-1 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition touch-target">
                        Terminer
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal État du bien (après scan) -->
        <div id="modal-etat-bien" class="fixed inset-0 z-[60] hidden">
            <div id="modal-etat-overlay" class="absolute inset-0 bg-black bg-opacity-50 touch-feedback"></div>
            <div class="absolute inset-x-4 top-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-2xl p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 mb-3" id="modal-etat-titre">État du bien</h3>
                <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Désignation</p>
                    <p class="text-base font-semibold text-gray-900" id="modal-etat-designation">-</p>
                    <p class="mt-0.5 hidden text-sm text-gray-600" id="modal-etat-descriptif"></p>
                    <p class="mt-2 font-mono text-sm font-semibold text-indigo-700" id="modal-etat-code"></p>
                    <p class="mt-0.5 text-xs text-gray-500" id="modal-etat-details"></p>
                </div>
                
                <div class="space-y-3 mb-4">
                    <label class="block text-sm font-medium text-gray-700">État constaté</label>
                    <div id="modal-etat-buttons" class="grid grid-cols-2 gap-2">
                        <!-- Boutons chargés dynamiquement depuis API /etats -->
                    </div>
                </div>

                <!-- Zone photo (visible si Défectueux) -->
                <div id="modal-etat-photo-section" class="hidden mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><x-icon name="camera" class="inline-block w-4 h-4 mr-1 align-[-3px]" /> Photo du bien défectueux (obligatoire)</label>
                    <div class="space-y-2">
                        <input type="file" id="modal-etat-photo-input" accept="image/*" capture="environment" 
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <div id="modal-etat-photo-preview" class="hidden mt-2">
                            <img id="modal-etat-photo-img" src="" alt="Aperçu" class="rounded-lg max-h-32 object-cover border border-gray-200">
                            <button type="button" id="modal-etat-photo-retake" class="mt-2 text-sm text-red-600 font-medium">Reprendre la photo</button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" id="modal-etat-annuler" class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition">
                        Annuler
                    </button>
                    <button type="button" id="modal-etat-confirmer" class="flex-1 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition touch-target" disabled>
                        Confirmer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script principal -->
    <script src="app-v2.js"></script>
    
    <script>
        // Enregistrement du Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./service-worker.js')
                    .then(registration => {
                        console.log('Service Worker enregistré');
                    })
                    .catch(error => {
                        console.error('Erreur Service Worker:', error);
                    });
            });
        }
    </script>
</body>
</html>
