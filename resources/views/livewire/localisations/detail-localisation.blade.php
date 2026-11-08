<div>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $natures = [
            'mobilier' => ['label' => 'Mobilier', 'color' => 'bg-blue-100 text-blue-800'],
            'informatique' => ['label' => 'Informatique', 'color' => 'bg-purple-100 text-purple-800'],
            'vehicule' => ['label' => 'Véhicule', 'color' => 'bg-yellow-100 text-yellow-800'],
            'materiel' => ['label' => 'Matériel', 'color' => 'bg-green-100 text-green-800'],
        ];
        $etats = [
            'bon etat' => ['label' => 'Bon Etat', 'color' => 'bg-blue-100 text-blue-800'],
            'neuf' => ['label' => 'Neuf', 'color' => 'bg-green-100 text-green-800'],
            'defectueux' => ['label' => 'Defectueux', 'color' => 'bg-red-100 text-red-800'],
        ];
        $statutsInventaire = [
            'en_attente' => ['label' => 'En attente', 'color' => 'bg-gray-100 text-gray-800'],
            'en_cours' => ['label' => 'En cours', 'color' => 'bg-blue-100 text-blue-800'],
            'termine' => ['label' => 'Terminé', 'color' => 'bg-green-100 text-green-800'],
        ];
    @endphp

    {{-- Header avec breadcrumb et actions --}}
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
                        <a href="{{ route('localisations.index') }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-indigo-600 md:ml-2">Localisations</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">{{ $localisation->code }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    {{ $localisation->code }} - {{ $localisation->designation }}
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $localisation->actif ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $localisation->actif ? 'Actif' : 'Inactif' }}
                    </span>
                </h1>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <a 
                    href="{{ route('localisations.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour à la liste
                </a>

                @if($isAdmin)
                    <a 
                        href="{{ route('localisations.edit', $localisation) }}"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Modifier
                    </a>
                @endif

                <button 
                    wire:click="telechargerEtiquette"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Télécharger étiquette
                </button>

                @if($isAdmin)
                    <button 
                        wire:click="supprimer"
                        wire:confirm="Êtes-vous sûr de vouloir supprimer cette localisation ? Cette action est irréversible."
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Supprimer
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Section principale (2 colonnes) --}}
    <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
        {{-- Colonne gauche (65%) --}}
        <div class="lg:col-span-7 space-y-6">
            {{-- Card 1 : Informations --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Informations</h2>
                
                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ $localisation->designation }}</h3>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <p class="text-sm text-gray-500 mb-2">Code de localisation</p>
                        <div class="flex items-center gap-2">
                            <code class="px-3 py-2 bg-gray-100 rounded-lg text-lg font-mono font-bold">{{ $localisation->code }}</code>
                            <button 
                                onclick="navigator.clipboard.writeText(this.dataset.code).then(() => window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Code copié', type: 'success' } })))"
                                data-code="{{ $localisation->code }}"
                                class="p-2 text-gray-500 hover:text-gray-700 transition-colors"
                                title="Copier">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                        @if($localisation->batiment)
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Bâtiment</p>
                                <p class="text-sm font-medium text-gray-900 flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    {{ $localisation->batiment }}
                                </p>
                            </div>
                        @endif
                        @if($localisation->etage !== null)
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Étage</p>
                                <p class="text-sm font-medium text-gray-900 flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    {{ $localisation->etage }}
                                </p>
                            </div>
                        @endif
                    </div>

                    @if($localisation->service_rattache)
                        <div class="pt-4 border-t border-gray-200">
                            <p class="text-sm text-gray-500 mb-1">Service rattaché</p>
                            <p class="text-sm font-medium text-gray-900 flex items-center">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                {{ $localisation->service_rattache }}
                            </p>
                        </div>
                    @endif

                    @if($localisation->responsable)
                        <div class="pt-4 border-t border-gray-200">
                            <p class="text-sm text-gray-500 mb-1">Responsable</p>
                            <p class="text-sm font-medium text-gray-900 flex items-center">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ $localisation->responsable }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Card 2 : Statistiques --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Statistiques</h2>
                
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-sm text-gray-500">Total immobilisations</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $this->statistiques['total_biens'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Sites</p>
                        <p class="text-3xl font-bold text-indigo-600">
                            {{ $this->statistiques['total_sites'] }}
                        </p>
                    </div>
                </div>

                {{-- Graphique camembert : Répartition par nature --}}
                @if(!empty($this->statistiques['par_nature']))
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Répartition par catégorie</h3>
                        <div class="space-y-2 mb-4">
                            @foreach($this->statistiques['par_nature'] as $nature => $count)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        @if(isset($natures[$nature]))
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $natures[$nature]['color'] }} mr-2">
                                                {{ $natures[$nature]['label'] }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-700 mr-2">{{ ucfirst($nature) }}</span>
                                        @endif
                                    </div>
                                    <span class="text-sm font-medium text-gray-900">{{ $count }} immobilisation(s)</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="relative h-56 w-full" wire:ignore>
                            <canvas id="chart-nature" aria-label="Répartition des immobilisations par catégorie" role="img"></canvas>
                        </div>
                    </div>
                @endif

                {{-- Graphique barre : Répartition par état --}}
                @if(!empty($this->statistiques['par_etat']))
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Répartition par état</h3>
                        <div class="space-y-2 mb-4">
                            @foreach($this->statistiques['par_etat'] as $etat => $count)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        @if(isset($etats[$etat]))
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $etats[$etat]['color'] }} mr-2">
                                                {{ $etats[$etat]['label'] }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-700 mr-2">{{ ucfirst($etat) }}</span>
                                        @endif
                                    </div>
                                    <span class="text-sm font-medium text-gray-900">{{ $count }} immobilisation(s)</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="relative h-56 w-full" wire:ignore>
                            <canvas id="chart-etat" aria-label="Répartition des immobilisations par état" role="img"></canvas>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Card 3 : Liste des biens (collapsible) --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">
                        Immobilisations dans cette localisation ({{ $this->statistiques['total_biens'] }})
                    </h2>
                    <button 
                        wire:click="toggleAfficherBiens"
                        class="text-sm text-indigo-600 hover:text-indigo-800">
                        {{ $afficherBiens ? 'Masquer' : 'Afficher' }}
                    </button>
                </div>

                @if($afficherBiens)
                    {{-- Filtres pour les biens --}}
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input 
                                type="text"
                                wire:model.live.debounce.300ms="searchBien"
                                placeholder="Rechercher une immobilisation..."
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <select 
                                wire:model.live="filterNature"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Toutes les catégories</option>
                                @foreach($this->categories as $categorie)
                                    <option value="{{ $categorie->id }}">{{ $categorie->categorie }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Tableau des biens --}}
                    @if($this->biens->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Catégorie</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">État</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($this->biens as $bien)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $bien->code_formate }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ Str::limit($bien->libelle_article ?? 'N/A', 40) }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $bien->categorie->categorie ?? 'N/A' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                {{ $bien->site->site ?? 'N/A' }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                @php
                                                    $etatNom = strtolower($bien->etat->etat ?? '');
                                                @endphp
                                                @if(isset($etats[$etatNom]))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $etats[$etatNom]['color'] }}">
                                                        {{ $etats[$etatNom]['label'] }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        {{ $bien->etat->etat ?? 'N/A' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm">
                                                <a 
                                                    href="{{ route('biens.show', $bien->id) }}"
                                                    class="text-indigo-600 hover:text-indigo-900">
                                                    Voir
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $this->biens->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-sm text-gray-500">Aucun bien trouvé</p>
                        </div>
                    @endif

                    <div class="mt-4 flex gap-2">
                        <a 
                            href="{{ route('biens.create') }}"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Ajouter un bien
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Colonne droite (35%) --}}
        <div class="lg:col-span-3 space-y-6">
            {{-- Card 1 : QR Code --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">QR Code</h2>
                
                @if($localisation->qr_code_path && Storage::disk('public')->exists($localisation->qr_code_path))
                    <div class="text-center mb-4">
                        @if(str_ends_with($localisation->qr_code_path, '.svg'))
                            <div 
                                class="w-64 h-64 mx-auto cursor-pointer hover:opacity-80 transition-opacity flex items-center justify-center overflow-hidden"
                                onclick="document.getElementById('qr-modal').classList.remove('hidden')">
                                @php
                                    $svgContent = file_get_contents(storage_path('app/public/' . $localisation->qr_code_path));
                                    // Remplacer les attributs width et height du SVG pour qu'il s'adapte au conteneur
                                    $svgContent = preg_replace('/width="[^"]*"/', 'width="100%"', $svgContent);
                                    $svgContent = preg_replace('/height="[^"]*"/', 'height="100%"', $svgContent);
                                    $svgContent = str_replace('<svg', '<svg style="width: 100%; height: 100%; object-fit: contain;"', $svgContent);
                                @endphp
                                {!! $svgContent !!}
                            </div>
                        @else
                            <img 
                                src="{{ asset('storage/' . $localisation->qr_code_path) }}" 
                                alt="QR Code"
                                class="w-64 h-64 mx-auto cursor-pointer hover:opacity-80 transition-opacity"
                                onclick="document.getElementById('qr-modal').classList.remove('hidden')">
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 text-center mb-4">
                        À apposer sur la porte<br>
                        Taille d'impression recommandée : 10x10cm
                    </p>
                    <div class="space-y-2">
                        <a 
                            href="{{ asset('storage/' . $localisation->qr_code_path) }}"
                            download
                            class="block w-full text-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Télécharger QR (PNG)
                        </a>
                        <button 
                            wire:click="telechargerEtiquette"
                            class="w-full px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                            Télécharger étiquette (PDF)
                        </button>
                        <button 
                            onclick="window.print()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Imprimer
                        </button>
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        <p class="text-sm text-gray-500 mb-4">Aucun QR code généré</p>
                        <button 
                            wire:click="genererQRCode"
                            class="px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                            Générer QR Code
                        </button>
                    </div>
                @endif
            </div>

            {{-- Card 2 : Historique inventaires --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Historique inventaires</h2>
                
                @if($this->derniersInventaires->count() > 0)
                    <div class="space-y-4">
                        @foreach($this->derniersInventaires as $invLoc)
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">
                                        Inventaire {{ $invLoc->inventaire->annee ?? 'N/A' }}
                                    </span>
                                    @if(isset($statutsInventaire[$invLoc->statut]))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statutsInventaire[$invLoc->statut]['color'] }}">
                                            {{ $statutsInventaire[$invLoc->statut]['label'] }}
                                        </span>
                                    @endif
                                </div>
                                @if($invLoc->date_debut_scan)
                                    <p class="text-xs text-gray-500 mb-1">
                                        {{ $invLoc->date_debut_scan->format('d/m/Y') }}
                                    </p>
                                @endif
                                <p class="text-xs text-gray-600">
                                    {{ $invLoc->nombre_biens_scannes ?? 0 }} / {{ $invLoc->nombre_biens_attendus ?? 0 }} immobilisations scannées
                                </p>
                                @if($invLoc->nombre_biens_attendus > 0)
                                    @php
                                        $taux = round(($invLoc->nombre_biens_scannes / $invLoc->nombre_biens_attendus) * 100, 1);
                                    @endphp
                                    <p class="text-xs text-gray-500 mt-1">
                                        Taux : {{ $taux }}%
                                    </p>
                                @endif
                                @if($invLoc->agent)
                                    <p class="text-xs text-gray-500 mt-1">
                                        Agent : {{ $invLoc->agent->users ?? 'N/A' }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <a 
                            href="#inventaires-detaille"
                            class="text-sm text-indigo-600 hover:text-indigo-800">
                            Voir tous les inventaires →
                        </a>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-sm text-gray-500">Aucun inventaire enregistré</p>
                    </div>
                @endif
            </div>

            {{-- Card 3 : Actions rapides --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Actions rapides</h2>
                
                <div class="space-y-2">
                    @if($localisation->batiment)
                        <a 
                            href="{{ route('localisations.index', ['filterBatiment' => $localisation->batiment]) }}"
                            class="block w-full text-left px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Voir toutes les localisations du bâtiment
                        </a>
                    @endif
                    @if($localisation->service_rattache)
                        <a 
                            href="{{ route('localisations.index', ['filterService' => $localisation->service_rattache]) }}"
                            class="block w-full text-left px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Voir toutes les localisations du service
                        </a>
                    @endif
                    @if($localisation->etage !== null)
                        <a 
                            href="{{ route('localisations.index', ['filterEtage' => $localisation->etage]) }}"
                            class="block w-full text-left px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Voir toutes les localisations de l'étage
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Section activité (pleine largeur) --}}
    <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div x-data="{ activeTab: 'mouvements' }">
            {{-- Onglets --}}
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button 
                        @click="activeTab = 'mouvements'"
                        :class="activeTab === 'mouvements' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Mouvements récents
                    </button>
                    <button 
                        @click="activeTab = 'inventaires-detaille'"
                        :class="activeTab === 'inventaires-detaille' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Historique inventaires détaillé
                    </button>
                    <button 
                        @click="activeTab = 'photos'"
                        :class="activeTab === 'photos' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Photos/Plan
                    </button>
                </nav>
            </div>

            {{-- Contenu onglet Mouvements --}}
            <div x-show="activeTab === 'mouvements'" x-transition>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Biens entrés --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Immobilisations entrées</h3>
                        @if($this->mouvementsRecents['entres']->count() > 0)
                            <div class="space-y-3">
                                @foreach($this->mouvementsRecents['entres'] as $scan)
                                    <div class="border-l-4 border-green-500 pl-4 py-2">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $scan->bien?->code_formate ?? 'GS'.$scan->immobilisation_id }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $scan->date_scan->format('d/m/Y à H:i') }}
                                        </p>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Inventaire {{ $scan->inventaire->annee ?? 'N/A' }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500">Aucun bien entré récemment</p>
                        @endif
                    </div>

                    {{-- Biens sortis --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Immobilisations sorties</h3>
                        @if($this->mouvementsRecents['sortis']->count() > 0)
                            <div class="space-y-3">
                                @foreach($this->mouvementsRecents['sortis'] as $scan)
                                    <div class="border-l-4 border-red-500 pl-4 py-2">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $scan->bien?->code_formate ?? 'GS'.$scan->immobilisation_id }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $scan->date_scan->format('d/m/Y à H:i') }}
                                        </p>
                                        @if($scan->localisationReelle)
                                            <p class="text-xs text-gray-600 mt-1">
                                                Vers : {{ $scan->localisationReelle->code ?? $scan->localisationReelle->localisation ?? 'N/A' }}
                                            </p>
                                        @elseif($scan->statut_scan === 'absent')
                                            <p class="text-xs text-red-600 mt-1">
                                                Absent
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500">Aucun bien sorti récemment</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Contenu onglet Inventaires détaillé --}}
            <div x-show="activeTab === 'inventaires-detaille'" x-transition style="display: none;" id="inventaires-detaille">
                @if($this->tousInventaires->count() > 0)
                    <div class="space-y-4">
                        @foreach($this->tousInventaires as $invLoc)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900">
                                            Inventaire {{ $invLoc->inventaire->annee ?? 'N/A' }}
                                        </h4>
                                        @if($invLoc->date_debut_scan && $invLoc->date_fin_scan)
                                            <p class="text-sm text-gray-500">
                                                Du {{ $invLoc->date_debut_scan->format('d/m/Y') }} au {{ $invLoc->date_fin_scan->format('d/m/Y') }}
                                            </p>
                                        @endif
                                    </div>
                                    @if(isset($statutsInventaire[$invLoc->statut]))
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statutsInventaire[$invLoc->statut]['color'] }}">
                                            {{ $statutsInventaire[$invLoc->statut]['label'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Immobilisations attendues</p>
                                        <p class="font-medium text-gray-900">{{ $invLoc->nombre_biens_attendus ?? 0 }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Immobilisations scannées</p>
                                        <p class="font-medium text-gray-900">{{ $invLoc->nombre_biens_scannes ?? 0 }}</p>
                                    </div>
                                    @if($invLoc->nombre_biens_attendus > 0)
                                        @php
                                            $taux = round(($invLoc->nombre_biens_scannes / $invLoc->nombre_biens_attendus) * 100, 1);
                                        @endphp
                                        <div>
                                            <p class="text-gray-500">Taux conformité</p>
                                            <p class="font-medium text-indigo-600">{{ $taux }}%</p>
                                        </div>
                                    @endif
                                    @if($invLoc->agent)
                                        <div>
                                            <p class="text-gray-500">Agent</p>
                                            <p class="font-medium text-gray-900">{{ $invLoc->agent->name }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p class="text-sm text-gray-500">Aucun inventaire enregistré</p>
                    </div>
                @endif
            </div>

            {{-- Contenu onglet Photos/Plan --}}
            <div x-show="activeTab === 'photos'" x-transition style="display: none;">
                <div class="text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm text-gray-500 mb-2">Fonctionnalité à venir</p>
                    <p class="text-xs text-gray-400">Upload photo de la localisation et plan d'implantation des immobilisations</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal QR Code agrandi --}}
    <div id="qr-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" onclick="this.classList.add('hidden')">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black bg-opacity-50" onclick="document.getElementById('qr-modal').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-lg p-8 max-w-md" onclick="event.stopPropagation()">
                <button 
                    onclick="document.getElementById('qr-modal').classList.add('hidden')"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                @if($localisation->qr_code_path && Storage::disk('public')->exists($localisation->qr_code_path))
                    @if(str_ends_with($localisation->qr_code_path, '.svg'))
                        <div class="w-full flex items-center justify-center">
                            @php
                                $svgContent = file_get_contents(storage_path('app/public/' . $localisation->qr_code_path));
                                $svgContent = str_replace('<svg', '<svg style="max-width: 100%; height: auto;"', $svgContent);
                            @endphp
                            {!! $svgContent !!}
                        </div>
                    @else
                        <img 
                            src="{{ asset('storage/' . $localisation->qr_code_path) }}" 
                            alt="QR Code"
                            class="w-full h-auto">
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Scripts pour les graphiques Chart.js --}}
    @vite('resources/js/chart.js')
    <script>
        (() => {
        // Chart.js est chargé en module (différé) : on attend sa disponibilité, puis on
        // recrée les graphiques sans empiler d'instances (navigation Livewire comprise).
        const initialiserGraphiques = () => {
            if (! window.Chart) {
                setTimeout(initialiserGraphiques, 50);
                return;
            }
            ['chart-nature', 'chart-etat'].forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas) window.Chart.getChart(canvas)?.destroy();
            });

            // Graphique camembert : Répartition par nature
            @if(!empty($this->statistiques['par_nature']))
                const ctxNature = document.getElementById('chart-nature');
                if (ctxNature) {
                    const dataNature = @json($this->statistiques['par_nature']);
                    new window.Chart(ctxNature, {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(dataNature).map(nature => {
                                const labels = @json($natures);
                                return labels[nature]?.label || nature;
                            }),
                            datasets: [{
                                data: Object.values(dataNature),
                                backgroundColor: [
                                    'rgba(59, 130, 246, 0.8)',   // Bleu - Mobilier
                                    'rgba(168, 85, 247, 0.8)',   // Violet - Informatique
                                    'rgba(234, 179, 8, 0.8)',    // Jaune - Véhicule
                                    'rgba(34, 197, 94, 0.8)',    // Vert - Matériel
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                }
                            }
                        }
                    });
                }
            @endif

            // Graphique barre : Répartition par état
            @if(!empty($this->statistiques['par_etat']))
                const ctxEtat = document.getElementById('chart-etat');
                if (ctxEtat) {
                    const dataEtat = @json($this->statistiques['par_etat']);
                    new window.Chart(ctxEtat, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(dataEtat).map(etat => {
                                const labels = @json($etats);
                                return labels[etat]?.label || etat;
                            }),
                            datasets: [{
                                label: 'Nombre de biens',
                                data: Object.values(dataEtat),
                                backgroundColor: [
                                    'rgba(34, 197, 94, 0.8)',    // Vert - Neuf/Bon
                                    'rgba(234, 179, 8, 0.8)',    // Jaune - Moyen
                                    'rgba(239, 68, 68, 0.8)',    // Rouge - Mauvais
                                    'rgba(156, 163, 175, 0.8)',  // Gris - Réformé
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            @endif
        };

        initialiserGraphiques();
        })();
    </script>

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

