<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie d'immobilisation et son compte d'imputation (feuille « Code compta »).
 */
class Categorie extends Model
{
    use HasFactory;

    protected $table = 'categories';

    public $timestamps = false;

    protected $fillable = ['categorie', 'code', 'imputation_comptable'];

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    public function immobilisations(): HasMany
    {
        return $this->hasMany(Immobilisation::class);
    }
}
