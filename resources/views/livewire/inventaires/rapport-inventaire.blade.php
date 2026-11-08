<div x-data="{ activeTab: @entangle('activeTab'), sousOnglet: 'presents' }" class="space-y-6">
    @php
        $statutsInventaire = [
            'termine' => ['label' => 'Terminé', 'color' => 'bg-orange-100 text-orange-800'],
            'cloture' => ['label' => 'Clôturé', 'color' => 'bg-green-100 text-green-800'],
        ];
        $statutsScan = [
            'present' => ['label' => 'Présent', 'color' => 'bg-green-100 text-green-800'],
            'deplace' => ['label' => 'Déplacé', 'color' => 'bg-yellow-100 text-yellow-800'],
            'absent' => ['label' => 'Absent', 'color' => 'bg-red-100 text-red-800'],
            'deteriore' => ['label' => 'Détérioré', 'color' => 'bg-orange-100 text-orange-800'],
        ];
        $etatsConstate = [
            'bon_etat' => ['label' => 'Bon Etat', 'color' => 'bg-blue-100 text-blue-800'],
            'neuf' => ['label' => 'Neuf', 'color' => 'bg-green-100 text-green-800'],
            'defectueux' => ['label' => 'Defectueux', 'color' => 'bg-amber-100 text-amber-800'],
        ];
        $conformiteInterpretation = function($taux) {
            if ($taux >= 90) return ['label' => 'Conforme', 'color' => 'text-green-600', 'bg' => 'bg-green-50'];
            if ($taux >= 70) return ['label' => 'À améliorer', 'color' => 'text-orange-600', 'bg' => 'bg-orange-50'];
            return ['label' => 'Non conforme', 'color' => 'text-red-600', 'bg' => 'bg-red-50'];
        };
        $interpretation = $conformiteInterpretation($this->statistiques['taux_conformite']);
        $recentExports = $this->exports;
    @endphp

    {{-- Header simplifié --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-2xl font-bold text-gray-900">
                        Rapport Inventaire {{ $inventaire->annee }}
                    </h1>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $statutsInventaire[$inventaire->statut]['color'] }}">
                        {{ $statutsInventaire[$inventaire->statut]['label'] }}
                    </span>
                </div>
                <p class="text-sm text-gray-600">
                    Du {{ ($inventaire->started_at ?? $inventaire->date_debut)->format('d/m/Y') }}
                    @if($inventaire->finished_at || $inventaire->date_fin)
                        au {{ ($inventaire->finished_at ?? $inventaire->date_fin)->format('d/m/Y') }}
                    @endif
                    • {{ $this->statistiques['duree_jours'] }} jour(s)
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('inventaires.export-pdf', $inventaire) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        PDF
                    </button>
                </form>
                <form method="POST" action="{{ route('inventaires.export-excel', $inventaire) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0118 9.414V19a2 2 0 01-2 2z" />
                        </svg>
                        Excel
                    </button>
                </form>
                <button 
                    onclick="window.print()"
                    class="inline-flex items-center px-3 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimer
                </button>
            </div>
        </div>
    </div>

    @if($recentExports->isNotEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4"
             @if($recentExports->contains(fn ($export) => in_array($export->status, ['pending', 'processing'], true))) wire:poll.5s @endif>
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Exports récents</h2>
            <div class="space-y-2">
                @foreach($recentExports as $export)
                    <div class="flex items-center justify-between gap-4 text-sm">
                        <span>
                            {{ strtoupper($export->format) }} · {{ $export->created_at->format('d/m/Y H:i') }}
                            @if($export->automatic)
                                <span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Rapport final</span>
                            @endif
                        </span>
                        @if($export->status === 'ready')
                            <a href="{{ route('inventaires.exports.download', $export) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Télécharger</a>
                        @elseif($export->status === 'failed')
                            <span class="text-red-600">Échec de génération</span>
                        @elseif($export->status === 'processing')
                            <span class="text-blue-600">Génération en cours…</span>
                        @else
                            <span class="text-gray-500">En attente…</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Section statistiques pertinentes --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        {{-- Taux de conformité --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 {{ $interpretation['bg'] }}">
            <h3 class="text-xs font-medium text-gray-600 mb-1">Taux de conformité</h3>
            <p class="text-2xl font-bold {{ $interpretation['color'] }}">{{ round($this->statistiques['taux_conformite'], 1) }}%</p>
            <p class="text-xs text-gray-500 mt-1">{{ $interpretation['label'] }}</p>
        </div>

        {{-- Taux de rapprochement --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <h3 class="text-xs font-medium text-gray-600 mb-1">Rapprochement</h3>
            <p class="text-2xl font-bold text-blue-600">{{ $this->statistiques['taux_rapprochement'] ?? 0 }}%</p>
            <p class="text-xs text-gray-500 mt-1">{{ $this->statistiques['total_biens_traites'] ?? 0 }}/{{ $this->statistiques['total_biens_attendus'] }} traités</p>
        </div>

        {{-- Taux de couverture --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <h3 class="text-xs font-medium text-gray-600 mb-1">Taux de couverture</h3>
            <p class="text-2xl font-bold text-indigo-600">{{ $this->statistiques['taux_couverture'] ?? 0 }}%</p>
            <p class="text-xs text-gray-500 mt-1">{{ $this->statistiques['total_biens_scannes'] }}/{{ $this->statistiques['total_biens_attendus'] }} scannés</p>
        </div>

        {{-- Biens non scannés --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 {{ ($this->statistiques['biens_non_scannes'] ?? 0) > 0 ? 'border-amber-200 bg-amber-50' : '' }}">
            <h3 class="text-xs font-medium text-gray-600 mb-1">Non scannés</h3>
            <p class="text-2xl font-bold {{ ($this->statistiques['biens_non_scannes'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-600' }}">
                {{ $this->statistiques['biens_non_scannes'] ?? 0 }}
            </p>
            <p class="text-xs text-gray-500 mt-1">non encore traités</p>
        </div>

        {{-- Agents --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
            <h3 class="text-xs font-medium text-gray-600 mb-1">Agents</h3>
            <p class="text-2xl font-bold text-gray-900">{{ $this->statistiques['nombre_agents'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 mt-1">ayant participé</p>
        </div>
    </div>

    {{-- Répartition par état physique (Neuf, Bon état, Défectueuse) --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Répartition par état physique</h3>
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-xs text-green-700 font-medium mb-1">Neuf</p>
                <p class="text-2xl font-bold text-green-700">{{ $this->statistiques['biens_neufs'] ?? 0 }}</p>
                <p class="text-xs text-green-600 mt-1">{{ $this->statistiques['total_biens_scannes'] > 0 ? round((($this->statistiques['biens_neufs'] ?? 0) / $this->statistiques['total_biens_scannes']) * 100, 1) : 0 }}%</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <p class="text-xs text-blue-700 font-medium mb-1">Bon état</p>
                <p class="text-2xl font-bold text-blue-700">{{ $this->statistiques['biens_bon_etat'] ?? 0 }}</p>
                <p class="text-xs text-blue-600 mt-1">{{ $this->statistiques['total_biens_scannes'] > 0 ? round((($this->statistiques['biens_bon_etat'] ?? 0) / $this->statistiques['total_biens_scannes']) * 100, 1) : 0 }}%</p>
            </div>
            <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                <p class="text-xs text-amber-700 font-medium mb-1">Défectueuse</p>
                <p class="text-2xl font-bold text-amber-700">{{ $this->statistiques['biens_defectueux'] ?? 0 }}</p>
                <p class="text-xs text-amber-600 mt-1">{{ $this->statistiques['total_biens_scannes'] > 0 ? round((($this->statistiques['biens_defectueux'] ?? 0) / $this->statistiques['total_biens_scannes']) * 100, 1) : 0 }}%</p>
            </div>
        </div>
    </div>

    {{-- Navigation onglets simplifiée --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-4 px-6 overflow-x-auto" aria-label="Tabs">
                <button 
                    @click="activeTab = 'resume'; $wire.setActiveTab('resume')"
                    :class="activeTab === 'resume' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Résumé
                </button>
                <button 
                    @click="activeTab = 'sites'; $wire.setActiveTab('sites')"
                    :class="activeTab === 'sites' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Par site
                </button>
                <button 
                    @click="activeTab = 'biens'; $wire.setActiveTab('biens')"
                    :class="activeTab === 'biens' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Immobilisations
                </button>
                <button 
                    @click="activeTab = 'anomalies'; $wire.setActiveTab('anomalies')"
                    :class="activeTab === 'anomalies' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Anomalies
                </button>
            </nav>
        </div>

        {{-- Contenu des onglets --}}
        <div class="p-6">
            {{-- ONGLET Résumé --}}
            <div x-show="activeTab === 'resume'" x-transition class="space-y-6">
                {{-- Indicateurs clés --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-600 font-medium mb-1">Taux d'absence</p>
                        <p class="text-xl font-bold {{ ($this->statistiques['taux_absence'] ?? 0) > 10 ? 'text-red-600' : 'text-gray-900' }}">{{ $this->statistiques['taux_absence'] ?? 0 }}%</p>
                        <p class="text-xs text-gray-500">{{ $this->statistiques['biens_absents'] }} biens absents</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-600 font-medium mb-1">Taux d'anomalies</p>
                        <p class="text-xl font-bold {{ ($this->statistiques['taux_anomalies'] ?? 0) > 15 ? 'text-orange-600' : 'text-gray-900' }}">{{ $this->statistiques['taux_anomalies'] ?? 0 }}%</p>
                        <p class="text-xs text-gray-500">biens uniques avec anomalie</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-600 font-medium mb-1">Progression</p>
                        <p class="text-xl font-bold text-indigo-600">{{ $this->statistiques['progression_globale'] ?? 0 }}%</p>
                        <p class="text-xs text-gray-500">{{ $this->statistiques['localisations_terminees'] }}/{{ $this->statistiques['total_localisations'] }} loc. terminées</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <p class="text-xs text-gray-600 font-medium mb-1">Durée</p>
                        <p class="text-xl font-bold text-gray-900">{{ $this->statistiques['duree_jours'] ?? 0 }} jour(s)</p>
                        <p class="text-xs text-gray-500">inventaire</p>
                    </div>
                </div>

                {{-- Répartition par statut de scan --}}
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <p class="text-xs text-green-700 font-medium mb-1">Présents</p>
                        <p class="text-2xl font-bold text-green-700">{{ $this->statistiques['biens_presents'] }}</p>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                        <p class="text-xs text-yellow-700 font-medium mb-1">Déplacés</p>
                        <p class="text-2xl font-bold text-yellow-700">{{ $this->statistiques['biens_deplaces'] }}</p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                        <p class="text-xs text-red-700 font-medium mb-1">Absents</p>
                        <p class="text-2xl font-bold text-red-700">{{ $this->statistiques['biens_absents'] }}</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                        <p class="text-xs text-orange-700 font-medium mb-1">Détériorés</p>
                        <p class="text-2xl font-bold text-orange-700">{{ $this->statistiques['biens_deteriores'] }}</p>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                        <p class="text-xs text-amber-700 font-medium mb-1">Défectueux</p>
                        <p class="text-2xl font-bold text-amber-700">{{ $this->statistiques['biens_defectueux'] ?? 0 }}</p>
                    </div>
                </div>

                {{-- Contribution par agent --}}
                @if(count($this->statistiques['par_agent'] ?? []) > 0)
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Contribution par agent</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisations</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Biens scannés</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">% du total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($this->statistiques['par_agent'] as $agent)
                                    @php $pct = $this->statistiques['total_biens_scannes'] > 0 ? round(($agent['biens_scannes'] / $this->statistiques['total_biens_scannes']) * 100, 1) : 0; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $agent['agent_name'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $agent['localisations'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $agent['biens_scannes'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-indigo-600">{{ $pct }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Performance par site (tableau simplifié) --}}
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance par site</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scannés physiquement</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Présents</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conformité</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach(collect($this->statistiques['par_site'] ?? [])->sortByDesc('taux_conformite')->take(10) as $emp)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $emp['designation'] ?? $emp['code'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $emp['localisation'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $emp['biens_scannes'] }}/{{ $emp['biens_attendus'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-green-600 font-medium">{{ $emp['biens_presents'] ?? 0 }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-sm font-medium {{ ($emp['taux_conformite'] ?? 0) >= 90 ? 'text-green-600' : (($emp['taux_conformite'] ?? 0) >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ round($emp['taux_conformite'] ?? 0, 1) }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ONGLET Par site (résultats détaillés) --}}
            <div x-show="activeTab === 'sites'" x-transition style="display: none;" class="space-y-6">
                @if(count($this->detailParSite) > 0)
                <div class="mb-4">
                    <select 
                        wire:model.live="filterEmplacement"
                        class="block w-full md:w-64 px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="all">Tous les sites</option>
                        @foreach($this->detailParSite as $emp)
                            <option value="{{ $emp['site_id'] }}">{{ $emp['designation'] ?? $emp['code'] }} ({{ $emp['localisation'] ?? 'N/A' }})</option>
                        @endforeach
                    </select>
                </div>

                @foreach($this->detailParSite as $emp)
                    @if($filterEmplacement === 'all' || $filterEmplacement == $emp['site_id'])
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-6">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900">{{ $emp['designation'] ?? $emp['code'] }}</h3>
                                        <p class="text-sm text-gray-600">{{ $emp['localisation'] ?? 'N/A' }} — {{ $emp['total_trouves'] }}/{{ $emp['total_attendus'] }} trouvées ({{ $emp['taux_conformite'] }}%)</p>
                                    </div>
                                    <span class="text-sm font-medium {{ ($emp['taux_conformite'] ?? 0) >= 90 ? 'text-green-600' : (($emp['taux_conformite'] ?? 0) >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ round($emp['taux_conformite'] ?? 0, 1) }}%
                                    </span>
                                </div>
                            </div>
                            @if(count($emp['lignes'] ?? []) > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Attendu</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Trouvé</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Conformité</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">État</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($emp['lignes'] as $ligne)
                                        @php
                                            $statut = $ligne['statut_scan'] ?? '';
                                            $conformiteClass = $statut === 'present' ? 'bg-green-100 text-green-800' : ($statut === 'absent' ? 'bg-red-100 text-red-800' : ($statut === 'deplace' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'));
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">{{ $ligne['code'] ?? 'N/A' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ Str::limit($ligne['designation'] ?? 'N/A', 40) }}</td>
                                            <td class="px-4 py-2 text-center text-sm text-gray-900">{{ $ligne['attendu'] ?? 1 }}</td>
                                            <td class="px-4 py-2 text-center text-sm text-gray-900">{{ $ligne['trouve'] ?? 0 }}</td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $conformiteClass }}">{{ $ligne['conformite'] ?? '-' }}</span>
                                            </td>
                                            <td class="px-4 py-2 text-center text-sm {{ ($ligne['etat'] ?? '') === 'Defectueux' ? 'text-amber-600 font-medium' : 'text-gray-900' }}">{{ $ligne['etat'] ?? '-' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="px-4 py-6 text-sm text-gray-500 italic">Aucune immobilisation dans ce site.</p>
                            @endif
                        </div>
                    @endif
                @endforeach
                @else
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="text-sm text-amber-800">Aucune donnée par site disponible. Les inventaires peuvent utiliser le mode par localisation.</p>
                </div>
                @endif
            </div>

            {{-- ONGLET Immobilisations (regroupé) --}}
            <div x-show="activeTab === 'biens'" x-transition style="display: none;" class="space-y-6">
                {{-- Sous-onglets pour les biens --}}
                <div class="border-b border-gray-200 mb-4">
                    <nav class="-mb-px flex space-x-4">
                        <button 
                            @click="sousOnglet = 'presents'"
                            :class="sousOnglet === 'presents' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-1 border-b-2 font-medium text-sm">
                            Présents ({{ count($this->biensPresents) }})
                        </button>
                        <button 
                            @click="sousOnglet = 'deplaces'"
                            :class="sousOnglet === 'deplaces' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-1 border-b-2 font-medium text-sm">
                            Déplacés ({{ count($this->biensDeplaces) }})
                        </button>
                        <button 
                            @click="sousOnglet = 'absents'"
                            :class="sousOnglet === 'absents' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-1 border-b-2 font-medium text-sm">
                            Absents ({{ count($this->biensAbsents) }})
                        </button>
                        <button 
                            @click="sousOnglet = 'defectueux'"
                            :class="sousOnglet === 'defectueux' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-1 border-b-2 font-medium text-sm">
                            Défectueux ({{ count($this->biensDefectueux) }})
                        </button>
                    </nav>
                </div>

                {{-- Présents --}}
                <div x-show="sousOnglet === 'presents'" x-transition style="display: none;">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">État</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($this->biensPresents->take(50) as $scan)
                                    @php $etatKey = $scan->etat_constate ?? 'bon_etat'; $etatStyle = $etatsConstate[$etatKey] ?? $etatsConstate['bon_etat']; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $scan->code_inventaire }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($scan->designation, 50) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $etatStyle['color'] }}">{{ $scan->etat_constate_label }}</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $scan->localisation_code ?? ($scan->bien?->localisation?->code ?? 'N/A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($this->biensPresents) > 50)
                        <p class="text-sm text-gray-500 mt-4">Affichage des 50 premiers résultats sur {{ count($this->biensPresents) }}</p>
                    @endif
                </div>

                {{-- Déplacés --}}
                <div x-show="sousOnglet === 'deplaces'" x-transition style="display: none;">
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <p class="text-sm text-yellow-700">
                            <strong>Action suggérée :</strong> Mettre à jour les localisations permanentes des immobilisations déplacées.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation prévue</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation réelle</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($this->biensDeplaces->take(50) as $scan)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $scan->code_inventaire }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($scan->designation, 50) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-red-600 font-medium">{{ $scan->localisation_code ?? ($scan->bien?->localisation?->code ?? 'N/A') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-orange-600 font-medium">{{ $scan->localisationReelle?->code ?? $scan->localisationReelle?->localisation ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Absents --}}
                <div x-show="sousOnglet === 'absents'" x-transition style="display: none;">
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                        <p class="text-sm font-medium text-red-800">
                            {{ count($this->biensAbsents) }} immobilisation(s) absente(s)
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($this->biensAbsents->take(50) as $scan)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $scan->code_inventaire }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($scan->designation, 50) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $scan->localisation_code ?? ($scan->bien?->localisation?->code ?? 'N/A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Défectueux (signalés via PWA) --}}
                <div x-show="sousOnglet === 'defectueux'" x-transition style="display: none;">
                    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-4">
                        <p class="text-sm text-amber-700">
                            <strong>Immobilisations signalées défectueuses</strong> lors de l'inventaire (via PWA). 3 états : Neuf, Bon état, Défectueuse.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">État</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Photo</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($this->biensDefectueux->take(50) as $scan)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $scan->code_inventaire }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($scan->designation, 50) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Défectueuse</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $scan->localisationReelle?->code ?? $scan->localisationReelle?->localisation ?? ($scan->localisation_code ?? 'N/A') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            @if($scan->photo_path && $scan->photo_url)
                                                <div x-data="{ open: false }" class="inline" @keydown.escape.window="open = false">
                                                    <button @click="open = true" type="button" class="flex items-center gap-2 group">
                                                        <img src="{{ $scan->photo_url }}" alt="Photo {{ $scan->code_inventaire }}" class="w-12 h-12 object-contain rounded border border-gray-200 group-hover:border-indigo-400 transition cursor-pointer bg-gray-50" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%239ca3af%22 stroke-width=%222%22%3E%3Crect x=%223%22 y=%223%22 width=%2218%22 height=%2218%22 rx=%222%22/%3E%3Ccircle cx=%228.5%22 cy=%228.5%22 r=%221.5%22/%3E%3Cpath d=%22M21 15l-5-5L5 21%22/%3E%3C/svg%3E';">
                                                        <span class="text-indigo-600 hover:underline text-sm">Voir</span>
                                                    </button>
                                                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click.self="open = false" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4">
                                                        <div class="relative max-w-4xl max-h-[90vh] flex items-center justify-center">
                                                            <img src="{{ $scan->photo_url }}" alt="Photo {{ $scan->code_inventaire }}" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-xl bg-gray-900" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                                                            <div class="hidden min-h-[200px] min-w-[300px] flex items-center justify-center rounded-lg bg-gray-800 text-gray-400 p-8">
                                                                <span>Image non disponible</span>
                                                            </div>
                                                            <button @click="open = false" type="button" class="absolute -top-12 right-0 text-white hover:text-gray-300 p-2 rounded-full hover:bg-white/10 transition" aria-label="Fermer">
                                                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                            <p class="text-white text-sm mt-2 text-center">{{ $scan->code_inventaire }} - {{ Str::limit($scan->designation, 40) }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($this->biensDefectueux) > 50)
                        <p class="text-sm text-gray-500 mt-4">Affichage des 50 premiers résultats sur {{ count($this->biensDefectueux) }}</p>
                    @endif
                </div>
            </div>

            {{-- ONGLET Anomalies --}}
            <div x-show="activeTab === 'anomalies'" x-transition style="display: none;" class="space-y-6">
                @php $anomalies = $this->anomalies; @endphp

                @if(count($anomalies['localisations_non_demarrees']) > 0 || count($anomalies['taux_absence_eleve']) > 0 || count($anomalies['biens_defectueux'] ?? []) > 0)
                    <div class="space-y-4">
                        @if(count($anomalies['localisations_non_demarrees']) > 0)
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <h4 class="font-medium text-yellow-800 mb-2">Localisations non démarrées ({{ count($anomalies['localisations_non_demarrees']) }})</h4>
                                <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                                    @foreach($anomalies['localisations_non_demarrees'] as $anomalie)
                                        <li>{{ $anomalie['code'] }} - {{ $anomalie['designation'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(count($anomalies['taux_absence_eleve']) > 0)
                            <div class="bg-orange-50 border-l-4 border-orange-400 p-4">
                                <h4 class="font-medium text-orange-800 mb-2">Taux d'absence élevé ({{ count($anomalies['taux_absence_eleve']) }})</h4>
                                <ul class="list-disc list-inside text-sm text-orange-700 space-y-1">
                                    @foreach($anomalies['taux_absence_eleve'] as $anomalie)
                                        <li>{{ $anomalie['code'] }} - {{ $anomalie['taux_absence'] }}% absents ({{ $anomalie['biens_absents'] }} immobilisations)</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(count($anomalies['biens_defectueux'] ?? []) > 0)
                            <div class="bg-amber-50 border-l-4 border-amber-400 p-4">
                                <h4 class="font-medium text-amber-800 mb-2">Immobilisations signalées défectueuses ({{ count($anomalies['biens_defectueux']) }})</h4>
                                <ul class="list-disc list-inside text-sm text-amber-700 space-y-1">
                                    @foreach($anomalies['biens_defectueux'] as $anomalie)
                                        <li>{{ $anomalie['code'] }} - {{ $anomalie['designation'] }} ({{ $anomalie['localisation'] }})</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 mx-auto text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-lg font-medium text-gray-900">Aucune anomalie détectée</p>
                        <p class="text-sm text-gray-500 mt-2">Tout semble être en ordre !</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Footer simplifié --}}
    <div class="mt-6 pt-6 border-t border-gray-200 text-sm text-gray-500 text-center">
        <p>Rapport généré le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->users }}</p>
    </div>

    {{-- Messages flash --}}
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
