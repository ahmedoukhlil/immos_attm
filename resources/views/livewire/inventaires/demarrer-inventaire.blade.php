<div class="max-w-5xl mx-auto">
    {{-- Header --}}
    <div class="mb-8">
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                <li><a href="{{ route('dashboard') }}" class="hover:text-indigo-600 transition-colors">Tableau de bord</a></li>
                <li><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></li>
                <li><a href="{{ route('inventaires.index') }}" class="hover:text-indigo-600 transition-colors">Inventaires</a></li>
                <li><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></li>
                <li class="text-gray-400">Nouveau</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900">Nouvel inventaire</h1>
        <p class="mt-1 text-sm text-gray-500">Configurez et lancez un inventaire en 3 étapes simples.</p>
    </div>

    <div x-data="{ etape: @entangle('etapeActuelle') }">
        {{-- ========================================== --}}
        {{-- INDICATEUR D'ÉTAPES                        --}}
        {{-- ========================================== --}}
        <div class="mb-8">
            <div class="grid grid-cols-3 gap-2" aria-label="Progression de la création de l’inventaire">
                {{-- Étape 1 --}}
                <button
                    type="button"
                    class="flex min-w-0 flex-col items-center gap-2 rounded-xl px-2 py-3 text-center transition-colors sm:flex-row sm:text-left"
                    :class="etape > 1 ? 'hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500' : ''"
                    @click="if(etape > 1) $wire.set('etapeActuelle', 1)"
                    :disabled="etape <= 1"
                    @if($etapeActuelle === 1) aria-current="step" @endif>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center transition-colors
                        {{ $etapeActuelle >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-400' }}">
                        @if($etapeActuelle > 1)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <span class="text-sm font-bold">1</span>
                        @endif
                    </div>
                    <span class="truncate text-xs font-medium sm:text-sm {{ $etapeActuelle >= 1 ? 'text-gray-900' : 'text-gray-400' }}">Informations</span>
                </button>

                {{-- Étape 2 --}}
                <button
                    type="button"
                    class="flex min-w-0 flex-col items-center gap-2 rounded-xl px-2 py-3 text-center transition-colors sm:flex-row sm:text-left"
                    :class="etape > 2 ? 'hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500' : ''"
                    @click="if(etape > 2) $wire.set('etapeActuelle', 2)"
                    :disabled="etape <= 2"
                    @if($etapeActuelle === 2) aria-current="step" @endif>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center transition-colors
                        {{ $etapeActuelle >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-400' }}">
                        @if($etapeActuelle > 2)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <span class="text-sm font-bold">2</span>
                        @endif
                    </div>
                    <span class="truncate text-xs font-medium sm:text-sm {{ $etapeActuelle >= 2 ? 'text-gray-900' : 'text-gray-400' }}">Périmètre</span>
                </button>

                {{-- Étape 3 --}}
                <div class="flex min-w-0 flex-col items-center gap-2 rounded-xl px-2 py-3 text-center sm:flex-row sm:text-left" @if($etapeActuelle === 3) aria-current="step" @endif>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center transition-colors
                        {{ $etapeActuelle >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-400' }}">
                        <span class="text-sm font-bold">3</span>
                    </div>
                    <span class="truncate text-xs font-medium sm:text-sm {{ $etapeActuelle >= 3 ? 'text-gray-900' : 'text-gray-400' }}">Équipe</span>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- CONTENU DES ÉTAPES                         --}}
        {{-- ========================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Colonne principale --}}
            <div class="lg:col-span-2">

                {{-- ÉTAPE 1 : Informations générales --}}
                <div x-show="etape === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
                        <div class="p-6">
                            <h2 class="text-lg font-semibold text-gray-900">Informations générales</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Définissez l'année et la date de début de l'inventaire.</p>
                        </div>
                        <div class="p-6 space-y-5">
                            {{-- Année --}}
                            <div>
                                <label for="annee" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Année <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="annee"
                                    wire:model.live="annee"
                                    class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm transition-colors @error('annee') border-red-300 ring-red-500/20 @enderror">
                                    <option value="">Sélectionner une année</option>
                                    @foreach($this->anneesDisponibles as $a)
                                        <option value="{{ $a }}">{{ $a }}</option>
                                    @endforeach
                                </select>
                                @error('annee')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-gray-400">Chaque année ne peut avoir qu'un seul inventaire.</p>
                            </div>

                            {{-- Date début --}}
                            <div>
                                <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Date de début <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="date_debut"
                                    wire:model="date_debut"
                                    min="{{ now()->format('Y-m-d') }}"
                                    class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm transition-colors @error('date_debut') border-red-300 ring-red-500/20 @enderror">
                                @error('date_debut')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Observation --}}
                            <div>
                                <label for="observation" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Observation <span class="text-xs font-normal text-gray-400">(optionnel)</span>
                                </label>
                                <textarea
                                    id="observation"
                                    wire:model="observation"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="Notes ou contexte sur cet inventaire..."
                                    class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm transition-colors @error('observation') border-red-300 @enderror"></textarea>
                                @error('observation')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ÉTAPE 2 : Sélection des localisations --}}
                <div x-show="etape === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Périmètre de l'inventaire</h2>
                                    <p class="text-sm text-gray-500 mt-0.5">
                                        <span class="font-medium text-indigo-600">{{ $this->totalLocalisations }}</span> localisation(s)
                                        &middot;
                                        <span class="font-medium text-gray-700">{{ $this->totalBiensAttendus }}</span> immobilisation(s)
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        wire:click="selectToutesLocalisations"
                                        class="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                        Tout cocher
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deselectToutesLocalisations"
                                        class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                        Tout décocher
                                    </button>
                                </div>
                            </div>

                            {{-- Barre de recherche --}}
                            <div class="mt-4 relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="rechercheLocalisation"
                                    placeholder="Filtrer les localisations..."
                                    class="block w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-colors">
                            </div>
                        </div>

                        @error('localisationsSelectionnees')
                            <div class="mx-6 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            </div>
                        @enderror

                        {{-- Grille des localisations --}}
                        <div class="p-6 max-h-[28rem] overflow-y-auto">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @forelse($this->localisationsFiltrees as $localisation)
                                    @php
                                        $estSelectionnee = in_array($localisation->id, $localisationsSelectionnees);
                                    @endphp
                                    <div
                                        wire:click="toggleLocalisation({{ $localisation->id }})"
                                        wire:key="loc-{{ $localisation->id }}"
                                        class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all
                                            {{ $estSelectionnee
                                                ? 'border-indigo-500 bg-indigo-50/60 shadow-sm'
                                                : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50' }}">

                                        {{-- Checkbox visuelle --}}
                                        <div class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors
                                            {{ $estSelectionnee ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white' }}">
                                            @if($estSelectionnee)
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </div>

                                        {{-- Infos --}}
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $localisation->localisation }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                @if($localisation->code)
                                                    <span class="text-xs text-gray-400">{{ $localisation->code }}</span>
                                                    <span class="text-gray-300">&middot;</span>
                                                @endif
                                                <span class="text-xs text-gray-500">{{ $localisation->biens_count ?? 0 }} bien(s)</span>
                                            </div>
                                        </div>

                                        {{-- Badge count --}}
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                                {{ $estSelectionnee ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $localisation->biens_count ?? 0 }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="sm:col-span-2 text-center py-8">
                                        @if(!empty($rechercheLocalisation))
                                            <p class="text-sm text-gray-400">Aucune localisation ne correspond à « {{ $rechercheLocalisation }} »</p>
                                        @else
                                            <p class="text-sm text-gray-400">Aucune localisation disponible</p>
                                        @endif
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ÉTAPE 3 : Assignation agents --}}
                <div x-show="etape === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900">Assignation des agents</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Attribuez un ou plusieurs agents par localisation. Vous pourrez modifier les assignations plus tard.</p>

                            {{-- Assignation rapide --}}
                            <div class="mt-4 flex flex-col sm:flex-row gap-3 p-4 bg-indigo-50/50 border border-indigo-100 rounded-xl">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-indigo-700 mb-1.5">Ajouter un agent à toutes les localisations</label>
                                    <select
                                        wire:model.live="agentGlobalSelect"
                                        class="block w-full px-3 py-2.5 border border-indigo-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 bg-white transition-colors">
                                        <option value="">Choisir un agent...</option>
                                        @foreach($this->agents as $ag)
                                            <option value="{{ $ag->idUser }}">{{ $ag->users }} ({{ $ag->role_name }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Liste des assignations (scrollable) --}}
                        <div class="divide-y divide-gray-100 max-h-[32rem] overflow-y-auto">
                            @foreach($this->localisations->whereIn('id', $localisationsSelectionnees) as $localisation)
                                @php
                                    $locId = $localisation->id;
                                    $agentsAssignes = $assignations[$locId] ?? [];
                                @endphp
                                <div wire:key="assign-{{ $locId }}" class="px-6 py-4">
                                    <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                                        {{-- Localisation info --}}
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $localisation->localisation }}
                                            </p>
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                {{ $localisation->biens_count ?? 0 }} immobilisation(s)
                                            </p>

                                            {{-- Tags des agents assignés --}}
                                            @if(!empty($agentsAssignes))
                                                <div class="flex flex-wrap gap-1.5 mt-2">
                                                    @foreach($agentsAssignes as $agId)
                                                        @php
                                                            $ag = $this->agents->firstWhere('idUser', $agId);
                                                        @endphp
                                                        @if($ag)
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                                                <span class="w-4 h-4 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[9px] font-bold flex-shrink-0">{{ mb_substr($ag->users, 0, 1) }}</span>
                                                                {{ $ag->users }}
                                                                <button type="button" wire:click="retirerAgent({{ $locId }}, {{ $agId }})" class="ml-0.5 p-0.5 rounded-full hover:bg-red-100 hover:text-red-600 transition-colors" title="Retirer">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                </button>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-xs text-amber-500 mt-2 italic">Aucun agent assigné</p>
                                            @endif
                                        </div>

                                        {{-- Dropdown multi-select agents --}}
                                        <div class="sm:w-48 flex-shrink-0 relative" x-data="{ open: false }">
                                            <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-2 px-3 py-2 border rounded-lg text-sm transition-colors"
                                                :class="open ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-500/20' : 'border-gray-300 bg-white hover:bg-gray-50'">
                                                <span class="text-gray-500 text-xs">
                                                    @if(count($agentsAssignes) > 0)
                                                        {{ count($agentsAssignes) }} agent(s)
                                                    @else
                                                        Assigner...
                                                    @endif
                                                </span>
                                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" x-transition x-cloak
                                                class="absolute right-0 mt-1 w-52 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 max-h-52 overflow-y-auto">
                                                @foreach($this->agents as $ag)
                                                    @php $isSelected = in_array($ag->idUser, $agentsAssignes); @endphp
                                                    <button
                                                        type="button"
                                                        wire:click="toggleAgent({{ $locId }}, {{ $ag->idUser }})"
                                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-indigo-50 transition-colors {{ $isSelected ? 'bg-indigo-50/60' : '' }}">
                                                        <div class="w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0
                                                            {{ $isSelected ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white' }}">
                                                            @if($isSelected)
                                                                <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                                <span class="text-[10px] font-bold text-gray-600">{{ mb_substr($ag->users, 0, 1) }}</span>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="text-sm font-medium text-gray-700 truncate">{{ $ag->users }}</p>
                                                                <p class="text-[10px] text-gray-400">{{ $ag->role_name }}</p>
                                                            </div>
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Résumé assignations --}}
                        @php
                            $locsAssignees = collect($assignations)->filter(fn($agents) => is_array($agents) && count($agents) > 0)->count();
                        @endphp
                        <div class="p-6 bg-gray-50 border-t border-gray-100 rounded-b-xl">
                            <div class="flex items-center justify-around text-center">
                                <div>
                                    <p class="text-2xl font-bold text-indigo-600">{{ $locsAssignees }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Assignée(s)</p>
                                </div>
                                <div class="w-px h-8 bg-gray-200"></div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-400">{{ $this->totalLocalisations - $locsAssignees }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Non assignée(s)</p>
                                </div>
                                <div class="w-px h-8 bg-gray-200"></div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-900">{{ $this->agentsImpliques }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Agent(s) distinct(s)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========================================== --}}
            {{-- SIDEBAR RÉSUMÉ                             --}}
            {{-- ========================================== --}}
            <div class="lg:col-span-1">
                <div class="sticky top-6 space-y-4">
                    {{-- Carte résumé --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-5 bg-gradient-to-br from-indigo-600 to-indigo-700 text-white">
                            <p class="text-xs font-medium text-indigo-200 uppercase tracking-wider">Inventaire</p>
                            <p class="text-3xl font-bold mt-1">{{ $annee ?: '—' }}</p>
                            <p class="text-sm text-indigo-200 mt-1">
                                {{ $date_debut ? \Carbon\Carbon::parse($date_debut)->translatedFormat('d F Y') : 'Date non définie' }}
                            </p>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Localisations</span>
                                <span class="text-sm font-bold text-gray-900">{{ $this->totalLocalisations }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Immobilisations</span>
                                <span class="text-sm font-bold text-gray-900">{{ $this->totalBiensAttendus }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Agents assignés</span>
                                <span class="text-sm font-bold text-gray-900">{{ $this->agentsImpliques }}</span>
                            </div>
                            @if($observation)
                                <div class="pt-3 border-t border-gray-100">
                                    <p class="text-xs text-gray-400 uppercase font-medium mb-1">Observation</p>
                                    <p class="text-sm text-gray-600">{{ Str::limit($observation, 100) }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Statut --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <p class="text-sm font-medium text-amber-800">Mode préparation</p>
                                <p class="text-xs text-amber-600 mt-0.5">L'inventaire sera créé en mode préparation. Vous pourrez le lancer depuis le tableau de bord.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- BARRE DE NAVIGATION                        --}}
        {{-- ========================================== --}}
        <div class="mt-8 flex items-center justify-between">
            <div>
                @if($etapeActuelle > 1)
                    <button
                        type="button"
                        wire:click="etapePrecedente"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Précédent
                    </button>
                @else
                    <a
                        href="{{ route('inventaires.index') }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Annuler
                    </a>
                @endif
            </div>
            <div>
                @if($etapeActuelle < 3)
                    <button
                        type="button"
                        wire:click="etapeSuivante"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                        Suivant
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @else
                    <button
                        type="button"
                        wire:click="demarrer"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <span wire:loading.remove wire:target="demarrer" class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Créer l'inventaire
                        </span>
                        <span wire:loading wire:target="demarrer" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Création...
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- MESSAGES FLASH                             --}}
    {{-- ========================================== --}}
    @if(session()->has('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            x-transition
            class="fixed bottom-4 right-4 flex items-center gap-3 bg-green-600 text-white px-5 py-3 rounded-xl shadow-lg z-50">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 6000)"
            x-transition
            class="fixed bottom-4 right-4 flex items-center gap-3 bg-red-600 text-white px-5 py-3 rounded-xl shadow-lg z-50">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif
</div>
