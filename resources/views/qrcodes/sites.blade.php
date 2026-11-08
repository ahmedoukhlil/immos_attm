<x-layouts.app>
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">QR Codes des Sites</h1>
        <p class="text-gray-600">Générez et imprimez les QR codes pour l'inventaire mobile</p>
    </div>

    {{-- Actions et Filtres --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('qrcodes.sites') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Filtre Localisation --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Localisation</label>
                    <select name="localisation" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Toutes les localisations</option>
                        @foreach($localisations as $loc)
                            <option value="{{ $loc->id }}" {{ request('localisation') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->localisation }}
                            </option>
                        @endforeach
                    </select>
                </div>


                {{-- Bouton Filtrer --}}
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filtrer
                    </button>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-200">
                <button type="button" onclick="selectAll()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                    Tout sélectionner
                </button>
                <button type="button" onclick="deselectAll()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                    Tout désélectionner
                </button>
                <button type="button" onclick="printSelected()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimer sélection
                </button>
                <a href="{{ route('qrcodes.sites.pdf', request()->all()) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Télécharger PDF
                </a>
            </div>
        </form>
    </div>

    {{-- Compteur --}}
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
        <p class="text-blue-800">
            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <strong>{{ $sites->count() }}</strong> site(s) trouvé(s)
        </p>
    </div>

    {{-- Grille des QR Codes --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($sites as $site)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition qr-card">
                {{-- Checkbox de sélection --}}
                <div class="flex items-center justify-between mb-3">
                    <input type="checkbox" 
                           class="emp-checkbox w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500" 
                           value="{{ $site->id }}"
                           id="emp-{{ $site->id }}">
                    <label for="emp-{{ $site->id }}" class="text-xs text-gray-500 cursor-pointer">
                        Sélectionner
                    </label>
                </div>

                {{-- QR Code --}}
                <div class="flex justify-center mb-3 bg-white p-2 rounded">
                    <img src="{{ route('qrcodes.generate', $site->id) }}" 
                         alt="QR Code {{ $site->code }}"
                         class="w-48 h-48">
                </div>

                {{-- Informations --}}
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-gray-900">{{ $site->code }}</span>
                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs font-mono">
                            EMP-{{ $site->id }}
                        </span>
                    </div>
                    
                    <div class="text-gray-700 font-medium">
                        {{ $site->site }}
                    </div>

                    @if($site->localisation)
                        <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ $site->localisation->localisation }}
                        </div>
                    @endif

                    @if($site->responsable)
                        <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            {{ $site->responsable }}
                        </div>
                    @endif

                    <div class="flex items-center text-gray-600">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        {{ $site->immobilisations_count }} bien(s)
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="text-gray-500">Aucun site trouvé</p>
            </div>
        @endforelse
    </div>
</div>

{{-- Formulaire caché pour impression --}}
<form id="print-form" action="{{ route('qrcodes.print-selected') }}" method="POST" target="_blank" class="hidden">
    @csrf
    <div id="print-inputs"></div>
</form>

@push('scripts')
<script>
// Sélectionner tous
function selectAll() {
    document.querySelectorAll('.emp-checkbox').forEach(cb => cb.checked = true);
}

// Désélectionner tous
function deselectAll() {
    document.querySelectorAll('.emp-checkbox').forEach(cb => cb.checked = false);
}

// Imprimer sélection
function printSelected() {
    const selected = [];
    document.querySelectorAll('.emp-checkbox:checked').forEach(cb => {
        selected.push(cb.value);
    });

    if (selected.length === 0) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Veuillez sélectionner au moins un site.', type: 'warning' } }));
        return;
    }

    // Préparer le formulaire
    const printInputs = document.getElementById('print-inputs');
    printInputs.innerHTML = '';
    
    selected.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'sites[]';
        input.value = id;
        printInputs.appendChild(input);
    });

    // Soumettre
    document.getElementById('print-form').submit();
}
</script>
@endpush
</x-layouts.app>
