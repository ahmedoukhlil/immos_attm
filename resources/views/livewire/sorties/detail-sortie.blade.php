<div class="py-6">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @php $brouillon = $sortie->estBrouillon(); @endphp

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Décision {{ $sortie->numero }}</h1>
                    <x-sortie-statut :statut="$sortie->statut" />
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $sortie->motif_libelle }} · {{ $lignes->count() }} bien(s)
                    @if($sortie->inventaire) · issue de l’inventaire {{ $sortie->inventaire->annee }} @endif
                    @if($sortie->validee_at) · validée le {{ $sortie->validee_at->format('d/m/Y') }} par {{ $sortie->validateur?->users ?? '—' }} @endif
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('sorties.pv', $sortie) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ $brouillon ? 'Projet de procès-verbal' : 'Procès-verbal' }} (PDF)
                </a>
                @if($sortie->inventaire)
                    <a href="{{ route('inventaires.report', $sortie->inventaire) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Report de l’inventaire</a>
                @else
                    <a href="{{ route('sorties.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Retour</a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @foreach(['statut', 'biens', 'date_decision'] as $champ)
            @error($champ) <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div> @enderror
        @endforeach

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Références de la décision --}}
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-1">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Décision</h2>
                <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
                    <label class="block text-sm font-medium text-gray-700">N° de décision
                        <input type="text" wire:model="numero" @disabled(! $brouillon) class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100">
                        @error('numero') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Date de la décision
                        <input type="date" wire:model="date_decision" @disabled(! $brouillon) class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100">
                        <span class="mt-1 block text-xs font-normal text-gray-500">Devient la date de sortie des biens.</span>
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Motif
                        <select wire:model="motif" @disabled(! $brouillon) class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100">
                            @foreach(\App\Models\Sortie::MOTIFS as $valeur => $libelle)
                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 sm:col-span-3 xl:col-span-1">Observations
                        <textarea wire:model="observations" rows="4" @disabled(! $brouillon) placeholder="Commission, PV de constat, acquéreur, déclaration de vol…" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100"></textarea>
                    </label>
                </div>

                @if($brouillon)
                    <div class="mt-5 flex flex-col gap-2 sm:flex-row xl:flex-col">
                        <button type="button" wire:click="enregistrer" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Enregistrer</button>
                        <button type="button" wire:click="valider" wire:confirm="Valider la décision {{ $sortie->numero }} ? Les {{ $lignes->count() }} bien(s) seront retirés du registre actif. Cette action est définitive."
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Valider la sortie</button>
                        <button type="button" wire:click="annuler" wire:confirm="Annuler cette décision ? Les biens resteront au registre."
                            class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Annuler la décision</button>
                    </div>
                @endif
            </section>

            {{-- Biens concernés --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Biens concernés</h2>
                    @if($brouillon)
                        <form wire:submit="ajouterParCode" class="flex gap-2">
                            <input type="text" wire:model="codeAjout" placeholder="Code, GS… ou n°" class="w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ajouter</button>
                        </form>
                    @endif
                </div>
                @error('codeAjout') <p class="px-5 pt-3 text-sm text-red-600">{{ $message }}</p> @enderror

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Code</th>
                                <th class="px-3 py-3">Désignation</th>
                                <th class="px-3 py-3">Site</th>
                                <th class="px-3 py-3">État / constat</th>
                                @if($brouillon) <th class="px-3 py-3"></th> @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($lignes as $ligne)
                                <tr wire:key="ligne-{{ $ligne->id }}">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs"><a href="{{ route('biens.show', $ligne->immobilisation_id) }}" class="text-indigo-600 hover:underline">{{ $ligne->code }}</a></td>
                                    <td class="px-3 py-3 font-medium text-gray-900">{{ $ligne->designation }}<span class="block text-xs font-normal text-gray-400">{{ $ligne->categorie }}</span></td>
                                    <td class="px-3 py-3 text-gray-600">{{ $ligne->site ?? '—' }}<span class="block text-xs text-gray-400">{{ $ligne->localisation }}</span></td>
                                    <td class="px-3 py-3 text-gray-600">{{ $ligne->etat ?? '—' }}<span class="block text-xs text-gray-400">{{ $ligne->constat_libelle }}</span></td>
                                    @if($brouillon)
                                        <td class="px-3 py-3 text-right"><button type="button" wire:click="retirer({{ $ligne->id }})" class="text-sm text-red-600 hover:underline">Retirer</button></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">Aucun bien dans cette décision.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
