<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Décision de sortie : un motif, un numéro, une date, et les biens concernés.
 * Brouillon tant qu'elle n'est pas validée ; la validation retire les biens du registre actif.
 */
class Sortie extends Model
{
    public const MOTIFS = [
        'reforme' => 'Réforme',
        'mise_au_rebut' => 'Mise au rebut',
        'cession' => 'Cession',
        'vente' => 'Vente',
        'don' => 'Don',
        'perte' => 'Perte',
        'vol' => 'Vol',
        'destruction' => 'Destruction (sinistre)',
    ];

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'validee' => 'Validée',
        'annulee' => 'Annulée',
    ];

    protected $fillable = [
        'numero',
        'motif',
        'date_decision',
        'inventaire_id',
        'observations',
        'statut',
        'created_by',
        'validee_par',
        'validee_at',
    ];

    protected $casts = [
        'date_decision' => 'date',
        'validee_at' => 'datetime',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(SortieImmobilisation::class)->orderBy('id');
    }

    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'idUser');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par', 'idUser');
    }

    public function getMotifLibelleAttribute(): string
    {
        return self::MOTIFS[$this->motif] ?? $this->motif;
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function estBrouillon(): bool
    {
        return $this->statut === 'brouillon';
    }
}
