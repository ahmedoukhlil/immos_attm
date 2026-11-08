<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Immobilisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImmobilisationObserver
{
    private const AUDITED_FIELDS = [
        'id',
        'site_id',
        'categorie_id',
        'designation_id',
        'numero_article',
        'etat_id',
        'descriptif',
        'marque',
        'modele',
        'numero_chassis',
        'numero_attm',
        'code_immo',
        'code_dernier_inventaire',
        'code_premier_inventaire',
        'code_propose',
        'autres_informations',
        'sortie_id',
        'date_sortie',
    ];

    public function created(Immobilisation $bien): void
    {
        $this->record('created', $bien, [], Arr::only($bien->getAttributes(), self::AUDITED_FIELDS));
    }

    public function updated(Immobilisation $bien): void
    {
        $changes = Arr::only($bien->getChanges(), self::AUDITED_FIELDS);

        if ($changes === []) {
            return;
        }

        $this->record(
            'updated',
            $bien,
            Arr::only($bien->getOriginal(), array_keys($changes)),
            $changes,
        );
    }

    public function deleted(Immobilisation $bien): void
    {
        $this->record('archived', $bien, [], ['deleted_at' => $bien->deleted_at]);
    }

    public function restored(Immobilisation $bien): void
    {
        $this->record('restored', $bien, ['deleted_at' => $bien->getOriginal('deleted_at')], []);
    }

    /**
     * L'absence temporaire de la table est tolérée pendant un déploiement roulant.
     */
    private function record(
        string $event,
        Immobilisation $bien,
        array $oldValues,
        array $newValues,
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $request = app()->runningInConsole() ? null : request();

        AuditLog::query()->create([
            'event' => $event,
            'auditable_type' => Immobilisation::class,
            'auditable_id' => (string) $bien->getKey(),
            'actor_id' => auth()->id(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent()
                ? Str::limit($request->userAgent(), 500, '')
                : null,
        ]);
    }
}
