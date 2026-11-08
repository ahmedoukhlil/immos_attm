<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site (bureau, service, salle) au sens du fichier « Inventaire Immo »,
 * avec son responsable. C'est l'unité scannée pendant un inventaire.
 */
class Site extends Model
{
    use HasFactory;

    protected $table = 'sites';

    public $timestamps = false;

    protected $fillable = ['localisation_id', 'site', 'code', 'responsable'];

    public function localisation(): BelongsTo
    {
        return $this->belongsTo(Localisation::class);
    }

    public function immobilisations(): HasMany
    {
        return $this->hasMany(Immobilisation::class);
    }

    public function etiquetteImpressions(): HasMany
    {
        return $this->hasMany(EtiquetteImpression::class);
    }
}
