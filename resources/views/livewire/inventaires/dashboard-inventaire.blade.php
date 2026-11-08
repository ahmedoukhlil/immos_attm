<div
    x-data="{
        confirmation: null,
        confirmationTrigger: null,
        askConfirmation(event, options) {
            this.confirmationTrigger = event.currentTarget;
            this.confirmation = options;
            this.$nextTick(() => this.$refs.confirmationButton?.focus());
        },
        closeConfirmation() {
            this.confirmation = null;
            this.$nextTick(() => this.confirmationTrigger?.focus());
        }
    }"
    @keydown.escape.window="if (confirmation) closeConfirmation()">
    @php
        $isAdmin = auth()->user()->canManageOperations();
        $stats = $this->statistiques;
        $statutsInventaire = [
            'en_preparation' => ['label' => 'En préparation', 'color' => 'bg-gray-100 text-gray-800', 'dot' => 'bg-gray-400'],
            'en_cours' => ['label' => 'En cours', 'color' => 'bg-blue-50 text-blue-700', 'dot' => 'bg-blue-500'],
            'termine' => ['label' => 'Terminé', 'color' => 'bg-amber-50 text-amber-700', 'dot' => 'bg-amber-500'],
            'cloture' => ['label' => 'Clôturé', 'color' => 'bg-green-50 text-green-700', 'dot' => 'bg-green-500'],
        ];
        $statutsLoc = [
            'en_attente' => ['label' => 'En attente', 'color' => 'bg-gray-100 text-gray-700'],
            'en_cours' => ['label' => 'En cours', 'color' => 'bg-blue-50 text-blue-700'],
            'termine' => ['label' => 'Terminée', 'color' => 'bg-green-50 text-green-700'],
        ];
        $cfgStatut = $statutsInventaire[$inventaire->statut] ?? $statutsInventaire['en_preparation'];
        $localisationsRestantes = max(0, ($stats['total_localisations'] ?? 0) - ($stats['localisations_terminees'] ?? 0));
    @endphp

    @if(in_array($inventaire->statut, ['en_preparation', 'en_cours']))
        <div wire:poll.10s="refreshStatistiques" class="hidden"></div>
    @endif

    {{-- ========================================== --}}
    {{-- EN-TETE                                     --}}
    {{-- ========================================== --}}
    <div class="mb-6">
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                <li><a href="{{ route('dashboard') }}" class="hover:text-indigo-600 transition-colors">Tableau de bord</a></li>
                <li><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></li>
                <li><a href="{{ route('inventaires.index') }}" class="hover:text-indigo-600 transition-colors">Inventaires</a></li>
                <li><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></li>
                <li class="text-gray-700 font-medium">{{ $inventaire->annee }}</li>
            </ol>
        </nav>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900">Inventaire {{ $inventaire->annee }}</h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $cfgStatut['color'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $cfgStatut['dot'] }}"></span>
                        {{ $cfgStatut['label'] }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ ($inventaire->started_at ?? $inventaire->date_debut)?->translatedFormat('d M Y') }} — {{ ($inventaire->finished_at ?? $inventaire->date_fin)?->translatedFormat('d M Y') ?? 'En cours' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($isAdmin)
                    @if($inventaire->statut === 'en_preparation')
                        <button type="button" @click="askConfirmation($event, { action: 'passerEnCours', title: 'Démarrer l’inventaire', message: 'Les agents pourront commencer les opérations de comptage et de rapprochement.', label: 'Démarrer', targetStatus: 'En cours', tone: 'indigo' })" wire:loading.attr="disabled" wire:target="passerEnCours" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            <svg wire:loading.remove wire:target="passerEnCours" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <svg wire:loading wire:target="passerEnCours" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Démarrer
                        </button>
                    @elseif($inventaire->statut === 'en_cours')
                        @if($localisationsRestantes === 0)
                            <button type="button" @click="askConfirmation($event, { action: 'terminerInventaire', title: 'Terminer l’inventaire', message: 'Toutes les localisations sont traitées. Confirmez la fin de l’inventaire.', label: 'Terminer', targetStatus: 'Terminé', tone: 'amber' })" wire:loading.attr="disabled" wire:target="terminerInventaire" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 disabled:opacity-50 transition-colors">
                                <svg wire:loading.remove wire:target="terminerInventaire" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg wire:loading wire:target="terminerInventaire" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Terminer
                            </button>
                        @else
                            <button type="button" disabled title="{{ $localisationsRestantes }} localisation(s) restent à traiter"
                                class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                                {{ $localisationsRestantes }} localisation(s) restante(s)
                            </button>
                        @endif
                    @elseif($inventaire->statut === 'termine')
                        <button type="button" @click="askConfirmation($event, { action: 'cloturerInventaire', title: 'Clôturer définitivement', message: 'Cette action est irréversible. Le rapport final sera généré après la clôture.', label: 'Clôturer', targetStatus: 'Clôturé', tone: 'red' })" wire:loading.attr="disabled" wire:target="cloturerInventaire" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors">
                            <svg wire:loading.remove wire:target="cloturerInventaire" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <svg wire:loading wire:target="cloturerInventaire" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Clôturer
                        </button>
                    @endif
                @endif
                @if($isAdmin && $inventaire->statut === 'cloture')
                    <a href="{{ route('inventaires.report', $inventaire) }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        Report et sorties
                    </a>
                @endif
                @if(in_array($inventaire->statut, ['termine', 'cloture']))
                    <a href="{{ route('inventaires.rapport', $inventaire) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Voir rapport
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- CARTES KPI                                  --}}
    {{-- ========================================== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Progression --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Progression</p>
            <p class="text-3xl font-bold text-gray-900">{{ round($stats['progression_globale'], 1) }}<span class="text-lg text-gray-400">%</span></p>
            <div class="w-full bg-gray-100 rounded-full h-2 mt-3 mb-2">
                <div class="bg-indigo-600 h-2 rounded-full transition-all duration-700" style="width: {{ min($stats['progression_globale'], 100) }}%"></div>
            </div>
            <p class="text-xs text-gray-500">
                {{ $stats['total_biens_traites'] }}/{{ $stats['total_biens_attendus'] }} rapprochés
                · {{ $stats['total_biens_scannes'] }} scannés physiquement
            </p>
            <p class="mt-1 text-xs font-medium text-gray-700">{{ $stats['biens_non_verifies'] }} restant(s) à vérifier</p>
        </div>

        {{-- Conformité --}}
        @php
            $confColor = $stats['taux_conformite'] >= 90 ? 'text-green-600' : ($stats['taux_conformite'] >= 70 ? 'text-amber-600' : 'text-red-600');
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Conformité</p>
            <p class="text-3xl font-bold {{ $confColor }}">{{ round($stats['taux_conformite'], 1) }}<span class="text-lg">%</span></p>
            <div class="mt-3 space-y-1">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Conformes</span>
                    <span class="font-semibold text-gray-700">{{ $stats['biens_presents'] }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Déplacés</span>
                    <span class="font-semibold text-gray-700">{{ $stats['biens_deplaces'] }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Absents</span>
                    <span class="font-semibold text-gray-700">{{ $stats['biens_absents'] }}</span>
                </div>
            </div>
        </div>

        {{-- Sites (unité de travail des agents) et localisations --}}
        @php $sites = $this->statistiquesSites; @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sites</p>
            <p class="text-3xl font-bold text-gray-900">{{ $sites['termines'] }}<span class="text-lg text-gray-400">/{{ $sites['total'] }}</span></p>
            <div class="mt-3 space-y-1">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">Terminés</span>
                    <span class="font-semibold text-green-600">{{ $sites['termines'] }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">En cours</span>
                    <span class="font-semibold text-blue-600">{{ $sites['en_cours'] }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">En attente</span>
                    <span class="font-semibold text-gray-400">{{ $sites['en_attente'] }}</span>
                </div>
            </div>
            <p class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500">
                Localisations : {{ $stats['localisations_terminees'] }}/{{ $stats['total_localisations'] }} terminée(s)
            </p>
        </div>

        {{-- Alertes --}}
        @php $totalAlertes = $this->totalAlertes; @endphp
        <div class="bg-white rounded-xl shadow-sm border {{ $totalAlertes > 0 ? 'border-red-200' : 'border-gray-200' }} p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Alertes</p>
            <p class="text-3xl font-bold {{ $totalAlertes > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $totalAlertes }}</p>
            @if($totalAlertes > 0)
                <div class="mt-3 space-y-1 text-xs">
                    @if($stats['biens_absents'] > 0)
                        <p class="text-gray-500">{{ $stats['biens_absents'] }} absent(s)</p>
                    @endif
                    @if(($stats['biens_defectueux'] ?? 0) > 0)
                        <p class="text-gray-500">{{ $stats['biens_defectueux'] }} défectueux</p>
                    @endif
                </div>
                <a href="#alertes-detail" class="text-xs text-red-600 hover:text-red-800 font-medium inline-flex items-center gap-1 mt-2">
                    Voir détails
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
            @else
                <p class="text-xs text-green-600 mt-3">Aucune anomalie</p>
            @endif
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- DÉTAIL DES RÉSULTATS                         --}}
    {{-- ========================================== --}}
    @php
        $totalPhysiques = max(1, ($stats['biens_neufs'] ?? 0) + ($stats['biens_bon_etat'] ?? 0) + ($stats['biens_defectueux'] ?? 0));
        $etatsConstates = [
            ['libelle' => 'Neuf', 'valeur' => $stats['biens_neufs'] ?? 0, 'barre' => 'bg-sky-500'],
            ['libelle' => 'Bon état', 'valeur' => $stats['biens_bon_etat'] ?? 0, 'barre' => 'bg-green-500'],
            ['libelle' => 'Défectueux', 'valeur' => $stats['biens_defectueux'] ?? 0, 'barre' => 'bg-red-500'],
        ];
    @endphp
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        {{-- État constaté (biens vus physiquement) --}}
        <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-5" aria-labelledby="stats-etat-titre">
            <h2 id="stats-etat-titre" class="text-sm font-semibold text-gray-900">État constaté</h2>
            <p class="text-xs text-gray-500 mb-4">{{ $stats['total_biens_scannes'] }} bien(s) vus physiquement</p>
            <div class="space-y-3">
                @foreach($etatsConstates as $etat)
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-gray-600">{{ $etat['libelle'] }}</span>
                            <span class="font-semibold tabular-nums text-gray-900">{{ $etat['valeur'] }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-1.5 rounded-full {{ $etat['barre'] }}" style="width: {{ round($etat['valeur'] / $totalPhysiques * 100, 1) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 text-xs">
                <div>
                    <dt class="text-gray-500">Taux de couverture</dt>
                    <dd class="font-semibold tabular-nums text-gray-900">{{ round($stats['taux_couverture'] ?? 0, 1) }} %</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Taux d'absence</dt>
                    <dd class="font-semibold tabular-nums text-gray-900">{{ round($stats['taux_absence'] ?? 0, 1) }} %</dd>
                </div>
            </dl>
        </section>

        {{-- Par catégorie --}}
        <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 xl:col-span-2 min-w-0" aria-labelledby="stats-categorie-titre">
            <h2 id="stats-categorie-titre" class="text-sm font-semibold text-gray-900">Par catégorie</h2>
            <p class="text-xs text-gray-500 mb-3">Biens attendus et résultats du rapprochement</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-[11px] uppercase tracking-wider text-gray-500">
                            <th class="py-2 pr-3 font-semibold">Catégorie</th>
                            <th class="py-2 px-3 text-right font-semibold">Attendus</th>
                            <th class="py-2 px-3 text-right font-semibold">Présents</th>
                            <th class="py-2 px-3 text-right font-semibold">Déplacés</th>
                            <th class="py-2 px-3 text-right font-semibold">Absents</th>
                            <th class="py-2 px-3 text-right font-semibold">Restants</th>
                            <th class="py-2 pl-3 text-right font-semibold">Conformité</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse(collect($stats['par_nature'] ?? [])->sortByDesc('total') as $nature)
                            <tr>
                                <td class="py-2 pr-3 font-medium text-gray-900">{{ $nature['nature'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $nature['total'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-green-700">{{ $nature['presents'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-amber-700">{{ $nature['deplaces'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-red-700">{{ $nature['absents'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-gray-500">{{ max(0, $nature['total'] - $nature['presents'] - $nature['deplaces'] - $nature['absents']) }}</td>
                                <td class="py-2 pl-3 text-right tabular-nums font-semibold">{{ round($nature['conformite'], 1) }} %</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 text-center text-gray-400">Aucune donnée</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- ========================================== --}}
    {{-- DÉTAIL DES IMMOBILISATIONS SCANNÉES          --}}
    {{-- ========================================== --}}
    @php
        $scansDetail = $this->scansDetail;
        $libellesStatut = [
            'present' => ['libelle' => 'Présent', 'classes' => 'bg-green-50 text-green-700 ring-green-200'],
            'deplace' => ['libelle' => 'Déplacé', 'classes' => 'bg-amber-50 text-amber-700 ring-amber-200'],
            'absent' => ['libelle' => 'Absent', 'classes' => 'bg-red-50 text-red-700 ring-red-200'],
            'deteriore' => ['libelle' => 'Détérioré', 'classes' => 'bg-orange-50 text-orange-700 ring-orange-200'],
        ];
        $libellesEtat = ['neuf' => 'Neuf', 'bon_etat' => 'Bon état', 'defectueux' => 'Défectueux'];
    @endphp
    <section id="detail-scans" class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6" aria-labelledby="detail-scans-titre">
        <div class="flex flex-wrap items-end justify-between gap-3 px-5 pt-5">
            <div>
                <h2 id="detail-scans-titre" class="text-sm font-semibold text-gray-900">Détail des immobilisations scannées</h2>
                <p class="text-xs text-gray-500">{{ $scansDetail->total() }} ligne(s) · du plus récent au plus ancien</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label for="scan-recherche" class="sr-only">Rechercher</label>
                <input id="scan-recherche" type="search" wire:model.live.debounce.300ms="scanRecherche"
                    placeholder="Code, désignation, GS…"
                    class="w-56 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <label for="scan-statut" class="sr-only">Statut</label>
                <select id="scan-statut" wire:model.live="scanStatut" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="physiques">Scannés (présents + déplacés)</option>
                    <option value="present">Présents</option>
                    <option value="deplace">Déplacés</option>
                    <option value="absent">Absents constatés</option>
                    <option value="tous">Toutes les lignes</option>
                </select>
                <label for="scan-etat" class="sr-only">État constaté</label>
                <select id="scan-etat" wire:model.live="scanEtat" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tous les états</option>
                    <option value="neuf">Neuf</option>
                    <option value="bon_etat">Bon état</option>
                    <option value="defectueux">Défectueux</option>
                </select>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500">
                        <th class="px-5 py-2.5 font-semibold">Immobilisation</th>
                        <th class="px-3 py-2.5 font-semibold">Site attendu</th>
                        <th class="px-3 py-2.5 font-semibold">Trouvé à</th>
                        <th class="px-3 py-2.5 font-semibold">Statut</th>
                        <th class="px-3 py-2.5 font-semibold">État constaté</th>
                        <th class="px-3 py-2.5 font-semibold">Agent</th>
                        <th class="px-5 py-2.5 font-semibold text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($scansDetail as $scan)
                        @php
                            $statut = $libellesStatut[$scan->statut_scan] ?? ['libelle' => $scan->statut_scan, 'classes' => 'bg-gray-50 text-gray-700 ring-gray-200'];
                            $codeAffiche = $scan->code_fige ?: ($scan->bien?->code_formate ?? 'GS'.$scan->immobilisation_id);
                            $designation = $scan->designation_figee ?: ($scan->bien?->libelle_article ?? 'Immobilisation');
                            $siteAttendu = $scan->site_attendu ?: $scan->bien?->site?->site;
                        @endphp
                        <tr wire:key="scan-detail-{{ $scan->id }}" class="hover:bg-gray-50/60">
                            <td class="px-5 py-2.5">
                                <a href="{{ route('biens.show', $scan->immobilisation_id) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $designation }}</a>
                                <p class="font-mono text-xs text-gray-500">{{ $codeAffiche }} · GS{{ $scan->immobilisation_id }}</p>
                            </td>
                            <td class="px-3 py-2.5 text-gray-700">{{ $siteAttendu ?? '—' }}</td>
                            <td class="px-3 py-2.5 {{ $scan->statut_scan === 'deplace' ? 'font-medium text-amber-700' : 'text-gray-700' }}">
                                {{ $scan->statut_scan === 'absent' ? '—' : ($scan->siteReel?->site ?? '—') }}
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $statut['classes'] }}">{{ $statut['libelle'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-gray-700">
                                @if($scan->statut_scan === 'absent')
                                    <span class="text-gray-400">—</span>
                                @else
                                    <span class="{{ $scan->etat_constate === 'defectueux' ? 'font-medium text-red-700' : '' }}">{{ $libellesEtat[$scan->etat_constate] ?? $scan->etat_constate }}</span>
                                    @if($scan->photo_url)
                                        <a href="{{ $scan->photo_url }}" target="_blank" rel="noopener" class="ml-1 text-xs font-medium text-indigo-600 hover:text-indigo-800">Photo</a>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-700">{{ $scan->agent?->users ?? 'Système' }}</td>
                            <td class="px-5 py-2.5 text-right text-xs tabular-nums text-gray-500 whitespace-nowrap">{{ $scan->date_scan?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">
                                {{ $scanRecherche !== '' || $scanEtat !== '' || $scanStatut !== 'physiques' ? 'Aucune ligne ne correspond à ces filtres.' : 'Aucune immobilisation n\'a encore été scannée.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($scansDetail->hasPages())
            <div class="border-t border-gray-100 px-5 py-3">
                {{ $scansDetail->links(data: ['scrollTo' => '#detail-scans']) }}
            </div>
        @endif
    </section>

    {{-- Par agent --}}
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6" aria-labelledby="stats-agent-titre">
        <h2 id="stats-agent-titre" class="text-sm font-semibold text-gray-900">Par agent</h2>
        <p class="text-xs text-gray-500 mb-3">Biens scannés physiquement par chaque agent (les absents constatés ne sont pas comptés)</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @forelse($stats['par_agent'] ?? [] as $agent)
                <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">{{ mb_substr($agent['agent_name'], 0, 1) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $agent['agent_name'] }}</p>
                        <p class="text-xs text-gray-500"><span class="font-semibold tabular-nums text-gray-900">{{ $agent['biens_scannes'] }}</span> scanné(s) · {{ $agent['localisations'] }} localisation(s)</p>
                    </div>
                </div>
            @empty
                <p class="text-xs text-gray-400">Aucun agent n'a encore scanné.</p>
            @endforelse
        </div>
    </section>

    {{-- ========================================== --}}
    {{-- GRAPHIQUE TEMPOREL (collapsible)            --}}
    {{-- ========================================== --}}
    <div x-data="{ showChart: false }" class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
        <script type="application/json" id="inventaire-scans-graph-data">@json($this->scansGraphData)</script>
        <span id="inventaire-scans-objectif" data-value="{{ $stats['total_biens_attendus'] }}" class="hidden"></span>
        <button @click="showChart = !showChart; if(showChart) $nextTick(() => window.dispatchEvent(new Event('init-chart')))" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div class="text-left">
                    <p class="text-sm font-semibold text-gray-900">Progression temporelle</p>
                    <p class="text-xs text-gray-500">{{ $stats['vitesse_moyenne'] }} scans/jour en moyenne @if($stats['scans_aujourdhui'] > 0) &middot; <span class="text-indigo-600 font-medium">{{ $stats['scans_aujourdhui'] }} aujourd'hui</span> @endif</p>
                </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': showChart }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="showChart" x-collapse x-cloak>
            <div class="px-6 pb-6 relative" style="height: 280px;">
                <canvas id="chart-progression-temporelle"></canvas>
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- ALERTES                                     --}}
    {{-- ========================================== --}}
    @if($totalAlertes > 0)
        @php $alertes = $this->alertes; @endphp
        <div id="alertes-detail" x-data="{ expanded: false }" class="bg-white rounded-xl shadow-sm border border-red-200 mb-6 overflow-hidden">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-6 py-4 hover:bg-red-50/30 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                        <svg class="h-4 w-4 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-red-800">{{ $totalAlertes }} alerte(s)</p>
                    <div class="hidden sm:flex items-center gap-1.5">
                        @if(count($alertes['localisations_bloquees']) > 0)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-700">{{ count($alertes['localisations_bloquees']) }} bloquée(s)</span>
                        @endif
                        @if(count($alertes['biens_defectueux'] ?? []) > 0)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-orange-100 text-orange-700">{{ count($alertes['biens_defectueux']) }} défectueux</span>
                        @endif
                        @if(count($alertes['localisations_non_assignees']) > 0)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-700">{{ count($alertes['localisations_non_assignees']) }} non assignée(s)</span>
                        @endif
                    </div>
                </div>
                <svg class="w-5 h-5 text-red-400 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="expanded" x-collapse x-cloak>
                <div class="border-t border-red-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-px bg-red-50">
                    @if(count($alertes['localisations_bloquees']) > 0)
                        <div class="bg-white p-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Localisations bloquées</h4>
                            <div class="space-y-1.5">
                                @foreach($alertes['localisations_bloquees'] as $alerte)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-medium text-red-800">{{ $alerte['code'] }}</span>
                                        <span class="text-xs text-red-600">{{ $alerte['jours'] }}j sans scan</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($alertes['biens_absents']) > 0)
                        <div class="bg-white p-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Immobilisations absentes</h4>
                            <div class="space-y-1.5">
                                @foreach(array_slice($alertes['biens_absents'], 0, 5) as $alerte)
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-xs font-mono text-red-700 bg-red-50 px-1.5 py-0.5 rounded">{{ $alerte['code'] }}</span>
                                        <span class="text-gray-700 truncate">{{ $alerte['designation'] }}</span>
                                    </div>
                                @endforeach
                                @if(count($alertes['biens_absents']) > 5)
                                    <p class="text-xs text-gray-400">+{{ count($alertes['biens_absents']) - 5 }} autre(s)</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if(count($alertes['localisations_non_assignees']) > 0)
                        <div class="bg-white p-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Non assignées</h4>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach(array_slice($alertes['localisations_non_assignees'], 0, 8) as $alerte)
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">{{ $alerte['code'] }}</span>
                                @endforeach
                                @if(count($alertes['localisations_non_assignees']) > 8)
                                    <span class="px-2 py-0.5 rounded text-xs text-gray-400">+{{ count($alertes['localisations_non_assignees']) - 8 }}</span>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if(count($alertes['biens_defectueux'] ?? []) > 0)
                        <div class="bg-white p-4 sm:col-span-2 lg:col-span-3">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Défectueux ({{ count($alertes['biens_defectueux']) }})</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach(array_slice($alertes['biens_defectueux'], 0, 9) as $alerte)
                                    <div x-data="{ showPhoto: false }" class="flex items-start gap-3 rounded-lg border border-orange-200 bg-orange-50/50 p-3">
                                        @if(!empty($alerte['photo_url']))
                                            <button @click="showPhoto = true" class="flex-shrink-0 w-12 h-12 rounded-lg overflow-hidden border-2 border-orange-300 hover:border-orange-500 transition-colors cursor-pointer relative group">
                                                <img src="{{ $alerte['photo_url'] }}" alt="Photo" class="w-full h-full object-cover">
                                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                                </div>
                                            </button>
                                            <div x-show="showPhoto" x-cloak x-transition @click="showPhoto = false" @keydown.escape.window="showPhoto = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
                                                <div @click.stop class="relative max-w-3xl max-h-[85vh] bg-white rounded-xl shadow-2xl overflow-hidden">
                                                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
                                                        <div>
                                                            <p class="text-sm font-semibold text-gray-900">{{ $alerte['code'] }}</p>
                                                            <p class="text-xs text-gray-500">{{ $alerte['designation'] }} &middot; {{ $alerte['localisation'] }}</p>
                                                        </div>
                                                        <button @click="showPhoto = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition-colors">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </div>
                                                    <div class="p-2 bg-gray-100">
                                                        <img src="{{ $alerte['photo_url'] }}" alt="Photo {{ $alerte['code'] }}" class="max-h-[70vh] w-auto mx-auto rounded-lg">
                                                    </div>
                                                    @if(!empty($alerte['commentaire']))
                                                        <div class="px-4 py-3 border-t border-gray-200">
                                                            <p class="text-xs text-gray-500 uppercase font-medium mb-1">Commentaire</p>
                                                            <p class="text-sm text-gray-700">{{ $alerte['commentaire'] }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-orange-100 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs font-mono font-semibold text-orange-800">{{ $alerte['code'] }}</span>
                                            <p class="text-xs text-gray-600 truncate mt-0.5" title="{{ $alerte['designation'] }}">{{ $alerte['designation'] }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">{{ $alerte['localisation'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if(count($alertes['biens_defectueux']) > 9)
                                <p class="text-xs text-gray-400 mt-2">+{{ count($alertes['biens_defectueux']) - 9 }} autre(s)</p>
                            @endif
                        </div>
                    @endif

                    @if(count($alertes['localisations_non_demarrees']) > 0)
                        <div class="bg-white p-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Non démarrées</h4>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach(array_slice($alertes['localisations_non_demarrees'], 0, 8) as $alerte)
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">{{ $alerte['code'] }}</span>
                                @endforeach
                                @if(count($alertes['localisations_non_demarrees']) > 8)
                                    <span class="px-2 py-0.5 rounded text-xs text-gray-400">+{{ count($alertes['localisations_non_demarrees']) - 8 }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ========================================== --}}
    {{-- TABLEAU LOCALISATIONS                       --}}
    {{-- ========================================== --}}
    @php
        $allInvLocs = $this->inventaireLocalisations;
        $countByStatut = [
            'en_attente' => $inventaire->inventaireLocalisations->where('statut', 'en_attente')->count(),
            'en_cours'   => $inventaire->inventaireLocalisations->where('statut', 'en_cours')->count(),
            'termine'    => $inventaire->inventaireLocalisations->where('statut', 'termine')->count(),
        ];
        $totalLocs = $inventaire->inventaireLocalisations->count();
        $hasActiveFilters = $searchLoc || $filterStatutLoc !== 'all' || $filterAgent !== 'all';
    @endphp
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        {{-- Header --}}
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-semibold text-gray-900">Localisations</h2>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2.5 py-0.5 rounded-full font-medium tabular-nums">{{ $totalLocs }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <button wire:click="$set('filterStatutLoc', 'all')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-all {{ $filterStatutLoc === 'all' ? 'bg-gray-900 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Tous
                    </button>
                    <button wire:click="$set('filterStatutLoc', 'en_attente')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-all {{ $filterStatutLoc === 'en_attente' ? 'bg-gray-600 text-white shadow-sm' : 'bg-gray-50 text-gray-500 hover:bg-gray-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        Attente <span class="tabular-nums opacity-70">{{ $countByStatut['en_attente'] }}</span>
                    </button>
                    <button wire:click="$set('filterStatutLoc', 'en_cours')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-all {{ $filterStatutLoc === 'en_cours' ? 'bg-blue-600 text-white shadow-sm' : 'bg-blue-50 text-blue-600 hover:bg-blue-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        En cours <span class="tabular-nums opacity-70">{{ $countByStatut['en_cours'] }}</span>
                    </button>
                    <button wire:click="$set('filterStatutLoc', 'termine')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-all {{ $filterStatutLoc === 'termine' ? 'bg-green-600 text-white shadow-sm' : 'bg-green-50 text-green-600 hover:bg-green-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        Terminées <span class="tabular-nums opacity-70">{{ $countByStatut['termine'] }}</span>
                    </button>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" wire:model.live.debounce.300ms="searchLoc" aria-label="Rechercher une localisation" placeholder="Rechercher par code ou nom..." class="w-full pl-9 pr-8 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition-all">
                    @if($searchLoc)
                        <button wire:click="$set('searchLoc', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
                <select wire:model.live="filterAgent" class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition-all sm:w-48">
                    <option value="all">Tous les agents</option>
                    @foreach($this->agents as $agent)
                        <option value="{{ $agent->idUser }}">{{ $agent->users ?? 'Agent' }}</option>
                    @endforeach
                </select>
            </div>
            @if($hasActiveFilters)
                <div class="flex items-center gap-2 mt-2.5 pt-2.5 border-t border-gray-100">
                    <span class="text-[11px] text-gray-400">Filtres :</span>
                    @if($searchLoc)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 font-medium">
                            "{{ $searchLoc }}"
                            <button wire:click="$set('searchLoc', '')" class="hover:text-indigo-900 transition-colors"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif
                    @if($filterAgent !== 'all')
                        @php $selectedAgent = $this->agents->firstWhere('idUser', $filterAgent); @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 font-medium">
                            {{ $selectedAgent->users ?? 'Agent' }}
                            <button wire:click="$set('filterAgent', 'all')" class="hover:text-indigo-900 transition-colors"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif
                    <button wire:click="$set('searchLoc', ''); $set('filterStatutLoc', 'all'); $set('filterAgent', 'all')" class="ml-auto text-[11px] text-gray-400 hover:text-red-500 transition-colors font-medium">
                        Tout effacer
                    </button>
                </div>
            @endif
        </div>

        <div class="relative">
            <div wire:loading.flex wire:target="searchLoc, filterStatutLoc, filterAgent, sortBy, toggleAgentLocalisation" class="absolute inset-0 bg-white/70 backdrop-blur-[2px] z-20 items-center justify-center">
                <div class="flex items-center gap-2.5 px-4 py-2 bg-white rounded-lg shadow-sm border border-gray-200">
                    <svg class="w-4 h-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-sm text-gray-600">Chargement...</span>
                </div>
            </div>

            {{-- Vue desktop --}}
            <div class="hidden md:block overflow-x-auto max-h-[36rem] overflow-y-auto">
                <table class="min-w-full">
                    <thead class="sticky top-0 z-[5]">
                        <tr class="bg-gray-50/95 backdrop-blur-sm border-b border-gray-200">
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-indigo-600 transition-colors select-none" wire:click="sortBy('code')">
                                <div class="flex items-center gap-1">
                                    Code
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $sortField === 'code' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3l7 7H3l7-7z"/></svg>
                                        <svg class="w-2.5 h-2.5 -mt-0.5 {{ $sortField === 'code' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M10 17l-7-7h14l-7 7z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Désignation</th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-indigo-600 transition-colors select-none" wire:click="sortBy('progression')">
                                <div class="flex items-center gap-1">
                                    Progression
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $sortField === 'progression' && $sortDirection === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3l7 7H3l7-7z"/></svg>
                                        <svg class="w-2.5 h-2.5 -mt-0.5 {{ $sortField === 'progression' && $sortDirection === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M10 17l-7-7h14l-7 7z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Agent(s)</th>
                            <th class="px-5 py-2.5 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($allInvLocs as $invLoc)
                            @php
                                $progression = $invLoc->nombre_biens_attendus > 0 ? round(($invLoc->nombre_biens_scannes / $invLoc->nombre_biens_attendus) * 100, 1) : 0;
                                $allAgents = $invLoc->agents->isNotEmpty() ? $invLoc->agents : ($invLoc->agent ? collect([$invLoc->agent]) : collect());
                                $rowBorderColor = match($invLoc->statut) {
                                    'termine' => 'border-l-green-500',
                                    'en_cours' => 'border-l-blue-500',
                                    default => 'border-l-gray-200',
                                };
                                $progressBarColor = $progression >= 100 ? 'bg-green-500' : ($progression >= 50 ? 'bg-blue-500' : ($progression > 0 ? 'bg-indigo-400' : 'bg-gray-200'));
                                $progressTextColor = $progression >= 100 ? 'text-green-700 font-semibold' : ($progression > 0 ? 'text-gray-700' : 'text-gray-400');
                            @endphp
                            <tr class="group hover:bg-indigo-50/30 transition-colors border-l-[3px] {{ $rowBorderColor }}">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="text-sm font-mono font-semibold text-gray-900">{{ $invLoc->localisation->code ?? 'N/A' }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('localisations.show', $invLoc->localisation) }}" class="text-sm text-gray-700 hover:text-indigo-600 transition-colors max-w-[240px] truncate block" title="{{ $invLoc->localisation->localisation ?? '' }}">
                                        {{ $invLoc->localisation->localisation ?? 'N/A' }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @php
                                        $statutIcon = match($invLoc->statut) {
                                            'termine' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
                                            'en_cours' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
                                            default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium {{ $statutsLoc[$invLoc->statut]['color'] ?? 'bg-gray-100 text-gray-700' }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $statutIcon !!}</svg>
                                        {{ $statutsLoc[$invLoc->statut]['label'] ?? $invLoc->statut }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3 min-w-[160px]">
                                        <div class="flex-1 max-w-[100px] bg-gray-100 rounded-full h-2 overflow-hidden">
                                            <div class="{{ $progressBarColor }} h-2 rounded-full transition-all duration-500" style="width: {{ min($progression, 100) }}%"></div>
                                        </div>
                                        <span class="text-xs tabular-nums {{ $progressTextColor }} min-w-[60px]">
                                            <span class="font-semibold">{{ number_format($progression, 0) }}%</span>
                                            <span class="text-gray-400 text-[10px] ml-0.5">{{ $invLoc->nombre_biens_scannes }}/{{ $invLoc->nombre_biens_attendus }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-1.5">
                                        @if($allAgents->isNotEmpty())
                                            <div class="flex -space-x-1.5">
                                                @foreach($allAgents->take(3) as $ag)
                                                    <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-bold ring-2 ring-white" title="{{ $ag->users ?? 'Agent' }}">{{ mb_substr($ag->users ?? '?', 0, 1) }}</span>
                                                @endforeach
                                                @if($allAgents->count() > 3)
                                                    <span class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-[9px] font-bold ring-2 ring-white" title="{{ $allAgents->count() - 3 }} de plus">+{{ $allAgents->count() - 3 }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[11px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-100">Non assigné</span>
                                        @endif
                                        @if($isAdmin)
                                            <div class="relative">
                                                <button wire:click="toggleAgentDropdown({{ $invLoc->id }})" class="w-6 h-6 rounded-full border-2 border-dashed {{ $activeAgentDropdown === $invLoc->id ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-gray-300 text-gray-400 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50' }} flex items-center justify-center transition-all" title="Gérer les agents">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $activeAgentDropdown === $invLoc->id ? 'M6 18L18 6M6 6l12 12' : 'M12 6v6m0 0v6m0-6h6m-6 0H6' }}"/></svg>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <button wire:click="toggleSitesPanel({{ $invLoc->id }})" class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border transition-colors {{ $activeSitesPanel === $invLoc->id ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-200 hover:border-indigo-300 hover:text-indigo-600' }}" title="Voir les sites">
                                        Sites ({{ $invLoc->inventaireSites->count() }})
                                    </button>
                                    <a href="{{ route('localisations.show', $invLoc->localisation) }}" class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-indigo-600 font-medium opacity-0 group-hover:opacity-100 transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                            @if($activeSitesPanel === $invLoc->id)
                                <tr class="bg-gray-50/60" wire:key="sites-panel-{{ $invLoc->id }}">
                                    <td colspan="6" class="px-5 py-3">
                                        @include('livewire.inventaires.partials.sites-localisation')
                                    </td>
                                </tr>
                            @endif
                            @if($activeAgentDropdown === $invLoc->id)
                                <tr class="bg-indigo-50/40" wire:key="agent-panel-{{ $invLoc->id }}">
                                    <td colspan="6" class="px-5 py-3">
                                        <div class="flex items-start gap-4">
                                            <div class="flex-shrink-0">
                                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Assigner des agents</p>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5 flex-1">
                                                @foreach($this->agentsDisponibles as $agent)
                                                    @php $isAssigned = $allAgents->contains('idUser', $agent->idUser); @endphp
                                                    <button
                                                        wire:click="toggleAgentLocalisation({{ $invLoc->id }}, {{ $agent->idUser }})"
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border transition-all
                                                            {{ $isAssigned
                                                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm hover:bg-indigo-700'
                                                                : 'bg-white text-gray-700 border-gray-200 hover:border-indigo-300 hover:bg-indigo-50' }}"
                                                    >
                                                        <span class="w-5 h-5 rounded-full {{ $isAssigned ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500' }} flex items-center justify-center text-[9px] font-bold flex-shrink-0">{{ mb_substr($agent->users ?? '?', 0, 1) }}</span>
                                                        {{ $agent->users ?? 'Agent' }}
                                                        @if($isAssigned)
                                                            <svg class="w-3.5 h-3.5 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                            <button wire:click="closeAgentDropdown" class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-white transition-colors" title="Fermer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-1">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                        </div>
                                        <p class="text-sm text-gray-500 font-medium">Aucune localisation trouvée</p>
                                        @if($hasActiveFilters)
                                            <p class="text-xs text-gray-400">Essayez de modifier vos critères de recherche</p>
                                            <button wire:click="$set('searchLoc', ''); $set('filterStatutLoc', 'all'); $set('filterAgent', 'all')" class="mt-1 inline-flex items-center gap-1 px-3 py-1.5 text-xs text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 rounded-lg font-medium transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                Réinitialiser les filtres
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Vue mobile (cards) --}}
            <div class="md:hidden max-h-[36rem] overflow-y-auto">
                @forelse($allInvLocs as $invLoc)
                    @php
                        $progression = $invLoc->nombre_biens_attendus > 0 ? round(($invLoc->nombre_biens_scannes / $invLoc->nombre_biens_attendus) * 100, 1) : 0;
                        $allAgents = $invLoc->agents->isNotEmpty() ? $invLoc->agents : ($invLoc->agent ? collect([$invLoc->agent]) : collect());
                        $cardBorder = match($invLoc->statut) {
                            'termine' => 'border-l-green-500 bg-green-50/20',
                            'en_cours' => 'border-l-blue-500 bg-blue-50/20',
                            default => 'border-l-gray-300',
                        };
                        $progressBarColor = $progression >= 100 ? 'bg-green-500' : ($progression >= 50 ? 'bg-blue-500' : ($progression > 0 ? 'bg-indigo-400' : 'bg-gray-200'));
                    @endphp
                    <div class="border-b border-gray-100 border-l-[3px] {{ $cardBorder }} hover:bg-gray-50/50 transition-colors">
                        <a href="{{ route('localisations.show', $invLoc->localisation) }}" class="block px-4 pt-3.5 pb-2">
                            <div class="flex items-start justify-between gap-3 mb-2.5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $invLoc->localisation->localisation ?? 'N/A' }}</p>
                                    <p class="text-[11px] font-mono text-gray-400 mt-0.5">{{ $invLoc->localisation->code ?? '' }}</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium flex-shrink-0 {{ $statutsLoc[$invLoc->statut]['color'] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $statutsLoc[$invLoc->statut]['label'] ?? $invLoc->statut }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mb-1">
                                <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div class="{{ $progressBarColor }} h-2 rounded-full transition-all" style="width: {{ min($progression, 100) }}%"></div>
                                </div>
                                <div class="flex items-baseline gap-1 flex-shrink-0">
                                    <span class="text-xs tabular-nums font-semibold {{ $progression >= 100 ? 'text-green-600' : 'text-gray-700' }}">{{ number_format($progression, 0) }}%</span>
                                    <span class="text-[10px] tabular-nums text-gray-400">{{ $invLoc->nombre_biens_scannes }}/{{ $invLoc->nombre_biens_attendus }}</span>
                                </div>
                            </div>
                        </a>
                        <div class="flex items-center gap-1.5 px-4 pb-3">
                            @if($allAgents->isNotEmpty())
                                <div class="flex -space-x-1">
                                    @foreach($allAgents->take(3) as $ag)
                                        <span class="w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[8px] font-bold ring-2 ring-white" title="{{ $ag->users ?? 'Agent' }}">{{ mb_substr($ag->users ?? '?', 0, 1) }}</span>
                                    @endforeach
                                </div>
                                <span class="text-[10px] text-gray-500">
                                    {{ $allAgents->pluck('users')->take(2)->join(', ') }}{{ $allAgents->count() > 2 ? ' +'.($allAgents->count() - 2) : '' }}
                                </span>
                            @else
                                <span class="text-[10px] text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100">Non assigné</span>
                            @endif
                            <button wire:click="toggleSitesPanel({{ $invLoc->id }})" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium border transition-colors {{ $activeSitesPanel === $invLoc->id ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-200' }}">
                                Sites ({{ $invLoc->inventaireSites->count() }})
                            </button>
                            @if($isAdmin)
                                <button wire:click="toggleAgentDropdown({{ $invLoc->id }})" class="w-6 h-6 rounded-full border-2 border-dashed {{ $activeAgentDropdown === $invLoc->id ? 'border-indigo-500 text-indigo-600 bg-indigo-50' : 'border-gray-300 text-gray-400 hover:border-indigo-400 hover:text-indigo-600' }} flex items-center justify-center transition-all" title="Gérer les agents">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $activeAgentDropdown === $invLoc->id ? 'M6 18L18 6M6 6l12 12' : 'M12 6v6m0 0v6m0-6h6m-6 0H6' }}"/></svg>
                                </button>
                            @endif
                        </div>
                        @if($activeSitesPanel === $invLoc->id)
                            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/60" wire:key="mobile-sites-panel-{{ $invLoc->id }}">
                                @include('livewire.inventaires.partials.sites-localisation')
                            </div>
                        @endif
                        @if($activeAgentDropdown === $invLoc->id)
                            <div class="px-4 pb-3 border-t border-indigo-100 bg-indigo-50/30" wire:key="mobile-agent-panel-{{ $invLoc->id }}">
                                <div class="flex items-center justify-between pt-2.5 mb-2">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Assigner des agents</p>
                                    <button wire:click="closeAgentDropdown" class="p-1 rounded text-gray-400 hover:text-gray-600 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($this->agentsDisponibles as $agent)
                                        @php $isAssigned = $allAgents->contains('idUser', $agent->idUser); @endphp
                                        <button
                                            wire:click="toggleAgentLocalisation({{ $invLoc->id }}, {{ $agent->idUser }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium border transition-all
                                                {{ $isAssigned
                                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                                                    : 'bg-white text-gray-700 border-gray-200 hover:border-indigo-300' }}"
                                        >
                                            <span class="w-4 h-4 rounded-full {{ $isAssigned ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500' }} flex items-center justify-center text-[8px] font-bold flex-shrink-0">{{ mb_substr($agent->users ?? '?', 0, 1) }}</span>
                                            {{ $agent->users ?? 'Agent' }}
                                            @if($isAssigned)
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mb-1">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </div>
                            <p class="text-sm text-gray-500">Aucune localisation trouvée</p>
                            @if($hasActiveFilters)
                                <button wire:click="$set('searchLoc', ''); $set('filterStatutLoc', 'all'); $set('filterAgent', 'all')" class="mt-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">Réinitialiser les filtres</button>
                            @endif
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Footer avec compteur --}}
        @if($allInvLocs->isNotEmpty())
            <div class="px-5 py-2.5 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                <p class="text-[11px] text-gray-400">
                    Affichage de <span class="font-semibold text-gray-600">{{ $allInvLocs->count() }}</span>
                    @if($hasActiveFilters) sur <span class="font-semibold text-gray-600">{{ $totalLocs }}</span> @endif
                    localisation{{ $allInvLocs->count() > 1 ? 's' : '' }}
                </p>
                @if($allInvLocs->count() > 10)
                    <p class="text-[10px] text-gray-400">Faites défiler pour voir plus</p>
                @endif
            </div>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- ACTIVITE RECENTE                            --}}
    {{-- ========================================== --}}
    @if($this->derniersScans->count() > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Derniers scans</h3>
                <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span>
                    Temps réel
                </span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                @foreach($this->derniersScans as $scan)
                    @php
                        $scanColors = ['present' => 'border-green-200 bg-green-50', 'deplace' => 'border-amber-200 bg-amber-50', 'absent' => 'border-red-200 bg-red-50', 'deteriore' => 'border-orange-200 bg-orange-50'];
                        $dotColors = ['present' => 'bg-green-500', 'deplace' => 'bg-amber-500', 'absent' => 'bg-red-500', 'deteriore' => 'bg-orange-500'];
                    @endphp
                    <div class="rounded-lg border {{ $scanColors[$scan->statut_scan] ?? 'border-gray-200 bg-gray-50' }} p-3">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-2 h-2 rounded-full {{ $dotColors[$scan->statut_scan] ?? 'bg-gray-400' }} flex-shrink-0"></span>
                            <span class="text-xs font-mono font-semibold text-gray-700 truncate">{{ $scan->code_inventaire }}</span>
                        </div>
                        <p class="text-xs text-gray-500 truncate mb-1" title="{{ $scan->designation }}">{{ $scan->designation }}</p>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <span>{{ $scan->agent?->users ?? 'Système' }}</span>
                            <span>{{ $scan->date_scan?->diffForHumans() }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Confirmation intégrée des changements de statut --}}
    <div
        x-show="confirmation"
        x-cloak
        x-trap.inert.noscroll="confirmation"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="inventory-confirmation-title"
        aria-describedby="inventory-confirmation-description">
        <div
            x-show="confirmation"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm"
            @click="closeConfirmation()"
            aria-hidden="true"></div>

        <div
            x-show="confirmation"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-3 scale-95"
            class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-white/60 bg-white shadow-2xl ring-1 ring-black/10"
            @click.stop>
            <div
                class="h-1.5 w-full"
                :class="{
                    'bg-indigo-600': confirmation?.tone === 'indigo',
                    'bg-amber-500': confirmation?.tone === 'amber',
                    'bg-red-600': confirmation?.tone === 'red'
                }"
                aria-hidden="true"></div>

            <div class="p-6 sm:p-8">
                <div class="flex items-start gap-4 sm:gap-5">
                    <div
                        class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl ring-1 ring-inset"
                        :class="{
                            'bg-indigo-50 text-indigo-700 ring-indigo-200': confirmation?.tone === 'indigo',
                            'bg-amber-50 text-amber-700 ring-amber-200': confirmation?.tone === 'amber',
                            'bg-red-50 text-red-700 ring-red-200': confirmation?.tone === 'red'
                        }">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 4.5h.008v.008H12V16.5z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Confirmation requise</p>
                        <h2 id="inventory-confirmation-title" class="mt-1 text-xl font-bold text-gray-900" x-text="confirmation?.title"></h2>
                        <p id="inventory-confirmation-description" class="mt-2 text-sm leading-6 text-gray-600" x-text="confirmation?.message"></p>
                    </div>
                    <button type="button" @click="closeConfirmation()" class="-mr-2 -mt-2 rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Fermer la confirmation">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Inventaire</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ $inventaire->annee }}</p>
                    </div>
                    <div class="border-l border-gray-200 pl-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Nouveau statut</p>
                        <span
                            class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="{
                                'bg-indigo-100 text-indigo-700': confirmation?.tone === 'indigo',
                                'bg-amber-100 text-amber-700': confirmation?.tone === 'amber',
                                'bg-red-100 text-red-700': confirmation?.tone === 'red'
                            }"
                            x-text="confirmation?.targetStatus"></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 bg-gray-50/80 px-6 py-4 sm:flex-row sm:justify-end sm:px-8">
                <button type="button" @click="closeConfirmation()" class="inline-flex min-w-28 justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Annuler
                </button>
                <button
                    type="button"
                    x-ref="confirmationButton"
                    @click="const action = confirmation.action; confirmation = null; $wire[action]()"
                    class="inline-flex min-w-28 justify-center rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                    :class="{
                        'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500': confirmation?.tone === 'indigo',
                        'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500': confirmation?.tone === 'amber',
                        'bg-red-600 hover:bg-red-700 focus:ring-red-500': confirmation?.tone === 'red'
                    }"
                    x-text="confirmation?.label">
                </button>
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- CHART.JS                                    --}}
    {{-- ========================================== --}}
    @vite('resources/js/chart.js')
    <script>
        (function() {
            let chartTemp = null;

            function destroyCharts() {
                if (chartTemp) { chartTemp.destroy(); chartTemp = null; }
            }

            function initCharts() {
                destroyCharts();
                const ctxTemp = document.getElementById('chart-progression-temporelle');
                if (!ctxTemp || !ctxTemp.offsetParent) return;

                const dataElement = document.getElementById('inventaire-scans-graph-data');
                const objectifElement = document.getElementById('inventaire-scans-objectif');
                let scansParJour = [];
                try { scansParJour = JSON.parse(dataElement?.textContent || '[]'); } catch (_) {}
                const objectif = Number(objectifElement?.dataset.value || 0);

                const dates = scansParJour.map(point => new Date(point.date + 'T00:00:00')
                    .toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }));
                const quotidien = scansParJour.map(point => Number(point.total));
                const cumulatif = [];
                let cumul = 0;
                quotidien.forEach(qty => { cumul += qty; cumulatif.push(cumul); });

                chartTemp = new Chart(ctxTemp, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Cumul des scans',
                            data: cumulatif,
                            borderColor: '#383f7b',
                            backgroundColor: 'rgba(79, 70, 229, 0.08)',
                            borderWidth: 2.5, tension: 0.3, fill: true,
                            pointRadius: dates.length > 20 ? 0 : 3,
                            pointHoverRadius: 5, pointBackgroundColor: '#383f7b', pointBorderColor: '#fff', pointBorderWidth: 2,
                        }, {
                            label: 'Objectif (' + objectif.toLocaleString('fr-FR') + ')',
                            data: new Array(dates.length).fill(objectif),
                            borderColor: '#d1d5db', borderWidth: 2, borderDash: [8, 4], fill: false, pointRadius: 0,
                        }, {
                            label: 'Scans quotidiens',
                            data: quotidien,
                            borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            borderWidth: 1.5, tension: 0.3, fill: false,
                            pointRadius: dates.length > 20 ? 0 : 2, pointHoverRadius: 4, yAxisID: 'y1',
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        animation: { duration: 500 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', align: 'end', labels: { padding: 20, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)', padding: 12,
                                callbacks: {
                                    label: (ctx) => {
                                        const v = ctx.parsed.y || 0;
                                        if (ctx.datasetIndex === 0) return ctx.dataset.label + ': ' + v.toLocaleString('fr-FR') + ' (' + (objectif > 0 ? ((v / objectif) * 100).toFixed(1) : 0) + '%)';
                                        if (ctx.datasetIndex === 1) return ctx.dataset.label;
                                        return ctx.dataset.label + ': ' + v;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#9ca3af', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                            y: { beginAtZero: true, max: cumulatif.length > 0 ? Math.max(objectif, Math.max(...cumulatif)) * 1.1 : objectif * 1.1, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, color: '#9ca3af', callback: v => v.toLocaleString('fr-FR') } },
                            y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { font: { size: 11 }, color: '#10b981' } }
                        }
                    }
                });
            }

            window.addEventListener('init-chart', initCharts);
            document.addEventListener('DOMContentLoaded', initCharts);
            document.addEventListener('livewire:navigated', initCharts);
            if (typeof Livewire !== 'undefined') {
                Livewire.on('statistiques-updated', () => setTimeout(initCharts, 200));
            } else {
                document.addEventListener('livewire:init', () => {
                    Livewire.on('statistiques-updated', () => setTimeout(initCharts, 200));
                });
            }
        })();
    </script>

    {{-- Messages flash --}}
    <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-3 items-end">
        @if(session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="flex items-center gap-3 bg-green-600 text-white px-5 py-3 rounded-lg shadow-lg max-w-md">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button @click="show = false" class="ml-2 text-green-200 hover:text-white"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
            </div>
        @endif
        @if(session()->has('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition class="flex items-center gap-3 bg-red-600 text-white px-5 py-3 rounded-lg shadow-lg max-w-md">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
                <button @click="show = false" class="ml-2 text-red-200 hover:text-white"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
            </div>
        @endif
    </div>
</div>
