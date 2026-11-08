<?php

namespace App\Models;

use App\Exceptions\InventaireWorkflowException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'annee',
        'date_debut',
        'date_fin',
        'statut',
        'created_by',
        'closed_by',
        'observation',
        'referentiel_frozen_at',
        'started_at',
        'finished_at',
        'closed_at',
    ];

    protected $casts = [
        'annee' => 'integer',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'referentiel_frozen_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Inventaire $inventaire): void {
            if ($inventaire->statut !== 'en_preparation' || $inventaire->inventaireScans()->exists()) {
                throw new InventaireWorkflowException('Seul un inventaire en préparation et sans scan peut être supprimé.');
            }
        });
    }

    /**
     * RELATIONS
     */

    /**
     * Relation avec l'utilisateur qui a créé l'inventaire
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation avec l'utilisateur qui a clôturé l'inventaire
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Relation avec les localisations inventoriées
     */
    public function inventaireLocalisations(): HasMany
    {
        return $this->hasMany(InventaireLocalisation::class);
    }

    /**
     * Relation avec les scans d'inventaire
     */
    public function inventaireScans(): HasMany
    {
        return $this->hasMany(InventaireScan::class);
    }

    /**
     * Référentiel figé des biens attendus au démarrage de l'inventaire.
     */
    public function biensAttendus(): HasMany
    {
        return $this->hasMany(InventaireImmobilisationAttendue::class);
    }

    public function hasFrozenReference(): bool
    {
        return $this->referentiel_frozen_at !== null;
    }

    /**
     * SCOPES
     */

    /**
     * Scope pour filtrer les inventaires en cours
     */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->where('statut', 'en_cours');
    }

    /**
     * Scope pour filtrer les inventaires terminés
     */
    public function scopeTermines(Builder $query): Builder
    {
        return $query->where('statut', 'termine');
    }

    /**
     * Scope pour filtrer par année
     */
    public function scopeByAnnee(Builder $query, int $annee): Builder
    {
        return $query->where('annee', $annee);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->canManageOperations()) {
            return $query;
        }

        return $query->whereHas(
            'inventaireLocalisations',
            fn (Builder $localisations) => $localisations->byAgent($user->idUser),
        );
    }

    public function isAccessibleTo(User $user): bool
    {
        return $user->canManageOperations()
            || $this->inventaireLocalisations()->byAgent($user->idUser)->exists();
    }

    /**
     * ACCESSORS
     */

    /**
     * Calcule le pourcentage de progression global
     */
    public function getProgressionAttribute(): float
    {
        $totalLocalisations = $this->inventaireLocalisations()->count();

        if ($totalLocalisations === 0) {
            return 0;
        }

        $localisationsTerminees = $this->inventaireLocalisations()
            ->where('statut', 'termine')
            ->count();

        return round(($localisationsTerminees / $totalLocalisations) * 100, 2);
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
     * Calcule la durée de l'inventaire en jours
     */
    public function getDureeAttribute(): ?int
    {
        $dateDebut = $this->started_at ?? $this->date_debut;
        if (! $dateDebut) {
            return null;
        }

        $dateFin = $this->finished_at ?? $this->date_fin ?? now();

        return (int) Carbon::parse($dateDebut)->diffInDays($dateFin);
    }

    /**
     * METHODS
     */

    /**
     * Démarre l'inventaire (passe le statut à 'en_cours')
     */
    public function demarrer(): bool
    {
        if ($this->statut !== 'en_preparation') {
            throw new InventaireWorkflowException('Seul un inventaire en préparation peut être démarré.');
        }

        return $this->update([
            'statut' => 'en_cours',
            'started_at' => now(),
        ]);
    }

    /**
     * Clôture l'inventaire sans écraser la date de fin opérationnelle.
     */
    public function cloturer(?int $closedBy = null): bool
    {
        if ($this->statut !== 'termine') {
            throw new InventaireWorkflowException('Seul un inventaire terminé peut être clôturé.');
        }

        if (! $closedBy) {
            throw new InventaireWorkflowException('L’administrateur qui clôture l’inventaire doit être précisé.');
        }

        return $this->update([
            'statut' => 'cloture',
            'closed_at' => now(),
            'closed_by' => $closedBy,
        ]);
    }

    /**
     * Retourne un tableau avec les statistiques complètes de l'inventaire
     */
    public function getStatistiques(): array
    {
        $localisationsParStatut = $this->inventaireLocalisations()
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $scans = $this->inventaireScans()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN statut_scan = 'present' THEN 1 ELSE 0 END) as presents")
            ->selectRaw("SUM(CASE WHEN statut_scan = 'deplace' THEN 1 ELSE 0 END) as deplaces")
            ->selectRaw("SUM(CASE WHEN statut_scan = 'absent' THEN 1 ELSE 0 END) as absents")
            ->selectRaw("SUM(CASE WHEN etat_constate = 'defectueux' AND statut_scan != 'absent' THEN 1 ELSE 0 END) as defectueux")
            ->first();

        $totalLocalisations = (int) $localisationsParStatut->sum();
        $localisationsTerminees = (int) ($localisationsParStatut['termine'] ?? 0);
        $totalScans = (int) ($scans?->total ?? 0);
        $scansPresents = (int) ($scans?->presents ?? 0);
        $scansDeplaces = (int) ($scans?->deplaces ?? 0);
        $scansAbsents = (int) ($scans?->absents ?? 0);
        $scansDeteriores = (int) ($scans?->defectueux ?? 0);

        return [
            'progression' => $totalLocalisations > 0
                ? round(($localisationsTerminees / $totalLocalisations) * 100, 2)
                : 0,
            'taux_conformite' => $totalScans > 0
                ? round(($scansPresents / $totalScans) * 100, 2)
                : 0,
            'duree' => $this->duree,
            'total_localisations' => $totalLocalisations,
            'localisations_terminees' => $localisationsTerminees,
            'total_scans' => $totalScans,
            'scans_presents' => $scansPresents,
            'scans_deplaces' => $scansDeplaces,
            'scans_absents' => $scansAbsents,
            'scans_deteriores' => $scansDeteriores,
        ];
    }
}
