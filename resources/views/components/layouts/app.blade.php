<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ 
    sidebarOpen: window.innerWidth >= 768, 
    profileOpen: false,
    isDesktop: window.innerWidth >= 768,
    init() {
        this.isDesktop = window.innerWidth >= 768;
        this.sidebarOpen = this.isDesktop;
        window.addEventListener('resize', () => {
            this.isDesktop = window.innerWidth >= 768;
            this.sidebarOpen = this.isDesktop;
        });
        document.addEventListener('livewire:navigating', () => {
            if (!this.isDesktop) this.sidebarOpen = false;
        });
    }
}" :class="{ 'overflow-hidden': sidebarOpen && !isDesktop }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- PWA Meta Tags --}}
    <meta name="theme-color" content="#383f7b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Inventaire">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="Gestion des immobilisations et des inventaires">

    @php
        $pageTitle = match (true) {
            request()->routeIs('dashboard') => 'Tableau de bord',
            request()->routeIs('biens.create') => 'Ajouter une immobilisation',
            request()->routeIs('biens.edit') => 'Modifier une immobilisation',
            request()->routeIs('biens.show') => 'Détail de l’immobilisation',
            request()->routeIs('biens.transfert.historique') => 'Historique des transferts',
            request()->routeIs('biens.transfert') => 'Transférer une immobilisation',
            request()->routeIs('biens.*') => 'Immobilisations',
            request()->routeIs('inventaires.create') => 'Nouvel inventaire',
            request()->routeIs('inventaires.rapport') => 'Rapport d’inventaire',
            request()->routeIs('inventaires.report') => 'Report de l’inventaire',
            request()->routeIs('sorties.*') => 'Sorties d’immobilisations',
            request()->routeIs('inventaires.*') => 'Inventaires',
            request()->routeIs('localisations.*') => 'Localisations',
            request()->routeIs('sites.etiquettes.*') => 'Étiquettes du site',
            request()->routeIs('sites.*') => 'Sites',
            request()->routeIs('qrcodes.*') => 'QR codes des sites',
            request()->routeIs('categories.*') => 'Catégories',
            request()->routeIs('designations.*') => 'Désignations',
            request()->routeIs('users.*') => 'Utilisateurs',
            request()->routeIs('profil') => 'Mon profil',
            default => 'Gestion des immobilisations',
        };
    @endphp
    <title>Gestion des immobilisations - {{ $pageTitle }}</title>
    
    {{-- PWA Manifest --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    
    {{-- PWA Icons --}}
    <link rel="icon" type="image/png" href="{{ asset('images/icons/icon-192x192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192x192.png') }}">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- Alpine.js est déjà inclus dans Livewire 3, ne pas le charger séparément --}}

    <style>
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased bg-secondary-50">
    {{-- Barre de progression affichée pendant les requêtes Livewire un peu longues --}}
    <div id="livewire-progress" class="pointer-events-none fixed inset-x-0 top-0 z-[200] h-0.5 overflow-hidden opacity-0 transition-opacity duration-150" role="progressbar" aria-label="Chargement en cours" aria-hidden="true">
        <div class="h-full w-1/3 bg-indigo-500" style="animation: livewire-progress 1s ease-in-out infinite"></div>
    </div>
    <style>
        @keyframes livewire-progress { 0% { transform: translateX(-100%); } 100% { transform: translateX(300%); } }
    </style>

    {{--
        Notifications ponctuelles. Depuis Livewire : $this->dispatch('toast', message: '…', type: 'success').
        Depuis JavaScript : window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } })).
    --}}
    <div
        x-data="{
            toasts: [],
            ajouter(detail) {
                const toast = { id: Date.now() + Math.random(), message: detail?.message ?? '', type: detail?.type ?? 'info' };
                this.toasts.push(toast);
                setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== toast.id), toast.type === 'error' ? 6000 : 3500);
            },
        }"
        x-on:toast.window="ajouter($event.detail)"
        class="pointer-events-none fixed bottom-4 right-4 z-[150] flex w-full max-w-sm flex-col gap-2 px-4 sm:px-0"
        aria-live="polite"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition.opacity
                class="pointer-events-auto rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
                :class="{
                    'bg-green-600': toast.type === 'success',
                    'bg-red-600': toast.type === 'error',
                    'bg-amber-500': toast.type === 'warning',
                    'bg-gray-800': ! ['success', 'error', 'warning'].includes(toast.type),
                }"
                :role="toast.type === 'error' ? 'alert' : 'status'"
                x-text="toast.message"
            ></div>
        </template>
    </div>
    <a href="#contenu-principal" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-indigo-800 focus:shadow-lg">
        Aller au contenu principal
    </a>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="navigation-principale"
            x-show="sidebarOpen || isDesktop"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-indigo-800 text-white flex flex-col"
            :class="{ 'translate-x-0': isDesktop || sidebarOpen }"
            x-cloak
        >
            <!-- Logo -->
            <div class="flex items-center justify-between h-16 px-6 bg-indigo-900 border-b border-indigo-700">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-white">
                        <x-icon name="cube" class="w-5 h-5" />
                    </div>
                    <div class="flex flex-col leading-tight">
                        <span class="font-bold text-lg">Inventaire</span>
                        <span class="text-[10px] text-indigo-300 uppercase tracking-wider">Immobilisations</span>
                    </div>
                </div>
                <button type="button" @click="sidebarOpen = false" class="md:hidden rounded-lg p-2 text-gray-300 hover:bg-indigo-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-white" aria-label="Fermer le menu principal">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            @php
                $estAdmin = auth()->user()?->isAdmin() ?? false;
                $peutInventorier = auth()->user()?->canManageInventaire() ?? false;
                $groupeActif = match (true) {
                    request()->routeIs('biens.*', 'sorties.*') => 'immobilisations',
                    request()->routeIs('localisations.*', 'sites.*', 'categories.*', 'designations.*', 'qrcodes.*') => 'referentiel',
                    default => '',
                };
                $lien = 'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70';
                $lienInactif = 'text-indigo-100 hover:bg-indigo-700 hover:text-white';
                $lienActif = 'bg-indigo-700 text-white shadow-inner';
                $sousLien = 'flex items-center gap-2.5 pl-11 pr-3 py-2 rounded-lg text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70';
                $sousLienInactif = 'text-indigo-200 hover:bg-indigo-700/70 hover:text-white';
                $sousLienActif = 'bg-indigo-700/70 text-white font-medium';
            @endphp
            <nav class="flex-1 overflow-y-auto py-4 px-3" aria-label="Navigation principale" x-data="{ openMenu: '{{ $groupeActif }}' }">
                <ul class="space-y-1">
                    <li>
                        <a href="{{ route('dashboard') }}" wire:navigate
                           @class([$lien, request()->routeIs('dashboard') ? $lienActif : $lienInactif])
                           @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                            <x-icon name="home" />
                            <span>Tableau de bord</span>
                        </a>
                    </li>

                    @if($peutInventorier)
                        <li>
                            <a href="{{ route('inventaires.index') }}" wire:navigate
                               @class([$lien, request()->routeIs('inventaires.*') ? $lienActif : $lienInactif])
                               @if(request()->routeIs('inventaires.*')) aria-current="page" @endif>
                                <x-icon name="clipboard-list" />
                                <span>Inventaires</span>
                            </a>
                        </li>

                        @if($estAdmin)
                            <li>
                                <button type="button" @click="openMenu = openMenu === 'immobilisations' ? '' : 'immobilisations'"
                                        @class([$lien, 'w-full', $groupeActif === 'immobilisations' ? 'text-white' : $lienInactif, 'hover:bg-indigo-700'])
                                        :aria-expanded="(openMenu === 'immobilisations').toString()"
                                        aria-controls="navigation-immobilisations">
                                    <x-icon name="cube" />
                                    <span class="flex-1 text-left">Immobilisations</span>
                                    <x-icon name="chevron-down" class="w-4 h-4 transition-transform" ::class="{ 'rotate-180': openMenu === 'immobilisations' }" />
                                </button>
                                <ul id="navigation-immobilisations" x-show="openMenu === 'immobilisations'" x-collapse x-cloak class="mt-1 space-y-0.5">
                                    @foreach([
                                        ['route' => 'biens.index', 'actif' => request()->routeIs('biens.index', 'biens.show'), 'icone' => 'view-list', 'libelle' => 'Liste'],
                                        ['route' => 'biens.create', 'actif' => request()->routeIs('biens.create', 'biens.edit'), 'icone' => 'plus', 'libelle' => 'Ajouter'],
                                        ['route' => 'biens.transfert', 'actif' => request()->routeIs('biens.transfert'), 'icone' => 'switch-horizontal', 'libelle' => 'Transférer'],
                                        ['route' => 'biens.transfert.historique', 'actif' => request()->routeIs('biens.transfert.historique'), 'icone' => 'clock', 'libelle' => 'Historique des transferts'],
                                        ['route' => 'sorties.index', 'actif' => request()->routeIs('sorties.*'), 'icone' => 'x-circle', 'libelle' => 'Sorties'],
                                    ] as $entree)
                                        <li>
                                            <a href="{{ route($entree['route']) }}" wire:navigate
                                               @class([$sousLien, $entree['actif'] ? $sousLienActif : $sousLienInactif])
                                               @if($entree['actif']) aria-current="page" @endif>
                                                <x-icon :name="$entree['icone']" class="w-4 h-4" />
                                                <span>{{ $entree['libelle'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>

                            <li>
                                <button type="button" @click="openMenu = openMenu === 'referentiel' ? '' : 'referentiel'"
                                        @class([$lien, 'w-full', $groupeActif === 'referentiel' ? 'text-white' : $lienInactif, 'hover:bg-indigo-700'])
                                        :aria-expanded="(openMenu === 'referentiel').toString()"
                                        aria-controls="navigation-referentiel">
                                    <x-icon name="collection" />
                                    <span class="flex-1 text-left">Référentiel</span>
                                    <x-icon name="chevron-down" class="w-4 h-4 transition-transform" ::class="{ 'rotate-180': openMenu === 'referentiel' }" />
                                </button>
                                <ul id="navigation-referentiel" x-show="openMenu === 'referentiel'" x-collapse x-cloak class="mt-1 space-y-0.5">
                                    @foreach([
                                        ['route' => 'localisations.index', 'actif' => request()->routeIs('localisations.*'), 'icone' => 'location-marker', 'libelle' => 'Localisations', 'navigate' => true],
                                        ['route' => 'sites.index', 'actif' => request()->routeIs('sites.*'), 'icone' => 'office-building', 'libelle' => 'Sites', 'navigate' => true],
                                        ['route' => 'categories.index', 'actif' => request()->routeIs('categories.*'), 'icone' => 'tag', 'libelle' => 'Catégories', 'navigate' => true],
                                        ['route' => 'designations.index', 'actif' => request()->routeIs('designations.*'), 'icone' => 'pencil-alt', 'libelle' => 'Désignations', 'navigate' => true],
                                        ['route' => 'qrcodes.sites', 'actif' => request()->routeIs('qrcodes.*'), 'icone' => 'qrcode', 'libelle' => 'QR codes des sites', 'navigate' => false],
                                    ] as $entree)
                                        <li>
                                            <a href="{{ route($entree['route']) }}" @if($entree['navigate']) wire:navigate @endif
                                               @class([$sousLien, $entree['actif'] ? $sousLienActif : $sousLienInactif])
                                               @if($entree['actif']) aria-current="page" @endif>
                                                <x-icon :name="$entree['icone']" class="w-4 h-4" />
                                                <span>{{ $entree['libelle'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            {{-- Agent : liens directs, sans sous-menu à une seule entrée --}}
                            <li>
                                <a href="{{ route('biens.index') }}" wire:navigate
                                   @class([$lien, request()->routeIs('biens.*') ? $lienActif : $lienInactif])
                                   @if(request()->routeIs('biens.*')) aria-current="page" @endif>
                                    <x-icon name="cube" />
                                    <span>Immobilisations</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('localisations.index') }}" wire:navigate
                                   @class([$lien, request()->routeIs('localisations.*') ? $lienActif : $lienInactif])
                                   @if(request()->routeIs('localisations.*')) aria-current="page" @endif>
                                    <x-icon name="location-marker" />
                                    <span>Localisations</span>
                                </a>
                            </li>
                        @endif
                    @endif

                    @if($estAdmin)
                        <li class="pt-4">
                            <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-indigo-300">Administration</p>
                            <a href="{{ route('users.index') }}" wire:navigate
                               @class([$lien, request()->routeIs('users.*') ? $lienActif : $lienInactif])
                               @if(request()->routeIs('users.*')) aria-current="page" @endif>
                                <x-icon name="users" />
                                <span>Utilisateurs</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </nav>

            <!-- Footer Sidebar -->
            <div class="px-4 py-4 border-t border-indigo-700">
                <div class="text-xs text-indigo-300">
                    Version 1.0.0
                </div>
            </div>
        </aside>

        <!-- Overlay mobile -->
        <div 
            x-show="sidebarOpen"
            @click="sidebarOpen = false"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-600 bg-opacity-75 z-40 md:hidden"
            x-cloak
        ></div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 shadow-sm h-16 flex items-center justify-between px-4 md:px-6 z-30">
                <!-- Left: Hamburger -->
                <div class="flex items-center space-x-4">
                    <button type="button" @click="sidebarOpen = !sidebarOpen" class="md:hidden rounded-lg p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500" :aria-expanded="sidebarOpen" aria-controls="navigation-principale" aria-label="Ouvrir le menu principal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>

                <!-- Right: Notifications + Profile -->
                <div class="flex items-center space-x-4">
                    @auth
                        <!-- Profile Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button type="button"
                                @click="open = !open"
                                class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                :aria-expanded="open"
                                aria-haspopup="menu"
                                aria-label="Ouvrir le menu du profil"
                            >
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold">
                                        {{ strtoupper(substr(auth()->user()->users ?? 'U', 0, 1)) }}
                                    </div>
                                    <div class="hidden md:block text-left">
                                        <div class="text-sm font-medium text-gray-900">{{ auth()->user()->users ?? 'Utilisateur' }}</div>
                                        <div class="text-xs text-gray-500">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ auth()->user()->role === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                                {{ auth()->user()->role_name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div 
                                x-show="open"
                                @click.away="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50 border border-gray-200"
                                role="menu"
                                x-cloak
                            >
                                <a href="{{ route('profil') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Mon profil</a>
                                <hr class="my-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        Déconnexion
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </header>

            <!-- Page Content -->
            <main id="contenu-principal" class="flex flex-1 flex-col overflow-y-auto bg-secondary-50" tabindex="-1">
                <!-- Main Content -->
                <div class="flex-1 p-4 md:p-6">
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <footer class="bg-white border-t border-gray-200 px-4 md:px-6 py-4 mt-auto">
                    <p class="text-sm text-gray-500 text-center">© {{ now()->year }} Gestion des immobilisations</p>
                </footer>
            </main>
        </div>
    </div>

    @livewireScripts(['url' => url('livewire/livewire.js')])
    <script>
        document.querySelector('script[data-update-uri]')?.setAttribute('data-update-uri', @js(url('livewire/update')));

        // Affiche la barre de progression si une requête dure plus de 200 ms.
        document.addEventListener('livewire:init', () => {
            const barre = document.getElementById('livewire-progress');
            let enCours = 0;
            let minuteur = null;

            const mettreAJour = () => {
                clearTimeout(minuteur);
                if (enCours > 0) {
                    minuteur = setTimeout(() => {
                        barre.classList.replace('opacity-0', 'opacity-100');
                        barre.setAttribute('aria-hidden', 'false');
                    }, 200);
                } else {
                    barre.classList.replace('opacity-100', 'opacity-0');
                    barre.setAttribute('aria-hidden', 'true');
                }
            };

            Livewire.hook('commit', ({ succeed, fail }) => {
                enCours++;
                mettreAJour();
                const terminer = () => { enCours = Math.max(0, enCours - 1); mettreAJour(); };
                succeed(terminer);
                fail(terminer);
            });
        });
    </script>
    
    @stack('scripts')
    
    {{-- PWA Service Worker Registration --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                const isLocalDevelopment = ['localhost', '127.0.0.1'].includes(window.location.hostname);

                if (isLocalDevelopment) {
                    navigator.serviceWorker.getRegistrations().then((registrations) => {
                        registrations
                            .filter((registration) => registration.active?.scriptURL.endsWith('/sw.js'))
                            .forEach((registration) => registration.unregister());
                    });

                    return;
                }

                navigator.serviceWorker.register('{{ asset('sw.js') }}')
                    .then((registration) => {
                        console.log('Service Worker enregistré :', registration.scope);
                        setInterval(() => registration.update(), 60000);
                    })
                    .catch((error) => {
                        console.error('Erreur Service Worker :', error);
                    });
            });
        }
    </script>
</body>
</html>
