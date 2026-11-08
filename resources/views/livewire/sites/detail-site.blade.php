<div>
    <div class="space-y-6">
        {{-- Header avec retour --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('sites.index') }}" 
                   class="inline-flex items-center text-gray-600 hover:text-gray-900 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Retour à la liste
                </a>
            </div>

            <div class="flex items-center space-x-2">
                <a href="{{ route('sites.edit', $site) }}" 
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Modifier
                </a>
            </div>
        </div>

        {{-- Titre --}}
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $site->site }}</h1>
            <p class="mt-1 text-sm text-gray-500">Code: {{ $site->code }}</p>
        </div>

        @php $resumeEtiquettes = $this->resumeEtiquettes; @endphp
        <div class="rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50 to-white p-5 shadow-sm">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Étiquettes du site</h2>
                    <p class="mt-1 text-sm text-gray-600">Les matériels composés sont automatiquement développés en plusieurs étiquettes.</p>
                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                        <span class="rounded-lg bg-white px-3 py-2 text-gray-700 shadow-sm"><strong>{{ $resumeEtiquettes['immobilisations'] }}</strong> immobilisations</span>
                        <span class="rounded-lg bg-white px-3 py-2 text-gray-700 shadow-sm"><strong>{{ $resumeEtiquettes['composants'] }}</strong> composants</span>
                        <span class="rounded-lg bg-indigo-600 px-3 py-2 font-medium text-white"><strong>{{ $resumeEtiquettes['total'] }}</strong> étiquettes</span>
                    </div>
                </div>
                <a href="{{ route('sites.etiquettes.apercu', $site) }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                    Aperçu et impression
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Colonne principale --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Informations générales --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Informations générales</h2>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Localisation</label>
                                <p class="text-gray-900">
                                    @if($site->localisation)
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            {{ $site->localisation->localisation }}
                                        </span>
                                        <span class="text-xs text-gray-500 block ml-6">{{ $site->localisation->code }}</span>
                                    @else
                                        <span class="text-gray-400">Non définie</span>
                                    @endif
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
                                <p class="text-gray-900">
                                    @if($site->responsable)
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            {{ $site->responsable }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">Non renseigné</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Liste des biens --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Biens affectés</h2>
                            <p class="text-sm text-gray-600">{{ $site->immobilisations->count() }} bien(s)</p>
                        </div>
                    </div>

                    @if($site->immobilisations->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Ordre</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descriptif</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($site->immobilisations as $bien)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <a href="{{ route('biens.show', $bien->id) }}" class="text-indigo-600 hover:text-indigo-900">
                                                    {{ $bien->id }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $bien->libelle_article ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                {{ $bien->categorie->categorie ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                @if($bien->etat)
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        @if($bien->etat->etat === 'Bon') bg-green-100 text-green-800
                                                        @elseif($bien->etat->etat === 'Moyen') bg-yellow-100 text-yellow-800
                                                        @elseif(in_array($bien->etat->code, ['MAUV', 'HS'], true)) bg-red-100 text-red-800
                                                        @else bg-gray-100 text-gray-800
                                                        @endif">
                                                        {{ $bien->etat->etat }}
                                                    </span>
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                {{ $bien->descriptif ?? '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-6 py-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Aucun bien affecté à ce site</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Colonne latérale - QR Code --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden sticky top-6">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-600 to-indigo-700">
                        <h2 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            QR Code Inventaire
                        </h2>
                        <p class="text-xs text-indigo-200 mt-1">Pour l'application mobile PWA</p>
                    </div>
                    
                    <div class="p-6">
                        {{-- QR Code --}}
                        <div class="bg-white rounded-lg border-2 border-gray-200 p-4 mb-4">
                            <div class="flex justify-center">
                                <img src="{{ route('qrcodes.generate', $site->id) }}" 
                                     alt="QR Code {{ $site->code }}"
                                     class="w-full max-w-xs">
                            </div>
                        </div>

                        {{-- Code texte --}}
                        <div class="bg-gray-50 rounded-lg p-3 mb-4">
                            <p class="text-xs text-gray-600 mb-1">Code QR</p>
                            <p class="font-mono text-sm font-bold text-gray-900">EMP-{{ $site->id }}</p>
                        </div>

                        {{-- Instructions --}}
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-3 mb-4 rounded">
                            <p class="text-xs text-blue-800">
                                <strong class="inline-flex items-center gap-1"><x-icon name="device-mobile" class="w-3.5 h-3.5" /> Utilisation :</strong><br>
                                1. Imprimez ce QR code<br>
                                2. Collez-le sur la porte<br>
                                3. Scannez avec l'app mobile
                            </p>
                        </div>

                        {{-- Actions --}}
                        <div class="space-y-2">
                            <a href="{{ route('qrcodes.generate', $site->id) }}" 
                               download="qr-{{ $site->code }}.svg"
                               class="block w-full text-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Télécharger QR Code
                            </a>

                            <form action="{{ route('qrcodes.print-selected') }}" method="POST" target="_blank">
                                @csrf
                                <input type="hidden" name="sites[]" value="{{ $site->id }}">
                                <button type="submit" 
                                        class="block w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Imprimer
                                </button>
                            </form>
                        </div>

                        {{-- Statistiques rapides --}}
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-indigo-600">{{ $site->immobilisations->count() }}</p>
                                    <p class="text-xs text-gray-600">Biens</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-green-600">{{ $site->id }}</p>
                                    <p class="text-xs text-gray-600">ID</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
