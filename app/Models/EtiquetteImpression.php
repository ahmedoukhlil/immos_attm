<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtiquetteImpression extends Model
{
    protected $fillable = [
        'generation_uuid',
        'immobilisation_id',
        'designation_composant_id',
        'site_id',
        'user_id',
        'code_etiquette',
        'nom_composant',
        'mode',
        'exemplaires',
        'statut',
        'generee_at',
        'imprimee_at',
    ];

    protected $casts = [
        'generee_at' => 'datetime',
        'imprimee_at' => 'datetime',
        'exemplaires' => 'integer',
    ];

    public function immobilisation(): BelongsTo
    {
        return $this->belongsTo(Immobilisation::class)->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE);
    }

    public function composant(): BelongsTo
    {
        return $this->belongsTo(DesignationComposant::class, 'designation_composant_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'idUser');
    }
}
