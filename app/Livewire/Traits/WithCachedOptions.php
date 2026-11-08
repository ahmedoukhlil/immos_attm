<?php

namespace App\Livewire\Traits;

use App\Models\Localisation;
use App\Models\Site;
use App\Services\ReferenceCache;
use Illuminate\Support\Facades\Cache;

/**
 * Trait pour optimiser les requêtes d'options dans les composants Livewire
 * Utilise le cache Laravel pour des performances ultra-rapides
 */
trait WithCachedOptions
{
    /**
     * Retourne les options de localisations avec cache
     *
     * @param  int  $cacheDuration  Durée du cache en secondes (default: 300 = 5 minutes)
     */
    protected function getCachedLocalisationOptions(int $cacheDuration = 300): array
    {
        return Cache::remember(ReferenceCache::key('localisations', 'options_all'), $cacheDuration, function () {
            return Localisation::select('id', 'localisation', 'code')
                ->orderBy('localisation')
                ->get()
                ->map(fn (Localisation $localisation): array => [
                    'value' => (string) $localisation->id,
                    'text' => ($localisation->code ? $localisation->code.' - ' : '').$localisation->localisation,
                ])
                ->toArray();
        });
    }

    /**
     * Retourne les options de sites, éventuellement filtrés par localisation, avec cache
     *
     * @param  int|null  $localisationId  ID de la localisation pour filtrer
     * @param  int  $cacheDuration  Durée du cache en secondes
     * @param  bool  $withDetails  Préfixer le libellé par la localisation
     */
    protected function getCachedSiteOptions(
        ?int $localisationId = null,
        int $cacheDuration = 300,
        bool $withDetails = false
    ): array {
        $cacheKey = ReferenceCache::key(
            'sites',
            'options_'.($localisationId ?? 'all').($withDetails ? '_detailed' : ''),
        );

        return Cache::remember($cacheKey, $cacheDuration, function () use ($localisationId, $withDetails) {
            return Site::select('id', 'site', 'code', 'responsable', 'localisation_id')
                ->when($localisationId, fn ($query) => $query->where('localisation_id', $localisationId))
                ->when($withDetails, fn ($query) => $query->with('localisation:id,localisation'))
                ->orderBy('site')
                ->get()
                ->map(function (Site $site) use ($withDetails): array {
                    $text = $site->site.($site->responsable ? ' ('.$site->responsable.')' : '');

                    if ($withDetails && $site->localisation) {
                        $text = $site->localisation->localisation.' > '.$text;
                    }

                    return [
                        'value' => (string) $site->id,
                        'text' => $text,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Invalide le cache pour les localisations
     */
    protected function invalidateLocalisationCache(): void
    {
        ReferenceCache::invalidate('localisations', 'sites');
    }

    /**
     * Invalide le cache pour les sites
     */
    protected function invalidateSiteCache(): void
    {
        ReferenceCache::invalidate('sites');
    }
}
