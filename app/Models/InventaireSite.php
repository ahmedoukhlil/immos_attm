<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Avancement du scan d'un site au sein d'une localisation d'inventaire.
 */
class InventaireSite extends Model
{
    protected $table = 'inventaire_sites';

    protected $fillable = [
        'inventaire_localisation_id',
        'site_id',
        'statut',
        'date_debut_scan',
        'date_fin_scan',
        'nombre_biens_attendus',
        'nombre_biens_scannes',
        'finalization_key',
    ];

    protected $casts = [
        'date_debut_scan' => 'datetime',
        'date_fin_scan' => 'datetime',
    ];

    public function inventaireLocalisation(): BelongsTo
    {
        return $this->belongsTo(InventaireLocalisation::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function biensAttendus(): HasMany
    {
        return $this->hasMany(InventaireImmobilisationAttendue::class);
    }
}
