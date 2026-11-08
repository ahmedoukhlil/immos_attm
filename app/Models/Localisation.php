<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Bâtiment ou lieu géographique regroupant des sites (ex. « Siège »).
 */
class Localisation extends Model
{
    use HasFactory;

    protected $table = 'localisations';

    public $timestamps = false;

    protected $fillable = ['localisation', 'code'];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function immobilisations(): HasManyThrough
    {
        return $this->hasManyThrough(Immobilisation::class, Site::class);
    }

    /**
     * Libellé court : le code s'il existe, sinon le nom.
     */
    public function getLibelleCourtAttribute(): string
    {
        return $this->code ?: $this->localisation;
    }
}
