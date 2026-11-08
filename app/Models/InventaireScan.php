<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventaireScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventaire_id',
        'inventaire_localisation_id',
        'immobilisation_id',
        'date_scan',
        'statut_scan',
        'localisation_reelle_id',
        'site_reel_id',
        'etat_constate',
        'commentaire',
        'photo_path',
        'user_id',
    ];

    protected $casts = [
        'date_scan' => 'datetime',
    ];

    /**
     * RELATIONS
     */

    /**
     * Relation avec l'inventaire
     */
    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    /**
     * Relation avec l'inventaire_localisation
     */
    public function inventaireLocalisation(): BelongsTo
    {
        return $this->belongsTo(InventaireLocalisation::class);
    }

    /**
     * Immobilisation scannée
     */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Immobilisation::class, 'immobilisation_id')
            ->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE);
    }

    /**
     * Relation avec la localisation réelle (où le bien a été trouvé)
     */
    public function localisationReelle(): BelongsTo
    {
        return $this->belongsTo(Localisation::class, 'localisation_reelle_id');
    }

    public function siteReel(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_reel_id');
    }

    /**
     * Relation avec l'agent qui a effectué le scan
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'idUser');
    }

    /**
     * SCOPES
     */
    public function scopePresents(Builder $query): Builder
    {
        return $query->where('statut_scan', 'present');
    }

    public function scopeDeplaces(Builder $query): Builder
    {
        return $query->where('statut_scan', 'deplace');
    }

    public function scopeAbsents(Builder $query): Builder
    {
        return $query->where('statut_scan', 'absent');
    }

    public function scopeByLocalisation(Builder $query, int $localisationId): Builder
    {
        return $query->where('localisation_reelle_id', $localisationId);
    }

    /**
     * ACCESSORS
     */

    /**
     * Le statut est calculé à l'enregistrement depuis le référentiel figé.
     * Le relire évite qu'une modification ultérieure du bien change le résultat.
     */
    public function getIsConformeAttribute(): bool
    {
        return $this->statut_scan === 'present';
    }

    /**
     * Vérifie si le bien est déplacé ou absent
     */
    public function getIsDeformeAttribute(): bool
    {
        return in_array($this->statut_scan, ['deplace', 'absent']);
    }

    /**
     * Code affiché de l'immobilisation scannée
     */
    public function getCodeInventaireAttribute(): string
    {
        if ($bienAttendu = $this->bienAttenduCharge()) {
            return $bienAttendu->code_affiche;
        }

        if ($this->bien) {
            return $this->bien->code_formate;
        }

        return 'GS'.$this->immobilisation_id;
    }

    /**
     * Désignation de l'immobilisation scannée
     */
    public function getDesignationAttribute(): string
    {
        if ($bienAttendu = $this->bienAttenduCharge()) {
            return $bienAttendu->designation ?? 'N/A';
        }

        if ($this->bien && $this->bien->designation) {
            return $this->bien->libelle_article ?? 'N/A';
        }

        return 'N/A';
    }

    /**
     * Retourne le code localisation du bien
     */
    public function getLocalisationCodeAttribute(): ?string
    {
        if ($bienAttendu = $this->bienAttenduCharge()) {
            return $bienAttendu->localisation_code ?? $bienAttendu->localisation_nom;
        }

        return $this->bien?->site?->localisation?->libelle_court;
    }

    /**
     * Retourne la catégorie du bien
     */
    public function getCategorieAttribute(): ?string
    {
        if ($bienAttendu = $this->bienAttenduCharge()) {
            return $bienAttendu->categorie;
        }

        return $this->bien?->categorie?->categorie;
    }

    /**
     * Retrouve la fiche figée sans déclencher de requête implicite par scan.
     */
    private function bienAttenduCharge(): ?InventaireImmobilisationAttendue
    {
        if (! $this->relationLoaded('inventaire')
            || ! $this->inventaire?->hasFrozenReference()
            || ! $this->inventaire->relationLoaded('biensAttendus')) {
            return null;
        }

        return $this->inventaire->biensAttendus->first(
            fn (InventaireImmobilisationAttendue $bien): bool => (int) $bien->immobilisation_id === (int) $this->immobilisation_id,
        );
    }

    /**
     * Libellé de l'état constaté (3 états: Bon Etat, Neuf, Defectueux)
     */
    public function getEtatConstateLabelAttribute(): string
    {
        $labels = [
            'bon_etat' => 'Bon Etat',
            'neuf' => 'Neuf',
            'defectueux' => 'Defectueux',
        ];

        return $labels[$this->etat_constate ?? 'bon_etat'] ?? 'Bon Etat';
    }

    /**
     * URL publique de la photo
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', $this->photo_path), '/');

        return '/storage/'.$path;
    }
}
