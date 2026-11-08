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
                        <a href="{{ route('designations.index') }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-indigo-600 md:ml-2">Désignations</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">
                            {{ $this->isEdit ? 'Modifier' : 'Ajouter' }} une désignation
                        </span>
                    </div>
                </li>
            </ol>
        </nav>

        <h1 class="text-3xl font-bold text-gray-900">
            {{ $this->isEdit && $this->designation ? 'Modifier ' . $this->designation->designation : 'Ajouter une désignation' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $this->isEdit ? 'Modifiez les informations de la désignation' : 'Remplissez le formulaire pour ajouter une nouvelle désignation' }}
        </p>
    </div>

    {{-- Formulaire --}}
    <form wire:submit.prevent="save" class="space-y-6">
        <div 
            wire:loading.class="opacity-50 pointer-events-none"
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            
            {{-- Section 1 : Identification --}}
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Identification
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Désignation --}}
                    <div>
                        <label for="designation_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Désignation <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <input 
                                type="text"
                                id="designation_name"
                                wire:model="designation_name"
                                placeholder="Ex: Bureau, Chaise, Table"
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('designation_name') border-red-300 @enderror"
                                wire:loading.attr="disabled">
                        </div>
                        @error('designation_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Catégorie --}}
                    <div>
                        <label for="categorie_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Catégorie <span class="text-red-500">*</span>
                        </label>
                        <livewire:components.searchable-select
                            wire:model="categorie_id"
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
                    </div>

                    {{-- Code Désignation --}}
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                            Code de désignation
                        </label>
                        <div class="flex gap-2">
                            <div class="flex-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                    </svg>
                                </div>
                                <input 
                                    type="text"
                                    id="code"
                                    wire:model="code"
                                    placeholder="Ex: BUR, CHA, TAB"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm @error('code') border-red-300 @enderror"
                                    wire:loading.attr="disabled">
                            </div>
                            <button 
                                type="button"
                                wire:click="generateCodeSuggestion"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                                title="Générer un code automatiquement">
                                Auto
                            </button>
                        </div>
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">
                            Code optionnel pour identifier la désignation
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Composition du matériel</h2>
                        <p class="text-sm text-gray-500">Chaque composant configuré produira sa propre étiquette.</p>
                    </div>
                    <label class="inline-flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <input type="checkbox" wire:model.live="estCompose" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-800">Matériel composé</span>
                    </label>
                </div>

                @if($estCompose)
                    <div class="mt-5 space-y-3">
                        @foreach($composants as $index => $composant)
                            <div wire:key="composant-{{ $index }}" class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-12 md:items-end">
                                <div class="md:col-span-4">
                                    <label for="composant-nom-{{ $index }}" class="mb-1 block text-xs font-medium text-gray-700">Nom</label>
                                    <input id="composant-nom-{{ $index }}" type="text" wire:model="composants.{{ $index }}.nom" placeholder="Ex. Écran" class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error("composants.$index.nom") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label for="composant-code-{{ $index }}" class="mb-1 block text-xs font-medium text-gray-700">Code</label>
                                    <input id="composant-code-{{ $index }}" type="text" wire:model="composants.{{ $index }}.code" placeholder="E" maxlength="20" class="block w-full rounded-lg border-gray-300 text-sm uppercase focus:border-indigo-500 focus:ring-indigo-500">
                                    @error("composants.$index.code") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label for="composant-quantite-{{ $index }}" class="mb-1 block text-xs font-medium text-gray-700">Étiquettes</label>
                                    <input id="composant-quantite-{{ $index }}" type="number" min="1" max="20" wire:model="composants.{{ $index }}.quantite" class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error("composants.$index.quantite") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="md:col-span-3">
                                    <label class="inline-flex min-h-10 items-center gap-2">
                                        <input type="checkbox" wire:model="composants.{{ $index }}.obligatoire" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm text-gray-700">Obligatoire</span>
                                    </label>
                                </div>
                                <div class="md:col-span-1 md:text-right">
                                    <button type="button" wire:click="retirerComposant({{ $index }})" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-red-600 hover:bg-red-50" aria-label="Supprimer le composant {{ $index + 1 }}">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach

                        @error('composants') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <button type="button" wire:click="ajouterComposant" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                            <span class="mr-2 text-lg leading-none">+</span> Ajouter un composant
                        </button>
                    </div>
                @else
                    <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
                        Ce matériel générera une seule étiquette principale.
                    </div>
                @endif
            </div>
        </div>

        {{-- Boutons d'action (sticky footer) --}}
        <div class="sticky bottom-0 bg-white border-t border-gray-200 px-6 py-4 rounded-b-lg shadow-lg -mx-6 -mb-6">
            <div class="flex justify-end gap-3">
                <button 
                    type="button"
                    wire:click="cancel"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Annuler
                </button>
                <button 
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
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
</div>
