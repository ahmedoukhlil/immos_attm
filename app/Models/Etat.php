<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * État d'une immobilisation : Neuf, Bon, Moyen, Mauvais, Hors service.
 */
class Etat extends Model
{
    use HasFactory;

    protected $table = 'etats';

    public $timestamps = false;

    protected $fillable = ['etat', 'code'];

    public function immobilisations(): HasMany
    {
        return $this->hasMany(Immobilisation::class);
    }

    /**
     * Traduit l'état de référence vers l'enum etat_constate des scans
     * (bon_etat, neuf, defectueux). Un bien mauvais ou hors service est défectueux.
     */
    public function etatConstate(): string
    {
        $parCode = [
            'NEUF' => 'neuf',
            'BON' => 'bon_etat',
            'MOY' => 'bon_etat',
            'MAUV' => 'defectueux',
            'HS' => 'defectueux',
        ];
        $code = strtoupper(trim((string) $this->code));

        if (isset($parCode[$code])) {
            return $parCode[$code];
        }

        $libelle = Str::of((string) $this->etat)->lower()->ascii()->squish()->toString();

        return match (true) {
            str_starts_with($libelle, 'neu') => 'neuf',
            str_starts_with($libelle, 'mauvais'),
            str_starts_with($libelle, 'defectueu'),
            str_starts_with($libelle, 'hors') => 'defectueux',
            default => 'bon_etat',
        };
    }
}
