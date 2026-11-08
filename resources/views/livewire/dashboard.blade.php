<div>
    <!-- Titre du dashboard -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Tableau de bord</h1>
        <p class="text-gray-500 mt-1">Vue d'ensemble de votre gestion d'inventaire</p>
    </div>

    @if($totalBiens === 0 && $totalLocalisations === 0)
        <!-- Message d'accueil pour nouvelle installation -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Bienvenue dans votre système de gestion d'inventaire !</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Pour commencer, vous pouvez :</p>
                        <ul class="list-disc list-inside mt-2 space-y-1">
                            <li>Créer des localisations (bureaux, ateliers, etc.)</li>
                            <li>Ajouter des immobilisations à inventorier</li>
                            <li>Démarrer votre premier inventaire</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Cartes statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Card 1 - Total Immobilisations -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Immobilisations</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($totalBiens, 0, ',', ' ') }}</p>
                    <p class="text-sm text-green-600 mt-2 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        +{{ $biensCetteAnnee }} cette année
                    </p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600"><x-icon name="cube" class="w-6 h-6" /></div>
            </div>
            <a href="{{ route('biens.index') }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-800 mt-4 inline-block">
                Voir toutes les immobilisations →
            </a>
        </div>

        <!-- Card 2 - Localisations -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Localisations</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($totalLocalisations, 0, ',', ' ') }}</p>
                    <p class="text-sm text-gray-500 mt-2">
                        {{ $nombreEmplacements }} {{ $nombreEmplacements === 1 ? 'site' : 'sites' }}
                    </p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-600"><x-icon name="location-marker" class="w-6 h-6" /></div>
            </div>
            <a href="{{ route('localisations.index') }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-800 mt-4 inline-block">
                Gérer les localisations →
            </a>
        </div>

        <!-- Card 3 - Inventaire -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-600">Inventaire suivi</p>
                    @if($inventaireEnCours)
                        <p class="text-lg font-bold text-gray-900 mt-2">Inventaire {{ $inventaireEnCours->annee }}</p>
                        <div class="mt-2 mb-2">
                            @if($inventaireEnCours->statut === 'en_preparation')
                                <span class="text-xs px-2 py-1 bg-gray-100 text-gray-800 rounded-full">En préparation</span>
                            @elseif($inventaireEnCours->statut === 'en_cours')
                                <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">En cours</span>
                            @elseif($inventaireEnCours->statut === 'termine')
                                <span class="text-xs px-2 py-1 bg-orange-100 text-orange-800 rounded-full">Terminé</span>
                            @elseif($inventaireEnCours->statut === 'cloture')
                                <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">Clôturé</span>
                            @endif
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                <span>Progression</span>
                                <span>{{ round($statistiquesInventaire['progression'] ?? 0, 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-300
                                    {{ ($statistiquesInventaire['progression'] ?? 0) >= 100 ? 'bg-green-600' : 
                                       (($statistiquesInventaire['progression'] ?? 0) >= 50 ? 'bg-blue-600' : 
                                       (($statistiquesInventaire['progression'] ?? 0) > 0 ? 'bg-yellow-500' : 'bg-gray-400')) }}" 
                                     style="width: {{ $statistiquesInventaire['progression'] ?? 0 }}%"></div>
                            </div>
                        </div>
                    @else
                        <p class="text-lg font-bold text-gray-400 mt-2">Aucun inventaire</p>
                    @endif
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-purple-50 text-purple-600"><x-icon name="clipboard-list" class="w-6 h-6" /></div>
            </div>
            @if($inventaireEnCours)
                <a href="{{ route('inventaires.show', $inventaireEnCours->id) }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-800 mt-4 inline-block">
                    Voir l'inventaire →
                </a>
            @endif
        </div>

        <!-- Card 4 - Biens en mauvais état ou hors service -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-600">À réformer</p>
                    <p class="mt-2 break-words text-2xl font-bold leading-tight text-gray-900 xl:text-3xl">
                        {{ number_format($biensAReformer, 0, ',', ' ') }}
                    </p>
                    <p class="text-sm text-gray-500 mt-2">Biens mauvais ou hors service</p>
                </div>
                <div class="ml-3 flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    @if($inventaireEnCours)
        <!-- Section Inventaire en cours -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Inventaire {{ $inventaireEnCours->annee }}
                        @if($inventaireEnCours->statut === 'en_preparation')
                            <span class="text-sm font-normal text-gray-500">(en préparation)</span>
                        @elseif($inventaireEnCours->statut === 'en_cours')
                            <span class="text-sm font-normal text-blue-600">(en cours)</span>
                        @elseif($inventaireEnCours->statut === 'termine')
                            <span class="text-sm font-normal text-orange-600">(terminé)</span>
                        @elseif($inventaireEnCours->statut === 'cloture')
                            <span class="text-sm font-normal text-green-600">(clôturé)</span>
                        @endif
                    </h3>
                    <div class="text-sm text-gray-500 mt-1 space-y-1">
                        @if($inventaireEnCours->date_debut)
                            <p>Démarré le {{ \Carbon\Carbon::parse($inventaireEnCours->date_debut)->format('d/m/Y') }}</p>
                        @endif
                        @if($inventaireEnCours->date_fin)
                            <p>Terminé le {{ \Carbon\Carbon::parse($inventaireEnCours->date_fin)->format('d/m/Y') }}</p>
                        @endif
                    </div>
                </div>
                <a href="{{ route('inventaires.show', $inventaireEnCours->id) }}" wire:navigate
                   class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    Voir détails complets →
                </a>
            </div>

            <!-- Résumé statistiques -->
            @if(!empty($statistiquesInventaire))
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 pb-6 border-b border-gray-200">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">Localisations</p>
                                <p class="text-2xl font-bold text-blue-900 mt-1">
                                    {{ $statistiquesInventaire['localisations_terminees'] ?? 0 }}<span class="text-lg text-blue-600">/{{ $statistiquesInventaire['total_localisations'] ?? 0 }}</span>
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/70 text-blue-600"><x-icon name="location-marker" class="w-5 h-5" /></div>
                        </div>
                    </div>

                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Total scans</p>
                                <p class="text-2xl font-bold text-green-900 mt-1">
                                    {{ number_format($statistiquesInventaire['total_scans'] ?? 0, 0, ',', ' ') }}
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/70 text-green-600"><x-icon name="check-circle" class="w-5 h-5" /></div>
                        </div>
                    </div>

                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-purple-600 uppercase tracking-wider">Progression</p>
                                <p class="text-2xl font-bold text-purple-900 mt-1">
                                    {{ round($statistiquesInventaire['progression'] ?? 0, 1) }}%
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/70 text-purple-600"><x-icon name="chart-bar" class="w-5 h-5" /></div>
                        </div>
                    </div>

                    <div class="bg-indigo-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Conformité</p>
                                <p class="text-2xl font-bold text-indigo-900 mt-1">
                                    {{ round($statistiquesInventaire['taux_conformite'] ?? 0, 1) }}%
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/70 text-amber-600"><x-icon name="chart-pie" class="w-5 h-5" /></div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tableau récapitulatif -->
            <div class="overflow-x-auto mb-8">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Localisation</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Attendus</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Scannés</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progression</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent assigné</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($localisationsInventaire as $loc)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ $loc['localisation'] }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-900 font-semibold">
                                    {{ number_format($loc['biens_attendus'], 0, ',', ' ') }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm font-semibold
                                    {{ $loc['biens_scannes'] > 0 ? 'text-blue-600' : 'text-gray-400' }}">
                                    {{ number_format($loc['biens_scannes'], 0, ',', ' ') }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-24 bg-gray-200 rounded-full h-2.5 mr-3">
                                            <div class="h-2.5 rounded-full transition-all duration-300
                                                {{ $loc['progression'] >= 100 ? 'bg-green-600' : 
                                                   ($loc['progression'] >= 50 ? 'bg-blue-600' : 
                                                   ($loc['progression'] > 0 ? 'bg-yellow-500' : 'bg-gray-400')) }}" 
                                                style="width: {{ min($loc['progression'], 100) }}%"></div>
                                        </div>
                                        <span class="text-sm font-medium
                                            {{ $loc['progression'] >= 100 ? 'text-green-600' : 
                                               ($loc['progression'] > 0 ? 'text-gray-700' : 'text-gray-400') }}">
                                            {{ round($loc['progression'], 1) }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                        {{ $loc['statut'] === 'termine' ? 'bg-green-100 text-green-800' : 
                                           ($loc['statut'] === 'en_cours' ? 'bg-blue-100 text-blue-800' : 
                                           ($loc['statut'] === 'en_attente' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')) }}">
                                        @if($loc['statut'] === 'en_attente')
                                            <x-icon name="clock" class="inline-block w-3.5 h-3.5 mr-0.5 align-[-2px]" /> En attente
                                        @elseif($loc['statut'] === 'en_cours')
                                            <x-icon name="lightning-bolt" class="inline-block w-3.5 h-3.5 mr-0.5 align-[-2px]" /> En cours
                                        @elseif($loc['statut'] === 'termine')
                                            <x-icon name="check-circle" class="inline-block w-3.5 h-3.5 mr-0.5 align-[-2px]" /> Terminé
                                        @else
                                            {{ ucfirst(str_replace('_', ' ', $loc['statut'])) }}
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    @if($loc['agent'] === 'Non assigné')
                                        <span class="text-gray-400 italic">{{ $loc['agent'] }}</span>
                                    @else
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ $loc['agent'] }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-500">Aucune localisation assignée</p>
                                        <p class="text-xs text-gray-400 mt-1">Assignez des localisations à inventorier pour commencer</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Graphiques -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" 
                 @if($inventaireEnCours && in_array($inventaireEnCours->statut, ['en_preparation', 'en_cours'])) wire:poll.10s="refresh" @endif>
                <script type="application/json" id="dashboard-statuts-data">@json($repartitionStatuts)</script>
                <!-- Graphique 1 - Pie chart statuts -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold text-gray-800">Répartition des statuts</h4>
                        <span class="text-xs text-gray-500">{{ array_sum($repartitionStatuts) }} bien(s) traité(s)</span>
                    </div>
                    @if(array_sum($repartitionStatuts) > 0)
                        <div class="relative h-56 max-h-56">
                            <canvas id="statutsChart"></canvas>
                        </div>
                    @else
                        <div class="flex h-40 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-4 text-center">
                            <svg class="mb-2 h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-5m3 5V7m5 13H4a1 1 0 01-1-1V5a1 1 0 011-1h16a1 1 0 011 1v14a1 1 0 01-1 1z" />
                            </svg>
                            <p class="text-sm font-medium text-gray-600">Aucun bien traité</p>
                            <p class="mt-1 text-xs text-gray-400">La répartition apparaîtra après les premiers scans.</p>
                        </div>
                    @endif
                </div>

                <!-- Progression compacte par localisation -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold text-gray-800">Progression par localisation</h4>
                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-500 ring-1 ring-gray-200">
                            {{ count($progressionParService) }} localisation(s)
                        </span>
                    </div>

                    <div class="max-h-56 space-y-3 overflow-y-auto pr-1">
                        @forelse($progressionParService as $progression)
                            @php($pourcentage = min(max((float) ($progression['progression'] ?? 0), 0), 100))
                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                                <div class="mb-2 flex items-center justify-between gap-4">
                                    <span class="truncate text-sm font-medium text-gray-700" title="{{ $progression['service'] }}">
                                        {{ $progression['service'] }}
                                    </span>
                                    <span class="flex-shrink-0 text-sm font-semibold {{ $pourcentage >= 100 ? 'text-green-600' : ($pourcentage > 0 ? 'text-indigo-600' : 'text-gray-400') }}">
                                        {{ round($pourcentage, 1) }}%
                                    </span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $pourcentage }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progression de {{ $progression['service'] }}">
                                    <div
                                        class="h-full rounded-full transition-all duration-500 {{ $pourcentage >= 100 ? 'bg-green-500' : ($pourcentage > 0 ? 'bg-indigo-500' : 'bg-gray-300') }}"
                                        style="width: {{ $pourcentage }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="flex h-40 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-4 text-center">
                                <p class="text-sm font-medium text-gray-600">Aucune localisation assignée</p>
                                <p class="mt-1 text-xs text-gray-400">La progression sera affichée après l’affectation.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Section Sites inventoriés -->
    @if($inventaireEnCours && !empty($emplacementsInventories))
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <x-icon name="office-building" class="w-6 h-6 mr-2 text-indigo-600" />
                        Sites inventoriés
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ count($emplacementsInventories) }} site(s) du référentiel suivi
                    </p>
                </div>
                <a href="{{ route('sites.index') }}" wire:navigate
                   class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    Voir tous les sites →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Site</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Localisation</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsable</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Biens scannés</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total biens</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progression</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($emplacementsInventories as $site)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $site['nom'] }}
                                    </div>
                                    @if(!empty($site['code']))
                                        <div class="text-xs text-gray-500">{{ $site['code'] }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $site['localisation'] }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $site['responsable'] }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-3 py-1 text-sm font-semibold bg-blue-100 text-blue-800 rounded-full">
                                        {{ number_format($site['biens_scannes'], 0, ',', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-700 font-medium">
                                    {{ number_format($site['total_biens'], 0, ',', ' ') }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-24 bg-gray-200 rounded-full h-2.5 mr-3">
                                            <div class="h-2.5 rounded-full transition-all duration-300
                                                {{ $site['progression'] >= 100 ? 'bg-green-600' : 
                                                   ($site['progression'] >= 50 ? 'bg-blue-600' : 
                                                   ($site['progression'] > 0 ? 'bg-yellow-500' : 'bg-gray-400')) }}" 
                                                style="width: {{ min($site['progression'], 100) }}%"></div>
                                        </div>
                                        <span class="text-sm font-medium
                                            {{ $site['progression'] >= 100 ? 'text-green-600' : 
                                               ($site['progression'] > 0 ? 'text-gray-700' : 'text-gray-400') }}">
                                            {{ round($site['progression'], 1) }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Section Activité récente -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Activité récente</h3>
        <div class="space-y-4">
            @forelse($dernieresActions as $action)
                <div class="flex items-start space-x-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600"><x-icon :name="$action['icon']" class="w-5 h-5" /></div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-900">{{ $action['message'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $action['time_ago'] }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 text-center py-8">Aucune activité récente</p>
            @endforelse
        </div>
    </div>

    <!-- Section Actions rapides (Admin) -->
    @if(auth()->user()->isAdmin())
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Actions rapides</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('biens.create') }}" wire:navigate
                   class="flex items-center justify-center px-4 py-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                    <x-icon name="plus" class="w-5 h-5 mr-2" />
                    <span class="font-medium">Ajouter une immobilisation</span>
                </a>
                <a href="{{ route('localisations.create') }}" wire:navigate
                   class="flex items-center justify-center px-4 py-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                    <x-icon name="location-marker" class="w-5 h-5 mr-2" />
                    <span class="font-medium">Ajouter une localisation</span>
                </a>
                <a href="{{ route('inventaires.create') }}" wire:navigate
                   class="flex items-center justify-center px-4 py-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                    <x-icon name="clipboard-list" class="w-5 h-5 mr-2" />
                    <span class="font-medium">Démarrer inventaire</span>
                </a>
                <a href="{{ route('users.index') }}" wire:navigate
                   class="flex items-center justify-center px-4 py-3 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition-colors">
                    <x-icon name="users" class="w-5 h-5 mr-2" />
                    <span class="font-medium">Gérer les utilisateurs</span>
                </a>
            </div>
        </div>
    @endif

    <!-- Chart.js -->
    @vite('resources/js/chart.js')

    <script>
        (() => {
            window.dashboardCharts ??= { statuts: null };

            const readData = (id, fallback) => {
                const element = document.getElementById(id);
                if (!element) return fallback;
                try { return JSON.parse(element.textContent); } catch (_) { return fallback; }
            };

            const initDashboardCharts = () => {
                window.dashboardCharts.statuts?.destroy();
                window.dashboardCharts.localisations?.destroy();
                window.dashboardCharts = { statuts: null };

                const statutsCtx = document.getElementById('statutsChart');
                const repartitionData = readData('dashboard-statuts-data', {});
                if (statutsCtx && Object.keys(repartitionData).length > 0) {
                    window.dashboardCharts.statuts = new Chart(statutsCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Présents', 'Déplacés', 'Absents', 'Détériorés'],
                        datasets: [{
                            data: [
                                repartitionData.present,
                                repartitionData.deplace,
                                repartitionData.absent,
                                repartitionData.deteriore
                            ],
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(249, 115, 22, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(107, 114, 128, 0.8)'
                            ],
                            borderColor: [
                                'rgb(34, 197, 94)',
                                'rgb(249, 115, 22)',
                                'rgb(239, 68, 68)',
                                'rgb(107, 114, 128)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                    });
                }

            };

            document.addEventListener('DOMContentLoaded', initDashboardCharts);
            document.addEventListener('livewire:navigated', initDashboardCharts);
            const registerDashboardListener = () => {
                Livewire.on('dashboard-updated', () => setTimeout(initDashboardCharts, 100));
            };
            if (typeof Livewire !== 'undefined') {
                registerDashboardListener();
            } else {
                document.addEventListener('livewire:init', registerDashboardListener, { once: true });
            }
        })();
    </script>
</div>
