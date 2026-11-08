<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventaireExportRequest extends Model
{
    protected $table = 'inventaire_exports';

    protected $fillable = [
        'inventaire_id',
        'requested_by',
        'format',
        'status',
        'disk',
        'path',
        'filename',
        'error_message',
        'finished_at',
        'expires_at',
        'automatic',
        'final_key',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
            'automatic' => 'boolean',
        ];
    }

    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'idUser');
    }
}
