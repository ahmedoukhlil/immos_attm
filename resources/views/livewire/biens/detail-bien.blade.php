<div>
    @php
        $isAdmin = auth()->user()->isAdmin();
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
                        <a href="{{ route('biens.index') }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-indigo-600 md:ml-2">Immobilisations</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">{{ $bien->scan_code }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="break-all text-2xl font-bold leading-tight text-gray-900 sm:text-3xl">
                    {{ $bien->code_formate }}
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $bien->libelle_article ?? 'N/A' }}
                </p>
                @if($bien->estSorti())
                    <p class="mt-2 inline-flex max-w-full items-center rounded-lg bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700">
                        Sorti du registre le {{ $bien->date_sortie?->format('d/m/Y') }} ({{ $bien->sortie?->motif_libelle }}) —
                        <a href="{{ route('sorties.show', $bien->sortie_id) }}" class="ml-1 underline">décision {{ $bien->sortie?->numero }}</a>
                    </p>
                @endif
                @if($bien->categorie)
                    <span class="mt-2 inline-flex max-w-full items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                        {{ $bien->categorie->categorie }}
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('biens.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour
                </a>

                @if($isAdmin)
                    <a href="{{ route('biens.edit', $bien) }}"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Modifier
                    </a>
                @endif

                @if($isAdmin)
                <button
                    type="submit"
                    form="form-etiquette-{{ $bien->id }}"
                    title="Imprimer l’étiquette QR de cette immobilisation"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimer
                </button>
                @endif

                @if($isAdmin)
                    <button
                        wire:click="supprimer"
                        wire:confirm="Archiver ce bien ? Il disparaîtra des listes actives mais son historique sera conservé."
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Archiver
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Section principale (2 colonnes) --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Colonne gauche (60%) --}}
        <div class="lg:col-span-3 space-y-6">
            {{-- Card 1 : Immobilisation --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Immobilisation</h2>

                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $bien->libelle_article ?? 'N/A' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $bien->descriptif ?: 'Aucun descriptif' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($bien->categorie)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {{ $bien->categorie->categorie }}
                            </span>
                        @endif
                        @if($bien->etat)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                {{ in_array($bien->etat->code, ['MAUV', 'HS'], true) ? 'bg-red-100 text-red-800' : ($bien->etat->code === 'MOY' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                {{ $bien->etat->etat }}
                            </span>
                        @endif
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                        <div>
                            <dt class="text-sm text-gray-500">Imputation comptable</dt>
                            <dd class="text-sm font-medium text-gray-900 font-mono">{{ $bien->categorie?->imputation_comptable ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Identifiant de scan</dt>
                            <dd class="text-sm font-medium text-indigo-600 font-mono">{{ $bien->scan_code }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Card 2 : Identification technique --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Identification technique</h2>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500">Marque</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $bien->marque ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Modèle</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $bien->modele ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Numéro de châssis</dt>
                        <dd class="text-sm font-medium text-gray-900 font-mono break-all">{{ $bien->numero_chassis ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Numéro ATTM</dt>
                        <dd class="text-sm font-medium text-gray-900 font-mono break-all">{{ $bien->numero_attm ?: '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-gray-500">Code immo</dt>
                        <dd class="text-sm font-semibold text-indigo-700 font-mono break-all">{{ $bien->code_immo ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card 3 : Codes d'inventaire --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Codes d'inventaire</h2>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500">Code proposé</dt>
                        <dd class="text-sm font-medium text-gray-900 font-mono break-all">{{ $bien->code_propose ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Code premier inventaire</dt>
                        <dd class="text-sm font-medium text-gray-900 font-mono break-all">{{ $bien->code_premier_inventaire ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Code dernier inventaire</dt>
                        <dd class="text-sm font-medium text-gray-900 font-mono break-all">{{ $bien->code_dernier_inventaire ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Card 4 : Site --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Site</h2>

                @if($bien->site)
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <dt class="text-sm text-gray-500 mb-1">Localisation</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $bien->site->localisation?->localisation ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 mb-1">Site</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                @if($isAdmin)
                                    <a href="{{ route('sites.show', $bien->site) }}" class="text-indigo-600 hover:text-indigo-900">{{ $bien->site->site }}</a>
                                @else
                                    {{ $bien->site->site }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500 mb-1">Responsable</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $bien->site->responsable ?? '-' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-500 italic">Aucun site assigné</p>
                @endif
            </div>

            {{-- Card 5 : Autres informations --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Autres informations</h2>
                @if($bien->autres_informations)
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $bien->autres_informations }}</p>
                @else
                    <p class="text-sm text-gray-500 italic">Aucune information complémentaire</p>
                @endif
            </div>
        </div>

        {{-- Colonne droite (40%) --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Card 1 : Étiquette réelle (même générateur que l'impression par site) --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="text-xl font-semibold text-gray-900">Étiquette d’identification</h2>
                    <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">QR · 70 × 24,4 mm</span>
                </div>

                {{-- Même HTML que le PDF imprimé, affiché à l'échelle de la carte --}}
                @php
                    $apercus = $this->apercusEtiquettes;
                @endphp
                <div class="space-y-3">
                    @forelse($apercus as $index => $html)
                        <div
                            wire:key="apercu-etiquette-{{ $bien->id }}-{{ $index }}"
                            x-data="{ zoom: 1, hauteur: 0 }"
                            x-init="
                                const ajuster = () => {
                                    zoom = $el.clientWidth / $refs.cadre.offsetWidth;
                                    hauteur = $refs.cadre.offsetHeight * zoom;
                                };
                                ajuster();
                                new ResizeObserver(ajuster).observe($el);
                            "
                            class="relative overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-200"
                            :style="{ height: `${hauteur}px` }">
                            <iframe
                                x-ref="cadre"
                                srcdoc="{{ $html }}"
                                sandbox=""
                                title="Étiquette {{ $index + 1 }} de {{ $bien->scan_code }}"
                                class="pointer-events-none absolute left-0 top-0"
                                style="width: 70mm; height: 24.4mm; border: 0; transform-origin: 0 0;"
                                :style="{ transform: `scale(${zoom})` }"></iframe>
                        </div>
                    @empty
                        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Cette immobilisation n’est rattachée à aucun site : aucune étiquette ne peut être générée.</p>
                    @endforelse
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <dt class="text-gray-500">Contenu du QR code</dt>
                        <dd class="break-all font-mono font-semibold text-gray-900">{{ $bien->contenu_qr }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Code affiché</dt>
                        <dd class="truncate font-mono text-gray-900">{{ $bien->code_formate }}</dd>
                    </div>
                </dl>
                @if(count($apercus) > 1)
                    <p class="mt-2 text-xs text-gray-500">Bien composé : {{ count($apercus) }} étiquettes, une par composant.</p>
                @endif

                @if($isAdmin)
                    <form id="form-etiquette-{{ $bien->id }}" method="POST" action="{{ route('sites.etiquettes.pdf', $bien->site_id) }}" target="_blank" class="mt-4">
                        @csrf
                        <input type="hidden" name="mode" value="bien">
                        <input type="hidden" name="bien" value="{{ $bien->id }}">
                        <input type="hidden" name="page_format" value="individuelle">
                        <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                            <x-icon name="qrcode" class="w-4 h-4" />
                            Imprimer l’étiquette
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-gray-500">
                        L’impression est enregistrée dans l’historique des étiquettes du site.
                        <a href="{{ route('sites.etiquettes.apercu', $bien->site_id) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Imprimer sur planche A4/A5</a>
                    </p>
                @endif
            </div>

            {{-- Card 2 : Actions rapides --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Actions rapides</h2>

                <div class="space-y-2">
                    @if($bien->site)
                        <a href="{{ route('biens.index', ['filterSite' => $bien->site_id]) }}"
                            class="block w-full text-left px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Biens de ce site
                        </a>
                    @endif
                    @if($bien->categorie)
                        <a href="{{ route('biens.index', ['filterCategorie' => $bien->categorie_id]) }}"
                            class="block w-full text-left px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Biens de cette catégorie
                        </a>
                    @endif
                    @if($bien->designation)
                        <a href="{{ route('biens.index', ['filterDesignation' => $bien->designation_id]) }}"
                            class="block w-full text-left px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Biens de cette désignation
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->isAdmin())
        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Journal d’audit</h2>
                <p class="text-sm text-gray-500">Les vingt dernières modifications sensibles de cette immobilisation.</p>
            </div>

            @if($this->auditLogs->isEmpty())
                <p class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                    Aucune opération auditée depuis l’activation du journal.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Action</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Champs concernés</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Utilisateur</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Adresse IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($this->auditLogs as $log)
                                @php
                                    $eventLabel = match ($log->event) {
                                        'created' => 'Création',
                                        'updated' => 'Modification',
                                        'archived' => 'Archivage',
                                        'restored' => 'Restauration',
                                        default => $log->event,
                                    };
                                    $changedFields = collect($log->new_values ?? $log->old_values ?? [])->keys()->implode(', ');
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $eventLabel }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $changedFields ?: '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $log->actor?->users ?? 'Système' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Historique propre à cette immobilisation --}}
    <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Historique des transferts</h2>
                <p class="text-sm text-gray-500">Les dix mouvements les plus récents de cette immobilisation.</p>
            </div>
            <a href="{{ route('biens.transfert.historique') }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                Voir l’historique complet
            </a>
        </div>

        @if($this->historiqueTransferts->isEmpty())
            <p class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                Aucun transfert enregistré pour cette immobilisation.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Origine</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Destination</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Motif</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Effectué par</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach($this->historiqueTransferts as $transfert)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $transfert->date_transfert?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $transfert->ancien_site_libelle ?: 'Sans site' }}</td>
                                <td class="px-4 py-3 font-medium text-indigo-700">{{ $transfert->nouveau_site_libelle ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $transfert->raison ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $transfert->utilisateur?->users ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

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
