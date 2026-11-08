<div class="py-6">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Sorties d’immobilisations</h1>
                <p class="mt-1 text-sm text-gray-500">Réformes, mises au rebut, cessions, pertes et vols, regroupés par décision.</p>
            </div>
            <a href="{{ route('sorties.export') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Exporter le registre des sorties</a>
        </div>

        <div class="mb-4 flex gap-2 border-b border-gray-200">
            @foreach(['decisions' => 'Décisions', 'registre' => 'Registre des biens sortis'] as $valeur => $libelle)
                <button type="button" wire:click="$set('onglet', '{{ $valeur }}')"
                    @class(['-mb-px border-b-2 px-4 py-2 text-sm font-medium', 'border-indigo-600 text-indigo-700' => $onglet === $valeur, 'border-transparent text-gray-500 hover:text-gray-700' => $onglet !== $valeur])>
                    {{ $libelle }}
                </button>
            @endforeach
        </div>

        <div class="mb-6 grid gap-4 rounded-lg bg-white p-6 shadow sm:grid-cols-3">
            <label class="block text-sm font-medium text-gray-700">Recherche
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ $onglet === 'decisions' ? 'N° de décision, observation…' : 'Code, désignation, n° de décision…' }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
            </label>
            <label class="block text-sm font-medium text-gray-700">Motif
                <select wire:model.live="filtreMotif" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                    <option value="">Tous les motifs</option>
                    @foreach(\App\Models\Sortie::MOTIFS as $valeur => $libelle)
                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </label>
            @if($onglet === 'decisions')
                <label class="block text-sm font-medium text-gray-700">Statut
                    <select wire:model.live="filtreStatut" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        <option value="">Tous les statuts</option>
                        @foreach(\App\Models\Sortie::STATUTS as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            @if($onglet === 'decisions')
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3">N° de décision</th>
                                <th class="px-3 py-3">Motif</th>
                                <th class="px-3 py-3">Date</th>
                                <th class="px-3 py-3">Inventaire</th>
                                <th class="px-3 py-3">Biens</th>
                                <th class="px-3 py-3">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($decisions as $sortie)
                                <tr wire:key="sortie-{{ $sortie->id }}" class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-medium"><a href="{{ route('sorties.show', $sortie) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $sortie->numero }}</a></td>
                                    <td class="px-3 py-3 text-gray-700">{{ $sortie->motif_libelle }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ $sortie->date_decision?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ $sortie->inventaire?->annee ?? 'Hors inventaire' }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ $sortie->lignes_count }}</td>
                                    <td class="px-3 py-3"><x-sortie-statut :statut="$sortie->statut" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">Aucune décision. Elles se créent depuis le report d’un inventaire clôturé.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3">{{ $decisions->links() }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Code</th>
                                <th class="px-3 py-3">Désignation</th>
                                <th class="px-3 py-3">Dernier site</th>
                                <th class="px-3 py-3">Motif</th>
                                <th class="px-3 py-3">Date de sortie</th>
                                <th class="px-3 py-3">Décision</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($biensSortis as $bien)
                                <tr wire:key="sorti-{{ $bien->id }}" class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs"><a href="{{ route('biens.show', $bien) }}" class="text-indigo-600 hover:underline">{{ $bien->code_formate }}</a></td>
                                    <td class="px-3 py-3 font-medium text-gray-900">{{ $bien->libelle_article }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ $bien->site?->site ?? '—' }}</td>
                                    <td class="px-3 py-3 text-gray-700">{{ $bien->sortie?->motif_libelle }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ $bien->date_sortie?->format('d/m/Y') }}</td>
                                    <td class="px-3 py-3"><a href="{{ route('sorties.show', $bien->sortie_id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $bien->sortie?->numero }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">Aucun bien sorti du registre.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3">{{ $biensSortis->links() }}</div>
            @endif
        </div>
    </div>
</div>
