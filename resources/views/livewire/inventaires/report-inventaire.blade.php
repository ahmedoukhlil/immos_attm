<div class="py-6">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Report de l’inventaire {{ $inventaire->annee }}</h1>
                <p class="mt-1 text-sm text-gray-500">Ce que la clôture a reporté sur le registre, puis les biens à faire sortir.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('sorties.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Décisions de sortie</a>
                <a href="{{ route('inventaires.show', $inventaire) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Retour à l’inventaire</a>
            </div>
        </div>

        @if($inventaire->statut !== 'cloture')
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                Le report est disponible une fois l’inventaire <strong>clôturé</strong>. La clôture transfère les biens retrouvés ailleurs, met à jour les états constatés et reporte les codes d’étiquette ; les biens absents ou hors d’usage apparaîtront ensuite ici.
            </div>
        @else
            {{-- 1. Ce que la clôture a déjà appliqué --}}
            <section class="mb-6">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500">1. Reporté automatiquement à la clôture</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach([
                        ['valeur' => $this->bilan['biens_transferes'], 'libelle' => 'Biens transférés', 'aide' => 'Trouvés dans un autre site, avec historique.'],
                        ['valeur' => $this->bilan['etats_mis_a_jour'], 'libelle' => 'États mis à jour', 'aide' => 'État constaté différent de l’état connu.'],
                        ['valeur' => $this->bilan['codes_reportes'], 'libelle' => 'Codes reportés', 'aide' => 'Code de l’étiquette → « Code dernier inventaire ».'],
                    ] as $carte)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <p class="text-3xl font-bold text-gray-900">{{ $carte['valeur'] }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-700">{{ $carte['libelle'] }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $carte['aide'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- 2. Biens à régulariser --}}
            <section class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">2. Biens à régulariser</h2>
                    <p class="mt-1 text-sm text-gray-600">Cochez les biens à sortir, choisissez le motif puis inscrivez-les dans une décision. Rien n’est retiré du registre avant la validation de la décision.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-5 py-3 text-sm">
                    <span class="text-gray-500">Afficher :</span>
                    @foreach(['' => 'Tous', 'absent' => 'Absents', 'hors_service' => 'Hors service', 'mauvais' => 'Mauvais état'] as $valeur => $libelle)
                        <button type="button" wire:click="$set('filtreConstat', '{{ $valeur }}')"
                            @class(['rounded-full px-3 py-1 font-medium', 'bg-indigo-600 text-white' => $filtreConstat === $valeur, 'bg-gray-100 text-gray-700 hover:bg-gray-200' => $filtreConstat !== $valeur])>
                            {{ $libelle }}
                            <span class="ml-1 opacity-70">{{ $valeur === '' ? $compteurs->sum() : ($compteurs[$valeur] ?? 0) }}</span>
                        </button>
                    @endforeach
                    <span class="mx-2 hidden h-5 w-px bg-gray-200 sm:inline-block"></span>
                    <button type="button" wire:click="selectionnerConstat('absent')" class="text-indigo-600 hover:underline">Cocher les absents</button>
                    <button type="button" wire:click="selectionnerConstat('hors_service')" class="text-indigo-600 hover:underline">Cocher les hors service</button>
                </div>

                @if($lignes->isEmpty())
                    <p class="px-5 py-10 text-center text-sm text-gray-500">Aucun bien à régulariser pour ce filtre.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="w-10 px-5 py-3"></th>
                                    <th class="px-3 py-3">Code</th>
                                    <th class="px-3 py-3">Désignation</th>
                                    <th class="px-3 py-3">Site</th>
                                    <th class="px-3 py-3">Constat</th>
                                    <th class="px-3 py-3">Motif habituel</th>
                                    <th class="px-3 py-3">Décision</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($lignes as $ligne)
                                    @php $bien = $ligne['bien']; @endphp
                                    <tr wire:key="candidat-{{ $bien->id }}" class="hover:bg-gray-50">
                                        <td class="px-5 py-3">
                                            @if(! $ligne['sortie_brouillon'])
                                                <input type="checkbox" wire:model.live="selection" value="{{ $bien->id }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 font-mono text-xs"><a href="{{ route('biens.show', $bien) }}" class="text-indigo-600 hover:underline">{{ $bien->code_formate }}</a></td>
                                        <td class="px-3 py-3 font-medium text-gray-900">{{ $bien->libelle_article }}</td>
                                        <td class="px-3 py-3 text-gray-600">{{ $bien->site?->site ?? '—' }}<span class="block text-xs text-gray-400">{{ $bien->site?->localisation?->localisation }}</span></td>
                                        <td class="px-3 py-3">
                                            <span @class(['rounded-full px-2.5 py-1 text-xs font-medium',
                                                'bg-red-50 text-red-700' => $ligne['constat'] === 'absent',
                                                'bg-orange-50 text-orange-700' => $ligne['constat'] === 'hors_service',
                                                'bg-amber-50 text-amber-700' => $ligne['constat'] === 'mauvais'])>
                                                {{ \App\Models\SortieImmobilisation::CONSTATS[$ligne['constat']] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-gray-600">{{ \App\Models\Sortie::MOTIFS[$ligne['motif_propose']] }}</td>
                                        <td class="px-3 py-3">
                                            @if($ligne['sortie_brouillon'])
                                                <a href="{{ route('sorties.show', $ligne['sortie_brouillon']) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $ligne['sortie_brouillon']->numero }}</a>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:items-end">
                    <label class="text-sm font-medium text-gray-700">Motif de sortie
                        <select wire:model="motif" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 sm:w-64">
                            @foreach(\App\Models\Sortie::MOTIFS as $valeur => $libelle)
                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="button" wire:click="proposer" wire:loading.attr="disabled" @disabled($selection === [])
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                        Inscrire {{ count($selection) }} bien(s) dans une décision
                    </button>
                    <p class="text-xs text-gray-500 sm:ml-auto sm:max-w-xs">Les biens non cochés restent au registre. Un bien absent peut aussi être retrouvé plus tard : ne le sortez qu’après recherche.</p>
                </div>
                @error('selection') <p class="px-5 pb-4 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('biens') <p class="px-5 pb-4 text-sm text-red-600">{{ $message }}</p> @enderror
            </section>

            {{-- 3. Décisions liées à cet inventaire --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">3. Décisions de sortie de cet inventaire</h2>
                </div>
                @forelse($this->sorties as $sortie)
                    <a href="{{ route('sorties.show', $sortie) }}" wire:navigate class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-3 text-sm hover:bg-gray-50">
                        <span class="font-medium text-gray-900">{{ $sortie->numero }} · {{ $sortie->motif_libelle }}</span>
                        <span class="text-gray-500">{{ $sortie->lignes_count }} bien(s)</span>
                        <x-sortie-statut :statut="$sortie->statut" />
                    </a>
                @empty
                    <p class="px-5 py-6 text-sm text-gray-500">Aucune décision pour l’instant.</p>
                @endforelse
            </section>
        @endif
    </div>
</div>
