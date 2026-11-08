<div>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $statutConfig = [
            'en_preparation' => ['label' => 'Préparation', 'bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400'],
            'en_cours'       => ['label' => 'En cours',     'bg' => 'bg-blue-50',  'text' => 'text-blue-700', 'dot' => 'bg-blue-500'],
            'termine'        => ['label' => 'Terminé',      'bg' => 'bg-amber-50', 'text' => 'text-amber-700','dot' => 'bg-amber-500'],
            'cloture'        => ['label' => 'Clôturé',      'bg' => 'bg-green-50', 'text' => 'text-green-700','dot' => 'bg-green-500'],
        ];
        $compteurs = $this->compteurs;
        $inventaireActif = $this->inventaireEnCours;
    @endphp

    {{-- Auto-refresh si inventaire en cours --}}
    @if($inventaireActif && $inventaireActif->statut === 'en_cours')
        <div wire:poll.30s></div>
    @endif

    {{-- ========================================== --}}
    {{-- HEADER                                     --}}
    {{-- ========================================== --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Inventaires</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $compteurs['total'] }} inventaire(s) au total
                @if($compteurs['en_cours'] > 0)
                    &middot; <span class="text-blue-600 font-medium">{{ $compteurs['en_cours'] }} actif(s)</span>
                @endif
            </p>
        </div>
        @if($isAdmin && !$inventaireActif)
            <a
                href="{{ route('inventaires.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouvel inventaire
            </a>
        @elseif($isAdmin)
            <button type="button" disabled title="Terminez ou clôturez l’inventaire actif avant d’en créer un autre"
                class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-gray-200 px-4 py-2.5 text-sm font-medium text-gray-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Inventaire actif en cours
            </button>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- INVENTAIRE EN COURS (mise en avant)        --}}
    {{-- ========================================== --}}
    @if($inventaireActif && $inventaireActif->statut === 'en_cours')
        @php
            $totalLoc = $inventaireActif->inventaire_localisations_count ?? 0;
            $locTerminees = $inventaireActif->localisations_terminees_count ?? 0;
            $progressionActif = $totalLoc > 0 ? round(($locTerminees / $totalLoc) * 100, 1) : 0;
        @endphp
        <div class="mb-6 bg-white rounded-xl shadow-sm border-2 border-blue-200 overflow-hidden">
            <div class="p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                        </span>
                        <h2 class="text-lg font-bold text-gray-900">Inventaire {{ $inventaireActif->annee }}</h2>
                        <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">En cours</span>
                    </div>
                    <a
                        href="{{ route('inventaires.show', $inventaireActif) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        Tableau de bord
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                {{-- Barre de progression --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm text-gray-600">Progression</span>
                        <span class="text-sm font-bold text-gray-900">{{ $progressionActif }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div
                            class="h-2.5 rounded-full transition-all duration-500 {{ $progressionActif >= 100 ? 'bg-green-500' : 'bg-blue-500' }}"
                            style="width: {{ min($progressionActif, 100) }}%"></div>
                    </div>
                </div>

                {{-- Stats compactes --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Localisations</p>
                            <p class="text-sm font-bold text-gray-900">{{ $locTerminees }}<span class="text-gray-400 font-normal">/{{ $totalLoc }}</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Présents</p>
                            <p class="text-sm font-bold text-green-600">{{ $inventaireActif->scans_presents_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Déplacés</p>
                            <p class="text-sm font-bold text-amber-600">{{ $inventaireActif->scans_deplaces_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Absents</p>
                            <p class="text-sm font-bold text-red-600">{{ $inventaireActif->scans_absents_count ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ========================================== --}}
    {{-- FILTRES + TABLEAU                          --}}
    {{-- ========================================== --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {{-- Barre de filtres compacte --}}
        <div class="px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
            {{-- Filtres par statut (tabs) --}}
            <div class="flex items-center gap-1 flex-1">
                @php
                    $filtresStatut = [
                        'all' => 'Tous (' . $compteurs['total'] . ')',
                        'en_cours' => 'En cours',
                        'en_preparation' => 'Préparation',
                        'termine' => 'Terminés',
                        'cloture' => 'Clôturés',
                    ];
                @endphp
                @foreach($filtresStatut as $value => $label)
                    <button
                        wire:click="$set('filterStatut', '{{ $value }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                            {{ $filterStatut === $value
                                ? 'bg-indigo-100 text-indigo-700'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Filtre année --}}
            @if($this->annees->count() > 1)
                <select
                    wire:model.live="filterAnnee"
                    class="text-sm border-gray-200 rounded-lg py-1.5 pl-3 pr-8 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Toutes les années</option>
                    @foreach($this->annees as $annee)
                        <option value="{{ $annee }}">{{ $annee }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        {{-- Liste des inventaires --}}
        <div class="divide-y divide-gray-100">
            @forelse($inventaires as $inventaire)
                @php
                    $cfg = $statutConfig[$inventaire->statut] ?? $statutConfig['en_preparation'];
                    $isActif = in_array($inventaire->statut, ['en_cours', 'en_preparation']);

                    // Données pré-chargées via withCount (0 requêtes supplémentaires)
                    $totalLoc = $inventaire->inventaire_localisations_count ?? 0;
                    $locTerminees = $inventaire->localisations_terminees_count ?? 0;
                    $totalScans = $inventaire->inventaire_scans_count ?? 0;
                    $scansPresents = $inventaire->scans_presents_count ?? 0;
                    $totalAttendus = (int) ($inventaire->total_biens_attendus ?? 0);

                    // Progression = localisations terminées / total localisations
                    $progression = $totalLoc > 0 ? round(($locTerminees / $totalLoc) * 100, 1) : 0;

                    // Conformité réelle = présents / total attendus (pas juste scannés)
                    $conformite = $totalAttendus > 0 ? round(($scansPresents / $totalAttendus) * 100, 1) : 0;
                @endphp
                <div class="px-5 py-4 hover:bg-gray-50/50 transition-colors {{ $inventaire->statut === 'en_cours' ? 'bg-blue-50/30' : '' }}">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        {{-- Colonne gauche : année + statut + dates --}}
                        <div class="flex items-center gap-4 sm:w-56 flex-shrink-0">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-900 leading-none">{{ $inventaire->annee }}</p>
                            </div>
                            <div>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $cfg['bg'] }} {{ $cfg['text'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }}"></span>
                                    {{ $cfg['label'] }}
                                </span>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $inventaire->date_debut ? $inventaire->date_debut->format('d/m/Y') : '—' }}
                                    @if($inventaire->date_fin)
                                        → {{ $inventaire->date_fin->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Colonne centre : progression + stats --}}
                        <div class="flex-1 flex items-center gap-6">
                            {{-- Progression --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs text-gray-400">
                                        {{ $locTerminees }}/{{ $totalLoc }} loc.
                                    </span>
                                    <span class="text-xs font-semibold text-gray-600">{{ round($progression, 0) }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5">
                                    @php
                                        $barColor = match(true) {
                                            $progression >= 100 => 'bg-green-500',
                                            $progression >= 50  => 'bg-blue-500',
                                            $progression > 0    => 'bg-amber-500',
                                            default             => 'bg-gray-300',
                                        };
                                    @endphp
                                    <div class="{{ $barColor }} h-1.5 rounded-full transition-all" style="width: {{ min($progression, 100) }}%"></div>
                                </div>
                            </div>

                            {{-- Stats mini --}}
                            <div class="hidden md:flex items-center gap-4 text-xs flex-shrink-0">
                                <div class="text-center" title="Scans effectués sur {{ $totalAttendus }} attendus">
                                    <p class="font-bold text-gray-700">{{ $totalScans }}<span class="text-gray-400 font-normal">/{{ $totalAttendus }}</span></p>
                                    <p class="text-gray-400">scans</p>
                                </div>
                                @if($conformite > 0 || $totalScans > 0)
                                    <div class="text-center" title="Taux de conformité (présents / attendus)">
                                        @php
                                            $confColor = $conformite >= 90 ? 'text-green-600' : ($conformite >= 70 ? 'text-amber-600' : 'text-red-600');
                                        @endphp
                                        <p class="font-bold {{ $confColor }}">{{ round($conformite, 0) }}%</p>
                                        <p class="text-gray-400">conf.</p>
                                    </div>
                                @endif
                                @if($inventaire->date_debut)
                                    @php
                                        $debut = $inventaire->started_at ?? $inventaire->date_debut;
                                        $fin = $inventaire->finished_at ?? $inventaire->date_fin ?? now();
                                        $duree = $debut ? (int) floor(\Carbon\Carbon::parse($debut)->diffInDays($fin)) : 0;
                                    @endphp
                                    <div class="text-center" title="Durée">
                                        <p class="font-bold text-gray-700">{{ $duree }} {{ $duree > 1 ? 'jours' : 'jour' }}</p>
                                        <p class="text-gray-400">durée</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Colonne droite : actions --}}
                        <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                            <a
                                href="{{ route('inventaires.show', $inventaire) }}"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                                    {{ $isActif
                                        ? 'text-blue-700 bg-blue-50 hover:bg-blue-100'
                                        : 'text-gray-600 bg-gray-100 hover:bg-gray-200' }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Voir
                            </a>

                            @if($isAdmin && $isActif)
                                <button
                                    wire:click="ouvrirModalAgents({{ $inventaire->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    Agents
                                </button>
                            @endif

                            @if(in_array($inventaire->statut, ['termine', 'cloture']))
                                <a
                                    href="{{ route('inventaires.rapport', $inventaire) }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Rapport
                                </a>
                            @endif

                            @if($isAdmin)
                                {{-- Clôturer --}}
                                @if($inventaire->statut === 'termine')
                                    <button
                                        wire:click="archiverInventaire({{ $inventaire->id }})"
                                        wire:confirm="Clôturer l'inventaire {{ $inventaire->annee }} ? Cette action est définitive."
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Clôturer
                                    </button>
                                @endif

                                {{-- Exporter PDF --}}
                                @if(in_array($inventaire->statut, ['termine', 'cloture']))
                                    <form method="POST" action="{{ route('inventaires.export-pdf', $inventaire) }}">
                                        @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        PDF
                                    </button>
                                    </form>
                                @endif

                                {{-- Supprimer --}}
                                <button
                                    wire:click="supprimerInventaire({{ $inventaire->id }})"
                                    wire:confirm="Supprimer l'inventaire {{ $inventaire->annee }} ? Toutes les données seront définitivement perdues."
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Supprimer
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-16 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <h3 class="text-sm font-medium text-gray-900">Aucun inventaire</h3>
                    <p class="text-sm text-gray-500 mt-1 mb-4">
                        @if($filterStatut !== 'all' || !empty($filterAnnee))
                            Aucun résultat pour ces filtres.
                            <button wire:click="resetFilters" class="text-indigo-600 hover:underline">Réinitialiser</button>
                        @else
                            Créez votre premier inventaire pour commencer.
                        @endif
                    </p>
                    @if($isAdmin && $filterStatut === 'all' && empty($filterAnnee))
                        <a
                            href="{{ route('inventaires.create') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Créer un inventaire
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($inventaires->hasPages())
            <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
                <select
                    wire:model.live="perPage"
                    class="text-xs border-gray-200 rounded-lg py-1 pl-2 pr-7 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="10">10 / page</option>
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                </select>
                <div class="text-sm">
                    {{ $inventaires->links() }}
                </div>
            </div>
        @endif
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

    {{-- ========================================== --}}
    {{-- MODALE GESTION DES AGENTS                  --}}
    {{-- ========================================== --}}
    @if($showAgentsModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ agentGlobal: '' }">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-black/50 transition-opacity" wire:click="fermerModalAgents"></div>

            {{-- Contenu modale --}}
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col z-10">
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Gestion des agents</h2>
                        <p class="text-sm text-gray-500">Inventaire {{ $modalInventaireAnnee }} — {{ $this->modalLocalisations->count() }} localisation(s)</p>
                    </div>
                    <button wire:click="fermerModalAgents" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Assignation rapide --}}
                <div class="px-6 py-3 bg-indigo-50/50 border-b border-indigo-100 flex flex-col sm:flex-row gap-3 flex-shrink-0">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-indigo-700 mb-1">Ajouter un agent à toutes les localisations</label>
                        <select x-model="agentGlobal" class="block w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <option value="">Choisir un agent...</option>
                            @foreach($this->allAgents as $ag)
                                <option value="{{ $ag->idUser }}">{{ $ag->users }} ({{ $ag->role_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="if(agentGlobal) { $wire.ajouterAgentPartout(agentGlobal); agentGlobal = ''; }"
                            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            Appliquer
                        </button>
                    </div>
                </div>

                {{-- Liste des localisations --}}
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    @forelse($this->modalLocalisations as $invLoc)
                        @php
                            $locAgents = $invLoc->agents->isNotEmpty() ? $invLoc->agents : collect();
                        @endphp
                        <div wire:key="modal-loc-{{ $invLoc->id }}" class="px-6 py-3">
                            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                                {{-- Info localisation --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $invLoc->localisation->localisation ?? 'N/A' }}</p>
                                        @php
                                            $statCfg = [
                                                'en_attente' => 'bg-gray-100 text-gray-600',
                                                'en_cours' => 'bg-blue-50 text-blue-700',
                                                'termine' => 'bg-green-50 text-green-700',
                                            ];
                                        @endphp
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded {{ $statCfg[$invLoc->statut] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst(str_replace('_', ' ', $invLoc->statut)) }}</span>
                                    </div>

                                    {{-- Tags agents --}}
                                    @if($locAgents->isNotEmpty())
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            @foreach($locAgents as $ag)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                                    <span class="w-4 h-4 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[9px] font-bold flex-shrink-0">{{ mb_substr($ag->users, 0, 1) }}</span>
                                                    {{ $ag->users }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-amber-500 mt-1 italic">Aucun agent</p>
                                    @endif
                                </div>

                                {{-- Dropdown multi-select --}}
                                <div class="sm:w-48 flex-shrink-0" x-data="{ open: false }">
                                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white hover:bg-gray-50 transition-colors">
                                        <span class="text-gray-500 text-xs">
                                            @if($locAgents->count() > 0)
                                                {{ $locAgents->count() }} agent(s)
                                            @else
                                                Assigner...
                                            @endif
                                        </span>
                                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition x-cloak
                                        class="absolute right-6 mt-1 w-52 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-40 max-h-48 overflow-y-auto">
                                        @foreach($this->allAgents as $ag)
                                            @php $isSelected = $locAgents->contains('idUser', $ag->idUser); @endphp
                                            <button
                                                type="button"
                                                wire:click="toggleAgentLoc({{ $invLoc->id }}, {{ $ag->idUser }})"
                                                class="w-full flex items-center gap-2 text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors {{ $isSelected ? 'bg-indigo-50/60' : '' }}">
                                                <div class="w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0
                                                    {{ $isSelected ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white' }}">
                                                    @if($isSelected)
                                                        <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    @endif
                                                </div>
                                                <span class="truncate {{ $isSelected ? 'text-indigo-700 font-medium' : 'text-gray-700' }}">{{ $ag->users }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-gray-400 text-sm">Aucune localisation.</div>
                    @endforelse
                </div>

                {{-- Footer --}}
                <div class="px-6 py-3 border-t border-gray-200 flex-shrink-0 flex items-center justify-between">
                    @php
                        $assignees = $this->modalLocalisations->filter(fn($il) => $il->agents->isNotEmpty())->count();
                        $distinctAgents = $this->modalLocalisations->flatMap(fn($il) => $il->agents)->unique('idUser')->count();
                    @endphp
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span><strong class="text-indigo-600">{{ $assignees }}</strong>/{{ $this->modalLocalisations->count() }} assignée(s)</span>
                        <span><strong class="text-gray-900">{{ $distinctAgents }}</strong> agent(s) distinct(s)</span>
                    </div>
                    <button wire:click="fermerModalAgents" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
