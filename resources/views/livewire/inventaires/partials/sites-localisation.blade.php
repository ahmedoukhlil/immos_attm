{{-- Avancement des sites d'une localisation, avec réouverture d'un site terminé par erreur --}}
@php
    $statutsSite = [
        'en_attente' => ['label' => 'En attente', 'color' => 'bg-gray-100 text-gray-700'],
        'en_cours' => ['label' => 'En cours', 'color' => 'bg-blue-100 text-blue-700'],
        'termine' => ['label' => 'Terminé', 'color' => 'bg-green-100 text-green-700'],
    ];
    $peutRouvrir = $isAdmin && in_array($inventaire->statut, ['en_cours', 'termine'], true);
    $messageConfirmation = $inventaire->statut === 'termine'
        ? "Rouvrir ce site ? Les biens marqués absents redeviendront à scanner et l'inventaire repassera en cours."
        : 'Rouvrir ce site ? Les biens marqués absents redeviendront à scanner.';
@endphp
<div class="flex items-center justify-between mb-2">
    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Sites</p>
    <button wire:click="toggleSitesPanel({{ $invLoc->id }})" class="p-1 rounded text-gray-400 hover:text-gray-600 transition-colors" title="Fermer">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>
<ul class="divide-y divide-gray-100 bg-white rounded-lg border border-gray-100">
    @forelse($invLoc->inventaireSites->sortBy(fn ($invSite) => $invSite->site?->site) as $invSite)
        <li class="flex flex-wrap items-center gap-2 px-3 py-2" wire:key="inv-site-{{ $invSite->id }}">
            <span class="text-sm text-gray-800 flex-1 min-w-0 truncate">
                {{ $invSite->site?->site ?? 'Site supprimé' }}
                @if($invSite->site?->code)
                    <span class="text-[11px] font-mono text-gray-400 ml-1">{{ $invSite->site->code }}</span>
                @endif
            </span>
            <span class="text-[11px] tabular-nums text-gray-500">{{ $invSite->nombre_biens_scannes }}/{{ $invSite->nombre_biens_attendus }}</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium {{ $statutsSite[$invSite->statut]['color'] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $statutsSite[$invSite->statut]['label'] ?? $invSite->statut }}
            </span>
            @if($peutRouvrir && $invSite->statut === 'termine')
                <button
                    wire:click="rouvrirSite({{ $invSite->id }})"
                    wire:confirm="{{ $messageConfirmation }}"
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 transition-colors"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Rouvrir
                </button>
            @endif
        </li>
    @empty
        <li class="px-3 py-2 text-xs text-gray-400">Aucun site dans cette localisation.</li>
    @endforelse
</ul>
