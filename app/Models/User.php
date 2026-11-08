<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Nom de la table
     */
    protected $table = 'users';

    /**
     * Clé primaire personnalisée
     */
    protected $primaryKey = 'idUser';

    /**
     * Désactiver les timestamps
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'users',  // Nom d'utilisateur (selon immos.md)
        'mdp',    // Mot de passe (selon immos.md)
        'role',
    ];

    /**
     * Nom de la colonne pour l'authentification (utilisé par Laravel)
     * Note: getAuthIdentifierName() doit retourner le nom de la colonne utilisée pour identifier l'utilisateur
     * mais Auth::id() retourne toujours la valeur de la clé primaire
     */
    public function getAuthIdentifierName()
    {
        return $this->primaryKey; // Retourner 'idUser' pour que Auth::id() fonctionne correctement
    }

    /**
     * Récupérer l'identifiant pour l'authentification (retourne la clé primaire)
     */
    public function getAuthIdentifier()
    {
        return $this->getAttribute($this->primaryKey); // Retourner idUser
    }

    /**
     * Récupérer le mot de passe pour l'authentification
     */
    public function getAuthPassword()
    {
        return $this->getAttribute('mdp');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'mdp',  // Mot de passe (selon immos.md)
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mdp' => 'hashed',
        ];
    }

    /**
     * RELATIONS
     */

    /**
     * Relation avec les inventaires créés par l'utilisateur
     */
    public function inventairesCreated(): HasMany
    {
        return $this->hasMany(Inventaire::class, 'created_by');
    }

    /**
     * Relation avec les inventaires clôturés par l'utilisateur
     */
    public function inventairesClosed(): HasMany
    {
        return $this->hasMany(Inventaire::class, 'closed_by');
    }

    /**
     * Relation avec les scans d'inventaire effectués par l'utilisateur
     */
    public function inventaireScans(): HasMany
    {
        return $this->hasMany(InventaireScan::class, 'user_id');
    }

    /**
     * Relation avec les inventaire_localisations assignées à l'utilisateur
     */
    public function inventaireLocalisations(): HasMany
    {
        return $this->hasMany(InventaireLocalisation::class, 'user_id');
    }

    /**
     * SCOPES
     */

    /**
     * Scope pour filtrer les administrateurs
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope pour filtrer les agents
     */
    public function scopeAgents(Builder $query): Builder
    {
        return $query->where('role', 'agent');
    }

    public function scopeGestionnaires(Builder $query): Builder
    {
        return $query->where('role', 'gestionnaire');
    }

    /**
     * Scope pour filtrer par rôle
     */
    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * ACCESSORS
     */

    /**
     * Retourne le nom du rôle en français
     */
    public function getRoleNameAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrateur',
            'gestionnaire' => 'Gestionnaire',
            'agent' => 'Agent',
            default => 'Non défini',
        };
    }

    /**
     * METHODS
     */

    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifie si l'utilisateur est agent
     */
    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isGestionnaire(): bool
    {
        return $this->role === 'gestionnaire';
    }

    /**
     * Vérifie une action métier depuis la matrice centralisée des rôles.
     */
    public function canPerform(string $ability): bool
    {
        $abilities = config('roles.abilities', []);

        return in_array($this->role, $abilities[$ability] ?? [], true);
    }

    /**
     * Vérifie si l'utilisateur peut gérer les inventaires
     * Les administrateurs préparent et clôturent les inventaires. Les agents
     * exécutent uniquement les opérations terrain qui leur sont affectées.
     */
    public function canManageInventaire(): bool
    {
        return $this->canPerform('inventaires.view');
    }

    public function canManageOperations(): bool
    {
        return $this->canPerform('operations.manage');
    }

    /**
     * Trouve un utilisateur par son nom d'utilisateur
     */
    public static function findByUsername(string $username): ?self
    {
        return static::query()->where('users', $username)->first();
    }
}
