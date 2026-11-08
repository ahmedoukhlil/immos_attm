<?php

namespace App\Models;

use App\Exceptions\InventaireWorkflowException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventaireLocalisation extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventaire_id',
        'localisation_id',
        'date_debut_scan',
        'date_fin_scan',
        'statut',
        'nombre_biens_attendus',
        'nombre_biens_scannes',
        'user_id',
    ];

    protected $casts = [
        'date_debut_scan' => 'datetime',
        'date_fin_scan' => 'datetime',
        'nombre_biens_attendus' => 'integer',
        'nombre_biens_scannes' => 'integer',
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

    public function localisation(): BelongsTo
    {
        return $this->belongsTo(Localisation::class);
    }

    /**
     * Relation avec l'agent assigné (legacy, premier agent ou dernier scanner)
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'idUser');
    }

    /**
     * Relation many-to-many avec les agents assignés
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inventaire_localisation_user', 'inventaire_localisation_id', 'user_id', 'id', 'idUser')
            ->withTimestamps();
    }

    /**
     * Relation avec les scans d'inventaire
     */
    public function inventaireScans(): HasMany
    {
        return $this->hasMany(InventaireScan::class);
    }

    public function biensAttendus(): HasMany
    {
        return $this->hasMany(InventaireImmobilisationAttendue::class);
    }

    public function inventaireSites(): HasMany
    {
        return $this->hasMany(InventaireSite::class);
    }

    /**
     * SCOPES
     */

    /**
     * Scope pour filtrer les inventaire_localisations en cours
     */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->where('statut', 'en_cours');
    }

    /**
     * Scope pour filtrer les inventaire_localisations terminées
     */
    public function scopeTerminees(Builder $query): Builder
    {
        return $query->where('statut', 'termine');
    }

    /**
     * Scope pour filtrer par agent (vérifie la table pivot ET le champ legacy user_id)
     */
    public function scopeByAgent(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereHas('agents', fn ($sub) => $sub->where('users.idUser', $userId));
        });
    }

    public function isAssignedTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $this->user_id === (int) $user->idUser) {
            return true;
        }

        if ($this->relationLoaded('agents')) {
            return $this->agents->contains(
                fn (User $agent): bool => (int) $agent->idUser === (int) $user->idUser,
            );
        }

        return $this->agents()->where('users.idUser', $user->idUser)->exists();
    }

    /**
     * ACCESSORS
     */

    /**
     * Calcule le pourcentage de progression
     */
    public function getProgressionAttribute(): float
    {
        if ($this->nombre_biens_attendus === 0) {
            return 0;
        }

        return round(($this->nombre_biens_scannes / $this->nombre_biens_attendus) * 100, 2);
    }

    /**
     * Calcule le taux de conformité (% de biens présents)
     */
    public function getTauxConformiteAttribute(): float
    {
        $totalScans = $this->inventaireScans()->count();

        if ($totalScans === 0) {
            return 0;
        }

        $scansPresents = $this->inventaireScans()
            ->where('statut_scan', 'present')
            ->count();

        return round(($scansPresents / $totalScans) * 100, 2);
    }

    /**
     * METHODS
     */

    /**
     * Démarre le scan de la localisation
     */
    public function demarrer(): bool
    {
        if ($this->statut !== 'en_attente') {
            throw new InventaireWorkflowException('Seule une localisation en attente peut être démarrée.');
        }

        return $this->update([
            'date_debut_scan' => now(),
            'statut' => 'en_cours',
        ]);
    }

    /**
     * Termine le scan de la localisation
     */
    public function terminer(): bool
    {
        if ($this->statut !== 'en_cours') {
            throw new InventaireWorkflowException('Seule une localisation en cours peut être terminée.');
        }

        return $this->update([
            'date_fin_scan' => now(),
            'statut' => 'termine',
        ]);
    }

    /**
     * Calcule et met à jour le nombre de biens attendus dans la localisation
     * Compte via la hiérarchie : Localisation → Sites → Immobilisations
     */
    public function calculerBiensAttendus(): int
    {
        if (! $this->localisation) {
            return 0;
        }

        $nombreBiens = $this->localisation->immobilisations()->count();

        $this->update(['nombre_biens_attendus' => $nombreBiens]);

        return $nombreBiens;
    }
}
