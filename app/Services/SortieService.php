<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Immobilisation;
use App\Models\Inventaire;
use App\Models\InventaireScan;
use App\Models\Sortie;
use App\Models\SortieImmobilisation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Report de l'inventaire et sorties d'immobilisations.
 *
 * Les biens absents ou constatés hors d'usage sont proposés à la sortie ;
 * l'administrateur les regroupe dans une décision par motif, qui ne retire
 * les biens du registre actif qu'au moment de sa validation.
 */
class SortieService
{
    /**
     * Biens d'un inventaire clôturé qui relèvent d'une décision de sortie.
     *
     * @return Collection<int, array{bien: Immobilisation, constat: string, motif_propose: string, sortie_brouillon: ?Sortie}>
     */
    public function candidats(Inventaire $inventaire): Collection
    {
        $scans = InventaireScan::query()
            ->where('inventaire_id', $inventaire->id)
            ->where(fn ($query) => $query
                ->whereIn('statut_scan', ['absent', 'deteriore'])
                ->orWhere('etat_constate', 'defectueux'))
            ->with(['bien.designation', 'bien.categorie', 'bien.site.localisation', 'bien.etat'])
            ->orderBy('immobilisation_id')
            ->get()
            ->filter(fn (InventaireScan $scan): bool => $scan->bien !== null && $scan->bien->sortie_id === null);

        // Un bien noté absent dans son site mais retrouvé ailleurs n'est pas manquant.
        $retrouves = InventaireScan::query()
            ->where('inventaire_id', $inventaire->id)
            ->where('statut_scan', '!=', 'absent')
            ->whereIn('immobilisation_id', $scans->pluck('immobilisation_id')->unique())
            ->pluck('immobilisation_id')
            ->flip();

        $brouillons = $this->sortiesBrouillonParBien($scans->pluck('immobilisation_id')->unique()->all());

        return $scans
            ->reject(fn (InventaireScan $scan): bool => $scan->statut_scan === 'absent' && $retrouves->has($scan->immobilisation_id))
            ->unique('immobilisation_id')
            ->map(function (InventaireScan $scan) use ($brouillons): array {
                $constat = $this->constat($scan->bien, $scan->statut_scan === 'absent');

                return [
                    'bien' => $scan->bien,
                    'constat' => $constat,
                    'motif_propose' => $constat === 'absent' ? 'perte' : 'reforme',
                    'sortie_brouillon' => $brouillons->get($scan->immobilisation_id),
                ];
            })
            ->values();
    }

    /**
     * Inscrit des biens dans le brouillon du motif (créé au besoin) et le renvoie.
     *
     * @param  array<int, int|string>  $bienIds
     */
    public function proposer(array $bienIds, string $motif, User $user, ?Inventaire $inventaire = null): Sortie
    {
        $this->verifierMotif($motif);

        return DB::transaction(function () use ($bienIds, $motif, $user, $inventaire): Sortie {
            $sortie = Sortie::query()
                ->where('statut', 'brouillon')
                ->where('motif', $motif)
                ->where('inventaire_id', $inventaire?->id)
                ->lockForUpdate()
                ->first()
                ?? Sortie::create([
                    'numero' => $this->prochainNumero(),
                    'motif' => $motif,
                    'inventaire_id' => $inventaire?->id,
                    'statut' => 'brouillon',
                    'created_by' => $user->idUser,
                ]);

            $this->ajouterBiens($sortie, $bienIds, $inventaire);

            return $sortie->refresh();
        });
    }

    /**
     * @param  array<int, int|string>  $bienIds
     */
    public function ajouterBiens(Sortie $sortie, array $bienIds, ?Inventaire $inventaire = null): int
    {
        $this->verifierBrouillon($sortie);

        $biens = Immobilisation::query()
            ->with(['designation', 'categorie', 'site.localisation', 'etat'])
            ->whereIn('id', $bienIds)
            ->get();

        if ($biens->isEmpty()) {
            throw ValidationException::withMessages(['biens' => 'Aucune immobilisation en service ne correspond à cette sélection.']);
        }

        $dejaInscrits = $this->sortiesBrouillonParBien($biens->pluck('id')->all());
        $absents = $inventaire
            ? InventaireScan::query()
                ->where('inventaire_id', $inventaire->id)
                ->whereIn('immobilisation_id', $biens->pluck('id'))
                ->get()
                ->groupBy('immobilisation_id')
                ->filter(fn (Collection $scans): bool => $scans->every(fn (InventaireScan $scan): bool => $scan->statut_scan === 'absent'))
            : collect();

        $ajouts = 0;

        foreach ($biens as $bien) {
            $autre = $dejaInscrits->get($bien->id);

            if ($autre && $autre->id !== $sortie->id) {
                throw ValidationException::withMessages([
                    'biens' => "{$bien->code_formate} figure déjà dans la décision {$autre->numero}.",
                ]);
            }

            if ($autre) {
                continue;
            }

            SortieImmobilisation::create([
                'sortie_id' => $sortie->id,
                'immobilisation_id' => $bien->id,
                'code' => $bien->code_formate,
                'designation' => $bien->libelle_article,
                'categorie' => $bien->categorie?->categorie,
                'site' => $bien->site?->site,
                'localisation' => $bien->site?->localisation?->localisation,
                'etat' => $bien->etat?->etat,
                'constat' => $inventaire ? $this->constat($bien, $absents->has($bien->id)) : 'manuel',
            ]);
            $ajouts++;
        }

        return $ajouts;
    }

    /**
     * Retrouve un bien en service par son code d'inventaire, son identifiant de scan (GS…) ou son numéro.
     */
    public function trouverBien(string $code): ?Immobilisation
    {
        $code = trim($code);
        $identifiant = preg_replace('/^GS/i', '', $code);

        return Immobilisation::query()
            ->where(function ($query) use ($code, $identifiant): void {
                if (ctype_digit($identifiant)) {
                    $query->where('id', (int) $identifiant);
                }

                $query->orWhere('code_propose', $code)
                    ->orWhere('code_dernier_inventaire', $code)
                    ->orWhere('code_premier_inventaire', $code);
            })
            ->first();
    }

    public function retirerBien(Sortie $sortie, int $ligneId): void
    {
        $this->verifierBrouillon($sortie);

        $sortie->lignes()->whereKey($ligneId)->delete();
    }

    /**
     * Applique la décision au registre : chaque bien prend le statut « sorti ».
     */
    public function valider(Sortie $sortie, User $user): void
    {
        DB::transaction(function () use ($sortie, $user): void {
            $sortie = Sortie::query()->lockForUpdate()->findOrFail($sortie->id);
            $this->verifierBrouillon($sortie);

            $erreurs = [];
            if (! $sortie->date_decision) {
                $erreurs['date_decision'] = 'Renseignez la date de la décision avant de la valider.';
            }
            if (! $sortie->lignes()->exists()) {
                $erreurs['biens'] = 'La décision ne contient aucun bien.';
            }
            if (Inventaire::query()->where('statut', 'en_cours')->exists()) {
                $erreurs['statut'] = 'Un inventaire est en cours : validez la sortie après sa clôture pour ne pas fausser les comptages.';
            }
            if ($erreurs !== []) {
                throw ValidationException::withMessages($erreurs);
            }

            $biens = Immobilisation::query()
                ->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE)
                ->whereIn('id', $sortie->lignes()->pluck('immobilisation_id'))
                ->lockForUpdate()
                ->get();

            $dejaSortis = $biens->filter(fn (Immobilisation $bien): bool => $bien->sortie_id !== null);
            if ($dejaSortis->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'biens' => 'Déjà sortis par une autre décision : '.$dejaSortis->map->code_formate->implode(', ').'.',
                ]);
            }

            foreach ($biens as $bien) {
                $bien->forceFill([
                    'sortie_id' => $sortie->id,
                    'date_sortie' => $sortie->date_decision,
                ])->save();
            }

            $sortie->update([
                'statut' => 'validee',
                'validee_par' => $user->idUser,
                'validee_at' => now(),
            ]);

            $this->auditer('sortie_validee', $sortie, $user, [
                'numero' => $sortie->numero,
                'motif' => $sortie->motif,
                'biens' => $biens->count(),
            ]);
        });
    }

    public function annuler(Sortie $sortie, User $user): void
    {
        $this->verifierBrouillon($sortie);

        $sortie->update(['statut' => 'annulee']);
        $this->auditer('sortie_annulee', $sortie, $user, ['numero' => $sortie->numero]);
    }

    public function prochainNumero(): string
    {
        $prefixe = 'SORT-'.now()->year.'-';
        $dernier = Sortie::query()
            ->where('numero', 'like', $prefixe.'%')
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) Str::after($numero, $prefixe))
            ->max() ?? 0;

        return $prefixe.str_pad((string) ($dernier + 1), 3, '0', STR_PAD_LEFT);
    }

    private function constat(Immobilisation $bien, bool $absent): string
    {
        if ($absent) {
            return 'absent';
        }

        return strtoupper((string) $bien->etat?->code) === 'HS' ? 'hors_service' : 'mauvais';
    }

    /**
     * @param  array<int, int>  $bienIds
     * @return Collection<int, Sortie> brouillon indexé par identifiant de bien
     */
    private function sortiesBrouillonParBien(array $bienIds): Collection
    {
        return SortieImmobilisation::query()
            ->whereIn('immobilisation_id', $bienIds)
            ->whereHas('sortie', fn ($query) => $query->where('statut', 'brouillon'))
            ->with('sortie')
            ->get()
            ->mapWithKeys(fn (SortieImmobilisation $ligne): array => [$ligne->immobilisation_id => $ligne->sortie]);
    }

    private function verifierMotif(string $motif): void
    {
        if (! array_key_exists($motif, Sortie::MOTIFS)) {
            throw ValidationException::withMessages(['motif' => 'Motif de sortie inconnu.']);
        }
    }

    private function verifierBrouillon(Sortie $sortie): void
    {
        if (! $sortie->estBrouillon()) {
            throw ValidationException::withMessages(['statut' => 'Cette décision est '.mb_strtolower($sortie->statut_libelle).' et ne peut plus être modifiée.']);
        }
    }

    private function auditer(string $event, Sortie $sortie, User $user, array $valeurs): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $request = app()->runningInConsole() ? null : request();

        AuditLog::query()->create([
            'event' => $event,
            'auditable_type' => Sortie::class,
            'auditable_id' => (string) $sortie->id,
            'actor_id' => $user->idUser,
            'new_values' => $valeurs,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 500, '') : null,
        ]);
    }
}
