<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bien inscrit dans une décision de sortie, avec ses libellés figés
 * au moment de l'inscription (le procès-verbal ne doit pas changer ensuite).
 */
class SortieImmobilisation extends Model
{
    public const CONSTATS = [
        'absent' => 'Absent à l’inventaire',
        'hors_service' => 'Hors service',
        'mauvais' => 'Mauvais état',
        'manuel' => 'Ajout manuel',
    ];

    protected $table = 'sortie_immobilisations';

    protected $fillable = [
        'sortie_id',
        'immobilisation_id',
        'code',
        'designation',
        'categorie',
        'site',
        'localisation',
        'etat',
        'constat',
    ];

    public function sortie(): BelongsTo
    {
        return $this->belongsTo(Sortie::class);
    }

    public function immobilisation(): BelongsTo
    {
        return $this->belongsTo(Immobilisation::class)
            ->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE)
            ->withTrashed();
    }

    public function getConstatLibelleAttribute(): string
    {
        return self::CONSTATS[$this->constat] ?? '—';
    }
}
