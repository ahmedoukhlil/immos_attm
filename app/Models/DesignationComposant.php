<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignationComposant extends Model
{
    protected $fillable = ['designation_id', 'nom', 'code', 'quantite', 'obligatoire', 'ordre'];

    protected $casts = [
        'quantite' => 'integer',
        'obligatoire' => 'boolean',
        'ordre' => 'integer',
    ];

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(EtiquetteImpression::class, 'designation_composant_id');
    }
}
