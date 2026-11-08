<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Code-barres généré pour une immobilisation.
 */
class Code extends Model
{
    use HasFactory;

    protected $table = 'codes';

    public $timestamps = false;

    protected $fillable = ['immobilisation_id', 'barcode'];

    public function immobilisation(): BelongsTo
    {
        return $this->belongsTo(Immobilisation::class)->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE);
    }
}
