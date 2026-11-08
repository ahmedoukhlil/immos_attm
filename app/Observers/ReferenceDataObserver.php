<?php

namespace App\Observers;

use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Immobilisation;
use App\Models\Localisation;
use App\Models\Site;
use App\Services\ReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ReferenceDataObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
        $this->recalculerCodes($model);
    }

    /**
     * Le code d'inventaire dépend de l'imputation comptable et du code de la désignation.
     */
    private function recalculerCodes(Model $model): void
    {
        if ($model instanceof Categorie && $model->wasChanged('imputation_comptable')) {
            Immobilisation::recalculerCodes(fn ($query) => $query->where('categorie_id', $model->id));
        }

        if ($model instanceof Designation && $model->wasChanged('code')) {
            Immobilisation::recalculerCodes(fn ($query) => $query->where('designation_id', $model->id));
        }
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $domains = match (true) {
            $model instanceof Localisation => ['localisations', 'sites'],
            $model instanceof Site => ['sites'],
            $model instanceof Categorie => ['categories', 'designations'],
            $model instanceof Designation => ['designations'],
            default => [],
        };

        ReferenceCache::invalidate(...$domains);

        foreach ($this->statisticKeys($model) as $key) {
            Cache::forget($key);
        }
    }

    /** @return list<string> */
    private function statisticKeys(Model $model): array
    {
        return match (true) {
            $model instanceof Localisation => ['localisations_total_count', 'sites_total_count'],
            $model instanceof Site => ['sites_total_count'],
            default => [],
        };
    }
}
