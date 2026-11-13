<?php

namespace App\Models;

use App\Services\BarcodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Une immobilisation, avec les colonnes du fichier « Inventaire Immo » :
 * site, catégorie (et son imputation), désignation, descriptif, état,
 * codes des inventaires précédents, code proposé et autres informations.
 */
class Immobilisation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Portée globale qui écarte les biens sortis (réforme, cession, perte…) :
     * listes, inventaires et étiquettes ne voient que les biens en service.
     */
    public const PORTEE_EN_SERVICE = 'en_service';

    protected $table = 'immobilisations';

    protected $fillable = [
        'site_id',
        'categorie_id',
        'designation_id',
        'numero_article',
        'annee_acquisition',
        'etat_id',
        'descriptif',
        'marque',
        'modele',
        'numero_chassis',
        'numero_attm',
        'code_immo',
        'photo_path',
        'code_dernier_inventaire',
        'code_premier_inventaire',
        'code_propose',
        'autres_informations',
        'import_ref',
    ];

    protected $casts = [
        'numero_article' => 'integer',
        'annee_acquisition' => 'integer',
        'date_sortie' => 'date',
    ];

    /**
     * Chaque immobilisation reçoit le numéro suivant de sa désignation
     * (Ordinateur 1, 2, 3…), y compris lorsqu'elle change de désignation.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(self::PORTEE_EN_SERVICE, fn (Builder $query) => $query->whereNull($query->qualifyColumn('sortie_id')));

        static::creating(function (Immobilisation $bien): void {
            if ($bien->numero_article === null && $bien->designation_id !== null) {
                $bien->numero_article = static::prochainNumeroArticle($bien->designation_id);
            }

            $bien->annee_acquisition ??= (int) now()->year;
            $bien->code_propose = $bien->calculerCode();
        });

        static::updating(function (Immobilisation $bien): void {
            if ($bien->isDirty('designation_id') && ! $bien->isDirty('numero_article')) {
                $bien->numero_article = static::prochainNumeroArticle($bien->designation_id);
            }

            if ($bien->isDirty(['designation_id', 'categorie_id', 'numero_article', 'annee_acquisition'])) {
                $bien->code_propose = $bien->calculerCode();
            }
        });
    }

    /**
     * Code d'inventaire : 3 premiers chiffres de l'imputation comptable / code de la désignation /
     * numéro d'article sur 3 chiffres / année d'acquisition (ex. 218/BUR/001/2026).
     */
    public static function formaterCode(
        ?string $imputation,
        ?string $codeDesignation,
        ?string $designation,
        ?int $numeroArticle,
        ?int $annee,
    ): ?string {
        if (! $numeroArticle || ! $annee) {
            return null;
        }

        $compte = substr(preg_replace('/\D/', '', (string) $imputation), 0, 3) ?: '000';
        $code = $codeDesignation
            ?: strtoupper(substr(preg_replace('/[^A-Za-z]/', '', Str::ascii((string) $designation)), 0, 3))
            ?: 'XXX';

        return sprintf('%s/%s/%03d/%d', $compte, strtoupper($code), $numeroArticle, $annee);
    }

    public function calculerCode(): ?string
    {
        $designation = $this->designation_id ? Designation::find($this->designation_id) : null;

        return static::formaterCode(
            $this->categorie_id ? Categorie::whereKey($this->categorie_id)->value('imputation_comptable') : null,
            $designation?->code,
            $designation?->designation,
            $this->numero_article,
            $this->annee_acquisition,
        );
    }

    /**
     * Recalcule les codes après un changement d'imputation comptable ou de code de désignation.
     */
    public static function recalculerCodes(callable $filtre): void
    {
        $filtre(static::withTrashed()->withoutGlobalScope(self::PORTEE_EN_SERVICE)->with(['designation', 'categorie']))
            ->chunkById(200, function ($biens): void {
                foreach ($biens as $bien) {
                    $code = static::formaterCode(
                        $bien->categorie?->imputation_comptable,
                        $bien->designation?->code,
                        $bien->designation?->designation,
                        $bien->numero_article,
                        $bien->annee_acquisition,
                    );

                    if ($code !== $bien->code_propose) {
                        $bien->code_propose = $code;
                        $bien->saveQuietly();
                    }
                }
            });
    }

    /**
     * Les numéros des immobilisations archivées ne sont jamais réattribués.
     */
    public static function prochainNumeroArticle(int $designationId): int
    {
        return 1 + (int) static::withTrashed()
            ->withoutGlobalScope(self::PORTEE_EN_SERVICE)
            ->where('designation_id', $designationId)
            ->lockForUpdate()
            ->max('numero_article');
    }

    /**
     * La fiche d'un bien sorti reste consultable par son URL.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withoutGlobalScope(self::PORTEE_EN_SERVICE), $value, $field);
    }

    public function sortie(): BelongsTo
    {
        return $this->belongsTo(Sortie::class);
    }

    public function estSorti(): bool
    {
        return $this->sortie_id !== null;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function etat(): BelongsTo
    {
        return $this->belongsTo(Etat::class);
    }

    /**
     * Code-barres mis en cache.
     */
    public function code(): HasOne
    {
        return $this->hasOne(Code::class);
    }

    public function etiquetteImpressions(): HasMany
    {
        return $this->hasMany(EtiquetteImpression::class);
    }

    /**
     * Mouvements successifs de l'immobilisation, du plus récent au plus ancien.
     */
    public function historiqueTransferts(): HasMany
    {
        return $this->hasMany(HistoriqueTransfert::class)
            ->orderByDesc('date_transfert')
            ->orderByDesc('id');
    }

    /**
     * Libellé de l'article : désignation suivie de son numéro (ex. « Ordinateur 3 »).
     */
    public function getLibelleArticleAttribute(): string
    {
        $designation = $this->designation?->designation ?? 'Immobilisation';

        return $this->numero_article ? "{$designation} {$this->numero_article}" : $designation;
    }

    /**
     * Code affiché : le code proposé s'il existe, sinon l'identifiant de scan.
     */
    public function getCodeFormateAttribute(): string
    {
        return $this->code_propose ?: $this->scan_code;
    }

    /**
     * Contenu du QR code de l'étiquette : « code d'inventaire | GS<id> ».
     * Le code d'inventaire reste lisible par n'importe quel lecteur ; le GS<id>,
     * immuable, identifie le bien même si son code est recalculé ensuite.
     */
    public static function contenuQr(?string $codeInventaire, string $codeScan): string
    {
        return $codeInventaire && $codeInventaire !== $codeScan
            ? "{$codeInventaire} | {$codeScan}"
            : $codeScan;
    }

    public function getContenuQrAttribute(): string
    {
        return static::contenuQr($this->code_propose, $this->scan_code);
    }

    /**
     * Identifiant immuable encodé dans les codes-barres et QR codes.
     */
    public function getScanCodeAttribute(): string
    {
        return 'GS'.$this->id;
    }

    /**
     * URL publique de la photo d'identification du bien.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $this->photo_path), '/');
    }

    /**
     * Génère et sauvegarde le code-barres Code 128 pour cette immobilisation.
     */
    public function generateBarcode(): string
    {
        return app(BarcodeService::class)->generateForImmobilisation($this);
    }
}
