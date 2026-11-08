@props(['statut'])

<span {{ $attributes->class([
    'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
    'bg-gray-100 text-gray-700' => $statut === 'brouillon',
    'bg-green-50 text-green-700' => $statut === 'validee',
    'bg-red-50 text-red-700' => $statut === 'annulee',
]) }}>{{ \App\Models\Sortie::STATUTS[$statut] ?? $statut }}</span>
