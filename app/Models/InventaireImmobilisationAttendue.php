<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photographie d'une immobilisation attendue, figée au démarrage de l'inventaire.
 */
class InventaireImmobilisationAttendue extends Model
{
    protected $table = 'inventaire_immobilisations_attendues';

    protected $fillable = [
        'inventaire_id',
        'inventaire_localisation_id',
        'inventaire_site_id',
        'immobilisation_id',
        'code_propose',
        'designation',
        'categorie',
        'site_id',
        'site_code',
        'site_nom',
        'localisation_id',
        'localisation_code',
        'localisation_nom',
    ];

    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    public function inventaireLocalisation(): BelongsTo
    {
        return $this->belongsTo(InventaireLocalisation::class);
    }

    public function inventaireSite(): BelongsTo
    {
        return $this->belongsTo(InventaireSite::class);
    }

    /**
     * Code affiché : le code proposé figé, sinon l'identifiant de scan.
     */
    public function getCodeAfficheAttribute(): string
    {
        return $this->code_propose ?: 'GS'.$this->immobilisation_id;
    }
}
