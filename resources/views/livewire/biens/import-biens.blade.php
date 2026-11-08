<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    @php
        $libellesTypes = [
            'localisations' => ['Nouvelles localisations', ['localisation' => 'Localisation']],
            'sites' => ['Nouveaux sites', ['site' => 'Site', 'localisation' => 'Localisation', 'responsable' => 'Responsable']],
            'responsables' => ['Responsables à renseigner ou modifier', ['site' => 'Site', 'actuel' => 'Responsable actuel', 'nouveau' => 'Responsable du fichier']],
            'categories' => ['Nouvelles catégories', ['categorie' => 'Catégorie', 'imputation_comptable' => 'Imputation comptable']],
            'designations' => ['Nouvelles désignations', ['designation' => 'Désignation', 'categorie' => 'Catégorie']],
        ];
        $libellesCompteurs = [
            'lignes_lues' => 'Lignes lues',
            'biens_crees' => $resultat ? 'Immobilisations créées' : 'Immobilisations à créer',
            'deja_importees' => 'Déjà importées (ignorées)',
            'lignes_ignorees' => 'Lignes ignorées',
        ];
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Importer depuis un fichier Excel</h2>
            <p class="mt-1 text-sm text-gray-500">
                Remplissez le modèle (une ligne par immobilisation, ou une quantité), puis analysez-le :
                ce qui n'existe pas encore (site, responsable, désignation, catégorie, localisation) vous sera proposé à la création.
            </p>
        </div>
        <a href="{{ route('biens.import.modele') }}"
           class="inline-flex shrink-0 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Télécharger le modèle
        </a>
    </div>

    {{-- Étape 1 : fichier --}}
    <div x-data="{ envoi: false, echec: false }"
         x-on:livewire-upload-start="envoi = true; echec = false"
         x-on:livewire-upload-finish="envoi = false"
         x-on:livewire-upload-cancel="envoi = false"
         x-on:livewire-upload-error="envoi = false; echec = true">
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <input type="file" wire:model="fichier" accept=".xlsx,.xls"
                   class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
            {{-- Pas de @disabled(! $fichier) : il n'est pas réactivé de façon fiable après le téléversement.
                 Sans fichier, la validation serveur affiche « Choisissez un fichier Excel. » --}}
            <button type="button" wire:click="analyser" wire:loading.attr="disabled" wire:target="analyser"
                    x-bind:disabled="envoi"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="analyser">Analyser le fichier</span>
                <span wire:loading wire:target="analyser">Analyse en cours…</span>
            </button>
        </div>
        <p x-show="envoi" x-cloak class="mt-2 text-xs text-gray-500">Envoi du fichier…</p>
        <p x-show="echec" x-cloak class="mt-2 text-sm text-red-600">
            L'envoi du fichier a échoué. Vérifiez sa taille (10 Mo maximum) et réessayez.
        </p>
    </div>
    @error('fichier')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    {{-- Résultat de l'import réel --}}
    @if($resultat)
        <div class="mt-5 rounded-lg border border-green-200 bg-green-50 p-4" role="status">
            <p class="text-sm font-semibold text-green-900">Import terminé</p>
            <ul class="mt-2 grid grid-cols-2 gap-2 text-sm text-green-800 sm:grid-cols-4">
                @foreach($libellesCompteurs as $cle => $libelle)
                    <li><span class="font-semibold">{{ $resultat['compteurs'][$cle] }}</span> {{ $libelle }}</li>
                @endforeach
            </ul>
            <a href="{{ route('biens.index') }}" class="mt-3 inline-block text-sm font-medium text-green-900 underline">Voir les immobilisations</a>
        </div>
    @endif

    {{-- Étape 2 : analyse et choix des créations --}}
    @if($analyse)
        <div class="mt-5 space-y-5">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach($libellesCompteurs as $cle => $libelle)
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-xs text-gray-500">{{ $libelle }}</p>
                        <p class="text-xl font-bold text-gray-900">{{ number_format($analyse['compteurs'][$cle], 0, ',', ' ') }}</p>
                    </div>
                @endforeach
            </div>

            @if($analyse['compteurs']['biens_crees'] === 0 && $analyse['compteurs']['deja_importees'] > 0)
                <p class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
                    Toutes les immobilisations de ce fichier ont déjà été importées : il n'y a rien à créer.
                </p>
            @endif

            @if(count($analyse['immobilisations']) > 0)
                @php($lignesACreer = count(array_filter($analyse['immobilisations'], fn ($i) => $i['quantite'] > 0)))
                <div>
                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">
                            Lignes du fichier ({{ count(array_filter($retenues)) }} / {{ $lignesACreer }} à créer retenues)
                        </h3>
                        <div class="flex gap-3 text-xs">
                            <button type="button" wire:click="toutCocher(true)" class="font-medium text-indigo-700 hover:underline">Tout cocher</button>
                            <button type="button" wire:click="toutCocher(false)" class="font-medium text-indigo-700 hover:underline">Tout décocher</button>
                        </div>
                    </div>
                    <p class="mb-2 text-xs text-gray-500">
                        Vérifiez chaque ligne : seules les lignes cochées seront créées. Les codes à créer sont indicatifs et attribués à l'import ;
                        les lignes déjà importées depuis ce fichier ne peuvent pas être recréées.
                    </p>
                    <div class="max-h-96 overflow-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="w-10 px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Créer</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Ligne</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Désignation</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Descriptif</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Site</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">État</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Année</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">Qté</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Code(s)</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Statut</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach($analyse['immobilisations'] as $index => $immobilisation)
                                    <tr wire:key="immobilisation-{{ $index }}" @class(['bg-gray-50 text-gray-400' => ! ($retenues[$index] ?? false)])>
                                        <td class="px-3 py-2">
                                            <input type="checkbox" wire:model.live="retenues.{{ $index }}" @disabled($immobilisation['quantite'] === 0)
                                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                   aria-label="Créer la ligne {{ $immobilisation['ligne'] }}">
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs">L{{ $immobilisation['ligne'] }}</td>
                                        <td class="px-3 py-2 font-medium">{{ $immobilisation['designation'] }}</td>
                                        <td class="px-3 py-2">{{ $immobilisation['descriptif'] ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $immobilisation['site'] }} <span class="text-xs text-gray-400">({{ $immobilisation['localisation'] }})</span></td>
                                        <td class="px-3 py-2">{{ $immobilisation['etat'] }}</td>
                                        <td class="px-3 py-2">{{ $immobilisation['annee'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $immobilisation['quantite'] + $immobilisation['deja_importees'] }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">
                                            @php($tousCodes = array_merge($immobilisation['codes_existants'], $immobilisation['codes']))
                                            {{ $tousCodes[0] }}@if(count($tousCodes) > 1) … {{ end($tousCodes) }}@endif
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            @if($immobilisation['quantite'] === 0)
                                                <span class="rounded-full bg-gray-200 px-2 py-0.5 font-medium text-gray-700">Déjà importée</span>
                                            @elseif($immobilisation['deja_importees'] > 0)
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800">{{ $immobilisation['quantite'] }} à créer, {{ $immobilisation['deja_importees'] }} déjà importée(s)</span>
                                            @else
                                                <span class="rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800">À créer</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('retenues')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @foreach($libellesTypes as $type => [$titre, $colonnes])
                @if(count($analyse['propositions'][$type] ?? []) > 0)
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-800">
                                {{ $titre }} ({{ count($analyse['propositions'][$type]) }})
                            </h3>
                            <span class="text-xs text-gray-500">Décochez ce qui ne doit pas être créé : les lignes concernées seront ignorées.</span>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="w-10 px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Créer</th>
                                        @foreach($colonnes as $libelle)
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">{{ $libelle }}</th>
                                        @endforeach
                                        <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">Lignes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach($analyse['propositions'][$type] as $index => $proposition)
                                        <tr wire:key="proposition-{{ $type }}-{{ $index }}">
                                            <td class="px-3 py-2">
                                                <input type="checkbox" wire:model="choix.{{ $type }}.{{ $index }}"
                                                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                       aria-label="Créer {{ $proposition[array_key_first($colonnes)] }}">
                                            </td>
                                            @foreach(array_keys($colonnes) as $champ)
                                                <td class="px-3 py-2 text-gray-800">{{ $proposition[$champ] ?? '—' }}</td>
                                            @endforeach
                                            <td class="px-3 py-2 text-right text-gray-500">{{ $proposition['lignes'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endforeach

            @if($analyse['anomalies_total'] > 0)
                <details class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <summary class="cursor-pointer text-sm font-medium text-amber-900">
                        {{ $analyse['anomalies_total'] }} remarque(s) sur le fichier
                    </summary>
                    <ul class="mt-2 max-h-64 space-y-1 overflow-y-auto text-xs text-amber-900">
                        @foreach($analyse['anomalies'] as $anomalie)
                            <li><span class="font-mono">L{{ $anomalie['ligne'] }}</span> — {{ $anomalie['message'] }}</li>
                        @endforeach
                        @if($analyse['anomalies_total'] > count($analyse['anomalies']))
                            <li class="italic">… et {{ $analyse['anomalies_total'] - count($analyse['anomalies']) }} autre(s).</li>
                        @endif
                    </ul>
                </details>
            @endif

            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end">
                <button type="button" wire:click="annuler"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Annuler
                </button>
                <button type="button" wire:click="importer" wire:loading.attr="disabled" wire:target="importer"
                        @disabled(count(array_filter($retenues)) === 0)
                        wire:confirm="Importer les immobilisations et créer les éléments cochés ?"
                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="importer">Importer</span>
                    <span wire:loading wire:target="importer">Import en cours…</span>
                </button>
            </div>
        </div>
    @endif
</div>
