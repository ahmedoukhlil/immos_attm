<div>
    {{-- Header avec titre et breadcrumb --}}
    <div class="mb-6">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-indigo-600">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Tableau de bord
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="{{ route('biens.index') }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-indigo-600 md:ml-2">Immobilisations</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">
                            {{ $this->isEdit ? 'Modifier' : 'Ajouter' }}
                        </span>
                    </div>
                </li>
            </ol>
        </nav>

        <h1 class="text-3xl font-bold text-gray-900">
            {{ $this->isEdit ? 'Modifier '.$bien->libelle_article.' ('.$bien->code_formate.')' : 'Ajouter une immobilisation' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $this->isEdit ? 'Modifiez les informations de l\'immobilisation' : 'Remplissez le formulaire pour ajouter une immobilisation à l\'inventaire' }}
        </p>
    </div>

    @php
        $champ = 'block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm';
    @endphp

    @unless($this->isEdit)
        <div class="mb-6">
            <livewire:biens.import-biens />
        </div>

        <h2 class="mb-3 text-lg font-semibold text-gray-900">Ou saisir une immobilisation</h2>
    @endunless

    {{-- Formulaire --}}
    <form wire:submit.prevent="save" class="space-y-6">
        <div
            wire:loading.class="opacity-50 pointer-events-none"
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">

            {{-- Section 1 : Site et responsable --}}
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Site
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Localisation (filtre) --}}
                    <div>
                        <label for="localisation_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Localisation
                        </label>
                        <livewire:components.searchable-select
                            wire:key="localisation-{{ $referencesVersion }}"
                            wire:model.live="localisation_id"
                            :default="$localisation_id"
                            :options="$this->localisationOptions"
                            placeholder="Toutes les localisations"
                            search-placeholder="Rechercher une localisation..."
                            no-results-text="Aucune localisation trouvée"
                            :allow-clear="true"
                            name="localisation_id"
                        />
                    </div>

                    {{-- Site --}}
                    <div>
                        <label for="site_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Site <span class="text-red-500">*</span>
                        </label>
                        <livewire:components.searchable-select
                            wire:key="site-{{ $localisation_id ?: 'tous' }}-{{ $referencesVersion }}"
                            wire:model.live="site_id"
                            :default="$site_id"
                            :options="$this->siteOptions"
                            placeholder="Sélectionner un site"
                            search-placeholder="Rechercher un site ou un responsable..."
                            no-results-text="Aucun site trouvé"
                            :allow-clear="true"
                            name="site_id"
                        />
                        @error('site_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @if($this->responsable)
                            <p class="mt-1 text-xs text-gray-500">Responsable : {{ $this->responsable }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Section 2 : Immobilisation --}}
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Immobilisation
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Désignation --}}
                    <div>
                        <label for="designation_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Désignation de l'immobilisation <span class="text-red-500">*</span>
                        </label>
                        <livewire:components.searchable-select
                            wire:key="designation-{{ $bienId ?? 'new' }}-{{ $referencesVersion }}"
                            wire:model.live.debounce.150ms="designation_id"
                            :default="$designation_id"
                            :options="$this->designationOptions"
                            placeholder="Sélectionner une désignation"
                            search-placeholder="Rechercher une désignation..."
                            no-results-text="Aucune désignation trouvée"
                            :allow-clear="true"
                            name="designation_id"
                        />
                        @error('designation_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">
                            @if($this->isEdit)
                                Article n° {{ $bien->numero_article }}. Changer de désignation attribue le numéro suivant de la nouvelle désignation.
                            @else
                                Le numéro d'article (ex. Ordinateur 3) est attribué automatiquement.
                            @endif
                        </p>
                    </div>

                    {{-- Catégorie --}}
                    <div>
                        <label for="categorie_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Catégorie (imputation comptable) <span class="text-red-500">*</span>
                        </label>
                        <livewire:components.searchable-select
                            wire:key="categorie-{{ $designation_id ?: 'aucune' }}-{{ $referencesVersion }}"
                            wire:model="categorie_id"
                            :default="$categorie_id"
                            :options="$this->categorieOptions"
                            placeholder="Sélectionner une catégorie"
                            search-placeholder="Rechercher une catégorie..."
                            no-results-text="Aucune catégorie trouvée"
                            :allow-clear="true"
                            name="categorie_id"
                        />
                        @error('categorie_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Proposée automatiquement selon la désignation.</p>
                    </div>

                    {{-- Descriptif --}}
                    <div class="md:col-span-2">
                        <label for="descriptif" class="block text-sm font-medium text-gray-700 mb-1">
                            Descriptifs de l'immobilisation
                        </label>
                        <input
                            type="text"
                            id="descriptif"
                            wire:model="descriptif"
                            maxlength="255"
                            placeholder="Ex: En bois rouge, type directeur avec retour"
                            class="{{ $champ }} @error('descriptif') border-red-300 @enderror">
                        @error('descriptif')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Identification technique --}}
                    <div>
                        <label for="marque" class="block text-sm font-medium text-gray-700 mb-1">Marque</label>
                        <input type="text" id="marque" wire:model="marque" maxlength="100"
                            autocomplete="organization-title" placeholder="Ex : Toyota, Dell, Caterpillar"
                            class="{{ $champ }} @error('marque') border-red-300 @enderror">
                        @error('marque')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="modele" class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
                        <input type="text" id="modele" wire:model="modele" maxlength="100"
                            placeholder="Ex : Hilux, Latitude 5540"
                            class="{{ $champ }} @error('modele') border-red-300 @enderror">
                        @error('modele')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="numero_chassis" class="block text-sm font-medium text-gray-700 mb-1">Numéro de châssis</label>
                        <input type="text" id="numero_chassis" wire:model="numero_chassis" maxlength="100"
                            placeholder="Saisir le numéro de châssis"
                            class="{{ $champ }} font-mono @error('numero_chassis') border-red-300 @enderror">
                        @error('numero_chassis')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="numero_attm" class="block text-sm font-medium text-gray-700 mb-1">Numéro ATTM</label>
                        <input type="text" id="numero_attm" wire:model="numero_attm" maxlength="100"
                            placeholder="Saisir le numéro ATTM"
                            class="{{ $champ }} font-mono @error('numero_attm') border-red-300 @enderror">
                        @error('numero_attm')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="code_immo" class="block text-sm font-medium text-gray-700 mb-1">Code immo</label>
                        <input type="text" id="code_immo" wire:model="code_immo" maxlength="100"
                            placeholder="Saisir le code immo interne"
                            class="{{ $champ }} font-mono @error('code_immo') border-red-300 @enderror">
                        @error('code_immo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Code interne saisi manuellement, distinct des codes d’inventaire.</p>
                    </div>

                    {{-- État --}}
                    <div>
                        <label for="etat_id" class="block text-sm font-medium text-gray-700 mb-1">
                            État de l'immo <span class="text-red-500">*</span>
                        </label>
                        <livewire:components.searchable-select
                            wire:key="etat-{{ $bienId ?? 'new' }}-{{ $referencesVersion }}"
                            wire:model="etat_id"
                            :default="$etat_id"
                            :options="$this->etatOptions"
                            placeholder="Sélectionner un état"
                            search-placeholder="Rechercher un état..."
                            no-results-text="Aucun état trouvé"
                            :allow-clear="true"
                            name="etat_id"
                        />
                        @error('etat_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @unless($this->isEdit)
                        {{-- Quantité --}}
                        <div>
                            <label for="quantite" class="block text-sm font-medium text-gray-700 mb-1">
                                Quantité <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="quantite"
                                wire:model="quantite"
                                min="1"
                                max="1000"
                                class="{{ $champ }} @error('quantite') border-red-300 @enderror">
                            @error('quantite')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">
                                Nombre d'immobilisations identiques à créer. Chacune reçoit son propre identifiant de scan.
                            </p>
                        </div>
                    @endunless
                </div>
            </div>

            {{-- Section 3 : Codes d'inventaire --}}
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Codes d'inventaire
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="code_dernier_inventaire" class="block text-sm font-medium text-gray-700 mb-1">
                            Code dernier inventaire
                        </label>
                        <input type="text" id="code_dernier_inventaire" wire:model="code_dernier_inventaire" maxlength="40"
                            placeholder="Ex: INV-17-216-A700"
                            class="{{ $champ }} @error('code_dernier_inventaire') border-red-300 @enderror">
                        @error('code_dernier_inventaire')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="code_premier_inventaire" class="block text-sm font-medium text-gray-700 mb-1">
                            Code premier inventaire
                        </label>
                        <input type="text" id="code_premier_inventaire" wire:model="code_premier_inventaire" maxlength="40"
                            placeholder="Ex: E0685"
                            class="{{ $champ }} @error('code_premier_inventaire') border-red-300 @enderror">
                        @error('code_premier_inventaire')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="annee_acquisition" class="block text-sm font-medium text-gray-700 mb-1">
                            Année d'acquisition <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="annee_acquisition" wire:model="annee_acquisition" min="1950"
                            class="{{ $champ }} @error('annee_acquisition') border-red-300 @enderror">
                        @error('annee_acquisition')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">
                            Code généré : imputation / code désignation / n° d'article / année
                            @if($this->isEdit) — actuel : <span class="font-mono">{{ $bien->code_propose }}</span>@endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Section 4 : Autres informations --}}
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Autres informations
                </h2>
                <textarea
                    id="autres_informations"
                    wire:model="autres_informations"
                    rows="3"
                    maxlength="2000"
                    class="{{ $champ }} @error('autres_informations') border-red-300 @enderror"></textarea>
                @error('autres_informations')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Boutons d'action --}}
        <div class="mt-6 border-t border-gray-200 bg-white px-4 py-4 sm:px-6 md:sticky md:bottom-0 md:z-20 md:-mx-6 md:-mb-6 md:rounded-b-lg md:shadow-lg">
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    wire:click="cancel"
                    wire:loading.attr="disabled"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors sm:w-auto">
                    Annuler
                </button>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex w-full items-center justify-center px-4 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors sm:w-auto">
                    <span wire:loading.remove wire:target="save">
                        {{ $this->isEdit ? 'Modifier' : 'Enregistrer' }}
                    </span>
                    <span wire:loading wire:target="save" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Enregistrement...
                    </span>
                </button>
            </div>
        </div>
    </form>

    {{-- Messages flash --}}
    @if(session()->has('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 3000)"
            x-transition
            class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50"
            role="status" aria-live="polite">
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            x-transition
            class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50"
            role="alert" aria-live="assertive">
            {{ session('error') }}
        </div>
    @endif
</div>
