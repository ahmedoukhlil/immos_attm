<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    use HasFactory;

    protected $table = 'designations';

    public $timestamps = false;

    protected $fillable = ['designation', 'code', 'categorie_id', 'est_compose'];

    protected $casts = [
        'est_compose' => 'boolean',
    ];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function immobilisations(): HasMany
    {
        return $this->hasMany(Immobilisation::class);
    }

    public function composants(): HasMany
    {
        return $this->hasMany(DesignationComposant::class)->orderBy('ordre');
    }
}
