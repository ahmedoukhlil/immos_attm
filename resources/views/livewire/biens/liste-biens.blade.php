<div>
    @php
        $isAdmin = auth()->user()->canManageOperations();
    @endphp

    <div class="space-y-6">
        {{-- Header avec titre et actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Gestion des Immobilisations</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $showArchived ? 'Immobilisations archivées pouvant être restaurées' : "Liste et gestion de toutes les immobilisations de l'inventaire" }}
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                @if($isAdmin)
                    <button
                        type="button"
                        wire:click="$toggle('showArchived')"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        {{ $showArchived ? 'Retour aux biens actifs' : 'Voir les archives' }}
                    </button>
                @endif

                @if(! $showArchived && $isAdmin)
                    <a
                        href="{{ route('biens.create') }}"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Ajouter une immobilisation
                    </a>
                @endif

                @if(! $showArchived && count($selectedBiens) > 0)
                    <button 
                        wire:click="exportSelected"
                        class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exporter sélection ({{ count($selectedBiens) }})
                    </button>
                @endif

                @if(! $showArchived && $isAdmin && count($selectedBiens) > 0)
                    <button
                        type="button"
                        wire:click="archiverSelection"
                        wire:confirm="Archiver les {{ count($selectedBiens) }} immobilisations sélectionnées ? Elles pourront être restaurées depuis les archives."
                        wire:loading.attr="disabled"
                        wire:target="archiverSelection"
                        class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50">
                        Archiver la sélection ({{ count($selectedBiens) }})
                    </button>
                @endif

                @if(! $showArchived)
                    <a
                        href="{{ route('biens.export-excel') }}"
                        class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Exporter en Excel
                    </a>
                @endif

            </div>
        </div>

        {{-- Barre de filtres (collapsible) --}}
        <div 
            x-data="{ open: false }"
            class="bg-white rounded-lg shadow-sm border border-gray-200">
            <button 
                type="button"
                @click="open = !open"
                :aria-expanded="open"
                aria-controls="filtres-immobilisations"
                class="w-full px-4 py-3 flex items-center justify-between text-left hover:bg-gray-50 transition-colors">
                <span class="font-medium text-gray-900">Filtres de recherche</span>
                <svg 
                    class="w-5 h-5 text-gray-500 transition-transform"
                    :class="{ 'rotate-180': open }"
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div 
                id="filtres-immobilisations"
                x-show="open"
                x-collapse
                class="border-t border-gray-200 p-4"
                style="overflow: visible !important;">
                <div class="space-y-4">
                    {{-- Première ligne : Recherche globale et filtres principaux --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        {{-- Recherche globale --}}
                        <div class="lg:col-span-2">
                            <label for="recherche-immobilisations" class="block text-sm font-medium text-gray-700 mb-1">
                                Recherche globale
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input 
                                    id="recherche-immobilisations"
                                    type="text"
                                    wire:model.live.debounce.300ms="search"
                                    placeholder="Identifiant (GS1001), code, désignation, site, responsable..."
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        {{-- Filtre Désignation --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Désignation
                            </label>
                            <livewire:components.searchable-select
                                wire:model.live="filterDesignation"
                                :options="$this->designations->map(fn($d) => [
                                    'value' => (string)$d->id,
                                    'text' => $d->designation . ($d->categorie ? ' (' . $d->categorie->categorie . ')' : '')
                                ])->prepend(['value' => '', 'text' => 'Toutes les désignations'])->toArray()"
                                placeholder="Toutes les désignations"
                                :key="'filter-designation'"
                            />
                        </div>

                        {{-- Filtre Catégorie (select natif - peu d'options) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Catégorie
                            </label>
                            <select
                                wire:model.live="filterCategorie"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Toutes les catégories</option>
                                @foreach($this->categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->categorie }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Deuxième ligne : Filtres Localisation → Site --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Filtrage par site
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {{-- Filtre Localisation --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Localisation
                                </label>
                                <livewire:components.searchable-select
                                    wire:model.live="filterLocalisation"
                                    :options="$this->localisationOptions"
                                    placeholder="Toutes les localisations"
                                    :key="'localisation-select-static'"
                                />
                            </div>

                            {{-- Filtre Site (avec responsable) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Site
                                    @if($filterLocalisation)
                                        <span class="text-xs text-gray-500 font-normal">(filtré)</span>
                                    @endif
                                </label>
                                <livewire:components.searchable-select
                                    wire:model.live="filterSite"
                                    :default="$filterSite"
                                    :options="$this->siteOptions"
                                    placeholder="Tous les sites"
                                    search-placeholder="Rechercher un site ou un responsable..."
                                    no-results-text="Aucun site trouvé"
                                    :allow-clear="true"
                                    :key="'site-select-' . ($filterLocalisation ?: 'tous')"
                                />
                            </div>

                            {{-- Filtre État --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    État
                                </label>
                                <select
                                    wire:model.live="filterEtat"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Tous les états</option>
                                    @foreach($this->etats as $etat)
                                        <option value="{{ $etat->id }}">{{ $etat->etat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Troisième ligne : Intervalle d'identifiants --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3m6 0c0 1.657-1.343 3-3 3m0-6V5m0 9v5m7-8h-3M8 12H5" />
                            </svg>
                            Filtrage par intervalle d'identifiants
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Identifiant de début
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    wire:model.live.debounce.300ms="filterNumOrdreMin"
                                    placeholder="Ex: 1000"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Identifiant de fin
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    wire:model.live.debounce.300ms="filterNumOrdreMax"
                                    placeholder="Ex: 1200"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <button 
                        wire:click="resetFilters"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Réinitialiser filtres
                    </button>

                    <div class="text-sm text-gray-600">
                        <span class="font-medium">{{ $biens->total() }}</span> immobilisation(s) trouvée(s)
                    </div>
                </div>
            </div>
        </div>

        {{-- Tableau des biens --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden transition-opacity" wire:loading.class="opacity-60">
            <div class="divide-y divide-gray-200 xl:hidden">
                @forelse($biens as $bien)
                    <article class="p-4">
                        <div class="flex items-start gap-3">
                            @if(! $showArchived)
                                <input
                                    type="checkbox"
                                    wire:model="selectedBiens"
                                    value="{{ $bien->id }}"
                                    aria-label="Sélectionner l’immobilisation {{ $bien->id }}"
                                    class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">N° {{ $bien->id }}</p>
                                        <h3 class="mt-1 truncate text-base font-semibold text-gray-900">
                                            {{ $bien->libelle_article ?? 'Désignation indisponible' }}
                                        </h3>
                                        <p class="mt-1 truncate font-mono text-xs text-gray-500">{{ $bien->code_formate ?? 'Code non disponible' }}</p>
                                        @if($showArchived && $bien->deleted_at)
                                            <p class="mt-1 text-xs font-medium text-red-600">Archivée le {{ $bien->deleted_at->format('d/m/Y H:i') }}</p>
                                        @endif
                                    </div>
                                    @if($bien->etat)
                                        <span class="shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800">
                                            {{ $bien->etat->etat }}
                                        </span>
                                    @endif
                                </div>

                                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-xs text-gray-500">Catégorie</dt>
                                        <dd class="mt-0.5 font-medium text-gray-800">{{ $bien->categorie?->categorie ?? 'Non renseignée' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">Site</dt>
                                        <dd class="mt-0.5 font-medium text-gray-800">{{ $bien->site?->site ?? 'Non affecté' }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
                                    @if($showArchived)
                                        <button
                                            type="button"
                                            wire:click="restoreBien({{ $bien->id }})"
                                            wire:confirm="Restaurer cette immobilisation ?"
                                            class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                                            Restaurer
                                        </button>
                                    @else
                                        <a href="{{ route('biens.show', $bien) }}" class="rounded-lg bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">Voir</a>
                                        @if($isAdmin)
                                            <a href="{{ route('biens.edit', $bien) }}" class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100">Modifier</a>
                                            <button
                                                type="button"
                                                wire:click="deleteBien({{ $bien->id }})"
                                                wire:confirm="Êtes-vous sûr de vouloir archiver cette immobilisation ? Vous pourrez la restaurer depuis les archives."
                                                wire:loading.attr="disabled"
                                                wire:target="deleteBien({{ $bien->id }})"
                                                class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100 disabled:opacity-50">
                                                Archiver
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <h3 class="mt-3 text-base font-semibold text-gray-900">Aucune immobilisation trouvée</h3>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500">
                            @if($search || $filterDesignation || $filterCategorie || $filterLocalisation || $filterSite || $filterEtat || $filterNumOrdreMin || $filterNumOrdreMax)
                                Modifiez ou réinitialisez les critères de recherche.
                            @elseif($showArchived)
                                Aucune immobilisation archivée.
                            @else
                                Créez votre première immobilisation pour commencer le suivi.
                            @endif
                        </p>
                        @if(! $showArchived && !($search || $filterDesignation || $filterCategorie || $filterLocalisation || $filterSite || $filterEtat || $filterNumOrdreMin || $filterNumOrdreMax) && $isAdmin)
                            <a href="{{ route('biens.create') }}" class="mt-5 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">
                                Ajouter une immobilisation
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>

            <div class="hidden overflow-x-auto xl:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                @if(! $showArchived)
                                    <input
                                        type="checkbox"
                                        wire:click="toggleSelectAll"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        @if($this->allSelected) checked @endif>
                                @endif
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" wire:click="sortBy('id')">
                                <div class="flex items-center">
                                    N°
                                    @if($sortField === 'id')
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                        </svg>
                                    @endif
                                </div>
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Code
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Désignation
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Catégorie
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                État
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Site
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Descriptif
                            </th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($biens as $bien)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if(! $showArchived)
                                        <input
                                            type="checkbox"
                                            wire:model="selectedBiens"
                                            value="{{ $bien->id }}"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $bien->id }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-600">{{ $bien->code_formate ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900">
                                        {{ $bien->designation ? Str::limit($bien->libelle_article, 50) : 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($bien->categorie)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $bien->categorie->categorie }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($bien->etat)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $bien->etat->etat }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        @if($bien->site)
                                            {{ $bien->site->site }}
                                            @if($bien->site->responsable)
                                                <br><span class="text-xs text-gray-500">{{ $bien->site->responsable }}</span>
                                            @endif
                                            @if($bien->site->localisation)
                                                <br><span class="text-xs text-gray-500">{{ $bien->site->localisation->localisation }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <span class="block max-w-xs truncate" title="{{ $bien->descriptif }}">{{ $bien->descriptif ?: '—' }}</span>
                                        @if($showArchived && $bien->deleted_at)
                                            <div class="mt-1 text-xs text-red-600">Archivée le {{ $bien->deleted_at->format('d/m/Y H:i') }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($showArchived)
                                            <button
                                                type="button"
                                                wire:click="restoreBien({{ $bien->id }})"
                                                wire:confirm="Restaurer cette immobilisation ?"
                                                class="text-emerald-600 transition-colors hover:text-emerald-900"
                                                title="Restaurer">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 110 8H9m-6-8l4-4m-4 4l4 4" />
                                                </svg>
                                            </button>
                                        @else
                                        <a 
                                            href="{{ route('biens.show', $bien) }}"
                                            class="text-indigo-600 hover:text-indigo-900 transition-colors"
                                            title="Voir">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>

                                        @if($isAdmin)
                                            <a 
                                                href="{{ route('biens.edit', $bien) }}"
                                                class="text-yellow-600 hover:text-yellow-900 transition-colors"
                                                title="Modifier">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        @endif

                                        @if($isAdmin)
                                            <button 
                                                wire:click="deleteBien({{ $bien->id }})"
                                                wire:confirm="Êtes-vous sûr de vouloir archiver ce bien ?"
                                                class="text-red-600 hover:text-red-900 transition-colors"
                                                title="Archiver">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun bien trouvé</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        @if($search || $filterDesignation || $filterCategorie || $filterLocalisation || $filterSite || $filterEtat || $filterNumOrdreMin || $filterNumOrdreMax)
                                            Essayez de modifier vos critères de recherche.
                                        @elseif($showArchived)
                                            Aucune immobilisation archivée.
                                        @else
                                            Commencez par créer une nouvelle immobilisation.
                                        @endif
                                    </p>
                                    @if(! $showArchived && !($search || $filterDesignation || $filterCategorie || $filterLocalisation || $filterSite || $filterEtat || $filterNumOrdreMin || $filterNumOrdreMax) && $isAdmin)
                                        <a href="{{ route('biens.create') }}" class="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                            Ajouter une immobilisation
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($biens->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 sm:px-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700">Par page :</label>
                            <select 
                                wire:model.live="perPage"
                                class="block px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div>
                            {{ $biens->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Messages flash --}}
    @if(session()->has('success'))
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 3000)"
            x-transition
            class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition
            class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('error') }}
        </div>
    @endif

    @if(session()->has('warning'))
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            x-transition
            class="fixed bottom-4 right-4 bg-yellow-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('warning') }}
        </div>
    @endif

</div>
