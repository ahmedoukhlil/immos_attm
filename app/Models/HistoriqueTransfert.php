<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueTransfert extends Model
{
    use HasFactory;

    protected $table = 'historique_transferts';

    protected $fillable = [
        'immobilisation_id',
        'ancien_site_id',
        'nouveau_site_id',
        'ancien_site_libelle',
        'nouveau_site_libelle',
        'ancien_localisation_libelle',
        'nouveau_localisation_libelle',
        'transfert_par',
        'date_transfert',
        'raison',
        'groupe_transfert_id', // Pour regrouper les transferts effectués ensemble
    ];

    protected $casts = [
        'date_transfert' => 'datetime',
    ];

    public function immobilisation(): BelongsTo
    {
        return $this->belongsTo(Immobilisation::class)->withTrashed()->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE);
    }

    public function ancienSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'ancien_site_id');
    }

    public function nouveauSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'nouveau_site_id');
    }

    /**
     * Utilisateur qui a effectué le transfert
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transfert_par', 'idUser');
    }
}
