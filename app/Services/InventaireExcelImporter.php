<?php

namespace App\Services;

use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Etat;
use App\Models\Immobilisation;
use App\Models\Localisation;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Importe un fichier Excel d'immobilisations : le fichier « Inventaire Immo »
 * ou le modèle téléchargeable depuis l'application (une ligne = une immobilisation,
 * ou plusieurs selon la quantité).
 *
 * Tout s'exécute dans une transaction : en simulation, elle est annulée à la fin,
 * ce qui donne exactement les mêmes chiffres qu'un import réel sans rien écrire.
 * La simulation liste aussi les référentiels à créer (« propositions ») ; l'import
 * réel peut être limité aux créations autorisées.
 */
class InventaireExcelImporter
{
    public const CODE_LOCALISATION = 'SIEGE';

    /**
     * Types de référentiels dont la création peut être proposée.
     */
    public const TYPES_PROPOSITIONS = ['localisations', 'sites', 'responsables', 'categories', 'designations'];

    /**
     * Préfixe d'en-tête normalisé => clé interne.
     */
    private const COLONNES = [
        'localisation' => 'localisation',
        'site' => 'site',
        'categorie' => 'categorie',
        'imputation' => 'imputation',
        'designation' => 'designation',
        'descriptif' => 'descriptif',
        'marque' => 'marque',
        'modele' => 'modele',
        'numero de chassis' => 'numero_chassis',
        'numero attm' => 'numero_attm',
        'code immo' => 'code_immo',
        'code dernier' => 'code_dernier',
        'code premier' => 'code_premier',
        'responsable' => 'responsable',
        'etat' => 'etat',
        'code propose' => 'code_propose',
        'annee' => 'annee',
        'quantite' => 'quantite',
        'autres' => 'autres',
    ];

    private const COLONNES_OBLIGATOIRES = ['site', 'categorie', 'designation', 'etat'];

    /**
     * Catégorie Excel normalisée => code de catégorie, utilisé si l'imputation est absente.
     */
    private const CATEGORIES_PAR_LIBELLE = [
        'materiel de bureau' => 'MMOB',
        'mobilier de bureau' => 'MMOB',
        'materiel informatique' => 'MINF',
        'materiel d exploitation' => 'MEXP',
        'materiel de transport' => 'MTRP',
        'materiels de transport' => 'MTRP',
    ];

    private const SITE_VIDE = 'Non renseigné';

    private const QUANTITE_MAX = 1000;

    private array $rapport;

    /** Créations autorisées par type (null : tout est autorisé). */
    private ?array $autorisations = null;

    /** Lignes Excel retenues par l'utilisateur (null : toutes). */
    private ?array $lignesRetenues = null;

    private Collection $categoriesParCompte;

    private Collection $categoriesParCode;

    private Collection $categoriesParLibelle;

    private Collection $etatsParCode;

    private Collection $designations;

    private Collection $localisations;

    private Collection $sites;

    /** Références d'import déjà en base => code de l'immobilisation. */
    private array $refsDejaImportees = [];

    private Localisation $localisationParDefaut;

    /** Référentiels créés pendant cet import, par type et par clé. */
    private array $crees = [];

    /**
     * @param  array<string, list<string>>|null  $autorisations  clés autorisées par type de proposition
     * @param  string|null  $source  préfixe des références d'import (nom du fichier par défaut)
     * @param  list<int>|null  $lignesRetenues  numéros des lignes Excel à importer (null : toutes)
     * @return array{simulation: bool, compteurs: array<string, int>, etats: array<string, int>, categories: array<string, int>, propositions: array<string, array<string, array>>, anomalies: list<array{ligne: int, type: string, message: string}>}
     */
    public function importer(
        string $chemin,
        bool $simulation,
        string $feuille = 'Immo',
        ?array $autorisations = null,
        ?string $source = null,
        ?array $lignesRetenues = null,
    ): array {
        $this->lignesRetenues = $lignesRetenues === null ? null : array_flip(array_map('intval', $lignesRetenues));
        $this->autorisations = $autorisations === null
            ? null
            : array_map(fn ($cles) => array_flip((array) $cles), $autorisations + array_fill_keys(self::TYPES_PROPOSITIONS, []));
        $this->crees = [];
        $this->rapport = [
            'simulation' => $simulation,
            'compteurs' => [
                'lignes_lues' => 0,
                'lignes_ignorees' => 0,
                'deja_importees' => 0,
                'biens_crees' => 0,
                'sites_crees' => 0,
                'designations_creees' => 0,
            ],
            'etats' => [],
            'categories' => [],
            'propositions' => array_fill_keys(self::TYPES_PROPOSITIONS, []),
            'immobilisations' => [],
            'anomalies' => [],
        ];

        [$lignes, $nomFichier] = $this->lireFeuille($chemin, $feuille);
        $source ??= $nomFichier;
        [$nomsSites, $responsables] = $this->analyserSites($lignes);

        DB::beginTransaction();

        try {
            $this->chargerReferentiels($source);

            foreach ($lignes as $numeroLigne => $ligne) {
                $this->importerLigne($numeroLigne, $ligne, $source, $nomsSites, $responsables);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $simulation ? DB::rollBack() : DB::commit();

        return $this->rapport;
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function lireFeuille(string $chemin, string $feuille): array
    {
        if (! is_file($chemin)) {
            throw new RuntimeException("Fichier introuvable : {$chemin}");
        }

        try {
            $lecteur = IOFactory::createReaderForFile($chemin);
            $lecteur->setReadDataOnly(true);
            $classeur = $lecteur->load($chemin);
        } catch (Throwable) {
            throw new RuntimeException('Le fichier n\'est pas un classeur Excel lisible.');
        }

        $onglet = $classeur->getSheetByName($feuille) ?? $classeur->getSheet(0);
        $brut = $onglet->toArray(null, true, false, false);

        $entetes = array_shift($brut) ?? [];
        $indexColonnes = [];

        foreach ($entetes as $index => $entete) {
            $cle = $this->normaliser((string) $entete);

            foreach (self::COLONNES as $prefixe => $colonne) {
                if (! isset($indexColonnes[$colonne]) && str_starts_with($cle, $prefixe)) {
                    $indexColonnes[$colonne] = $index;
                    break;
                }
            }
        }

        $manquantes = array_diff(self::COLONNES_OBLIGATOIRES, array_keys($indexColonnes));

        if ($manquantes !== []) {
            throw new RuntimeException('Colonnes obligatoires absentes de la feuille « '.$onglet->getTitle().' » : '.implode(', ', $manquantes));
        }

        $lignes = [];

        foreach ($brut as $index => $cellules) {
            $ligne = [];

            foreach (self::COLONNES as $colonne) {
                $valeur = isset($indexColonnes[$colonne]) ? ($cellules[$indexColonnes[$colonne]] ?? null) : null;
                $ligne[$colonne] = $valeur === null ? '' : Str::squish((string) $valeur);
            }

            // Numéro de ligne Excel : l'en-tête occupe la ligne 1.
            $lignes[$index + 2] = $ligne;
        }

        return [$lignes, basename($chemin)];
    }

    /**
     * Regroupe les variantes d'un même site (casse, espaces) d'une même localisation
     * et retient, par site, le libellé et le responsable les plus fréquents.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function analyserSites(array $lignes): array
    {
        $libelles = [];
        $responsables = [];

        foreach ($lignes as $numeroLigne => $ligne) {
            if ($this->estVide($ligne)) {
                continue;
            }

            $site = $ligne['site'] !== '' ? $ligne['site'] : self::SITE_VIDE;
            $cle = $this->cleSite($ligne);
            $libelles[$cle][$site] = ($libelles[$cle][$site] ?? 0) + 1;

            if ($ligne['responsable'] !== '') {
                $cleResponsable = preg_replace('/[^a-z]/', '', $this->normaliser($ligne['responsable']));
                $responsables[$cle][$cleResponsable]['libelles'][$ligne['responsable']] = true;
                $responsables[$cle][$cleResponsable]['total'] = ($responsables[$cle][$cleResponsable]['total'] ?? 0) + 1;
                $responsables[$cle][$cleResponsable]['ligne'] ??= $numeroLigne;
            }
        }

        $nomsSites = array_map(fn (array $variantes) => $this->plusFrequent($variantes), $libelles);
        $responsableParSite = [];

        foreach ($responsables as $cleSite => $candidats) {
            uasort($candidats, fn ($a, $b) => $b['total'] <=> $a['total']);
            $retenu = reset($candidats);
            $responsableParSite[$cleSite] = array_key_first($retenu['libelles']);

            if (count($candidats) > 1) {
                $autres = collect($candidats)->skip(1)->flatMap(fn ($c) => array_keys($c['libelles']))->implode(' ; ');
                $this->anomalie($retenu['ligne'], 'responsable_multiple', "Site « {$nomsSites[$cleSite]} » : plusieurs responsables, « {$responsableParSite[$cleSite]} » retenu (ignorés : {$autres}).");
            }
        }

        return [$nomsSites, $responsableParSite];
    }

    private function chargerReferentiels(string $source): void
    {
        $categories = Categorie::all();
        $this->categoriesParCompte = $categories->whereNotNull('imputation_comptable')->keyBy('imputation_comptable');
        $this->categoriesParCode = $categories->keyBy('code');
        $this->categoriesParLibelle = $categories->keyBy(fn (Categorie $c) => $this->normaliser($c->categorie));
        $this->etatsParCode = Etat::all()->keyBy(fn (Etat $etat) => strtoupper((string) $etat->code));

        foreach (['BON', 'MOY', 'MAUV', 'HS', 'NEUF'] as $code) {
            if (! $this->etatsParCode->has($code)) {
                throw new RuntimeException("État {$code} introuvable. Lancez EtatSeeder.");
            }
        }

        $this->localisationParDefaut = Localisation::firstOrCreate(
            ['code' => self::CODE_LOCALISATION],
            ['localisation' => 'Siège'],
        );

        $this->localisations = collect();
        foreach (Localisation::all() as $localisation) {
            $this->localisations->put($this->normaliser($localisation->localisation), $localisation);

            if ($localisation->code) {
                $this->localisations->put($this->normaliser($localisation->code), $localisation);
            }
        }

        $this->designations = Designation::all()->keyBy(fn (Designation $d) => $this->normaliser($d->designation));
        $this->sites = Site::all()->keyBy(fn (Site $s) => $s->localisation_id.'|'.$this->normaliser($s->site));
        $this->refsDejaImportees = Immobilisation::withTrashed()->withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE)->where('import_ref', 'like', $source.'#%')
            ->get(['id', 'code_propose', 'import_ref'])
            ->mapWithKeys(fn (Immobilisation $i) => [$i->import_ref => $i->code_formate])
            ->all();
    }

    private function importerLigne(int $numeroLigne, array $ligne, string $source, array $nomsSites, array $responsables): void
    {
        // Lignes vides (fin de tableau, lignes laissées par la mise en forme) : ni lues ni ignorées.
        if ($this->estVide($ligne)) {
            return;
        }

        $this->rapport['compteurs']['lignes_lues']++;

        // Ligne décochée par l'utilisateur : ni l'immobilisation ni ses référentiels ne sont créés.
        if ($this->lignesRetenues !== null && ! isset($this->lignesRetenues[$numeroLigne])) {
            $this->rapport['compteurs']['lignes_ignorees']++;

            return;
        }

        if ($ligne['designation'] === '') {
            $this->ignorer($numeroLigne, 'designation_absente', 'Désignation vide : ligne ignorée.');

            return;
        }

        $categorie = $this->resoudreCategorie($numeroLigne, $ligne);
        $localisation = $this->resoudreLocalisation($numeroLigne, $ligne['localisation']);
        $cleSite = $this->cleSite($ligne);
        $site = $localisation
            ? $this->resoudreSite($numeroLigne, $localisation, $cleSite, $nomsSites[$cleSite], $responsables[$cleSite] ?? null)
            : null;
        $designation = $categorie ? $this->resoudreDesignation($numeroLigne, $ligne['designation'], $categorie) : null;

        if (! $categorie || ! $localisation || ! $site || ! $designation) {
            $this->rapport['compteurs']['lignes_ignorees']++;

            return;
        }

        $etat = $this->resoudreEtat($numeroLigne, $ligne['etat']);
        [$quantite, $observations, $anneeAcquisition] = $this->analyserCodePropose($numeroLigne, $ligne['code_propose']);
        $quantite = $this->resoudreQuantite($numeroLigne, $ligne['quantite'], $quantite);
        $anneeAcquisition = $this->resoudreAnnee($numeroLigne, $ligne['annee']) ?? $anneeAcquisition;

        if ($ligne['autres'] !== '') {
            $observations[] = $ligne['autres'];
        }

        $descriptif = $ligne['descriptif'] !== '' ? $ligne['descriptif'] : null;

        if ($descriptif !== null && mb_strlen($descriptif) > 255) {
            $this->anomalie($numeroLigne, 'descriptif_tronque', 'Descriptif tronqué à 255 caractères.');
            $descriptif = mb_substr($descriptif, 0, 255);
        }

        $codes = [];
        $codesExistants = [];

        for ($rang = 1; $rang <= $quantite; $rang++) {
            $importRef = "{$source}#L{$numeroLigne}#{$rang}";

            if (isset($this->refsDejaImportees[$importRef])) {
                $this->rapport['compteurs']['deja_importees']++;
                $codesExistants[] = $this->refsDejaImportees[$importRef];

                continue;
            }

            // Le numéro d'article et le code d'inventaire sont attribués par le modèle.
            $codes[] = Immobilisation::create([
                'site_id' => $site->id,
                'categorie_id' => $categorie->id,
                'designation_id' => $designation->id,
                'etat_id' => $etat->id,
                'annee_acquisition' => $anneeAcquisition,
                'descriptif' => $descriptif,
                'marque' => $this->limiter($numeroLigne, 'marque', $ligne['marque'], 100),
                'modele' => $this->limiter($numeroLigne, 'modele', $ligne['modele'], 100),
                'numero_chassis' => $this->limiter($numeroLigne, 'numero_chassis', $ligne['numero_chassis'], 100),
                'numero_attm' => $this->limiter($numeroLigne, 'numero_attm', $ligne['numero_attm'], 100),
                'code_immo' => $this->limiter($numeroLigne, 'code_immo', $ligne['code_immo'], 100),
                'code_premier_inventaire' => $this->codeAncien($numeroLigne, $ligne['code_premier']),
                'code_dernier_inventaire' => $this->codeAncien($numeroLigne, $ligne['code_dernier']),
                'autres_informations' => $observations !== [] ? implode(' | ', $observations) : null,
                'import_ref' => $importRef,
            ])->code_formate;

            $this->rapport['compteurs']['biens_crees']++;
            $this->rapport['etats'][$etat->etat] = ($this->rapport['etats'][$etat->etat] ?? 0) + 1;
            $this->rapport['categories'][$categorie->categorie] = ($this->rapport['categories'][$categorie->categorie] ?? 0) + 1;
        }

        // Lignes à créer, mais aussi déjà importées : l'utilisateur voit tout le contenu du fichier.
        if ($codes !== [] || $codesExistants !== []) {
            $this->rapport['immobilisations'][] = [
                'ligne' => $numeroLigne,
                'designation' => $designation->designation,
                'descriptif' => $descriptif,
                'site' => $site->site,
                'localisation' => $localisation->localisation,
                'etat' => $etat->etat,
                'annee' => $anneeAcquisition,
                'quantite' => count($codes),
                'codes' => $codes,
                'deja_importees' => count($codesExistants),
                'codes_existants' => $codesExistants,
            ];
        }
    }

    private function resoudreCategorie(int $numeroLigne, array $ligne): ?Categorie
    {
        $compte = preg_replace('/\D/', '', $ligne['imputation']);
        $parCompte = $compte !== '' ? $this->categoriesParCompte->get($compte) : null;
        $codeParLibelle = self::CATEGORIES_PAR_LIBELLE[$this->normaliser($ligne['categorie'])] ?? null;
        $parLibelle = ($codeParLibelle ? $this->categoriesParCode->get($codeParLibelle) : null)
            ?? $this->categoriesParLibelle->get($this->normaliser($ligne['categorie']));

        if ($parCompte && $parLibelle && $parCompte->isNot($parLibelle)) {
            $this->anomalie($numeroLigne, 'categorie_incoherente', "Imputation {$compte} et catégorie « {$ligne['categorie']} » divergent : l'imputation est retenue.");
        }

        if ($categorie = $parCompte ?? $parLibelle) {
            $this->compterLigne('categories', (string) $categorie->imputation_comptable);

            return $categorie;
        }

        // Catégorie inconnue : création proposée si l'imputation et le libellé sont renseignés.
        if (strlen($compte) !== 8 || $ligne['categorie'] === '') {
            $this->anomalie($numeroLigne, 'categorie_inconnue', "Catégorie « {$ligne['categorie']} » / imputation « {$ligne['imputation']} » non reconnue : ligne ignorée (une nouvelle catégorie exige un libellé et une imputation à 8 chiffres).");

            return null;
        }

        $libelle = Str::ucfirst($ligne['categorie']);
        $this->proposer('categories', $compte, ['categorie' => $libelle, 'imputation_comptable' => $compte]);

        if (! $this->autorise('categories', $compte)) {
            $this->anomalie($numeroLigne, 'creation_refusee', "Catégorie « {$libelle} » ({$compte}) non créée : ligne ignorée.");

            return null;
        }

        $base = strtoupper(substr(preg_replace('/[^a-z]/', '', $this->normaliser($libelle)), 0, 4)) ?: 'CAT';
        $code = $base;

        for ($suffixe = 2; $this->categoriesParCode->has($code); $suffixe++) {
            $code = $base.$suffixe;
        }

        $categorie = Categorie::create(['categorie' => $libelle, 'code' => $code, 'imputation_comptable' => $compte]);
        $this->categoriesParCompte->put($compte, $categorie);
        $this->categoriesParCode->put($code, $categorie);
        $this->categoriesParLibelle->put($this->normaliser($libelle), $categorie);

        return $categorie;
    }

    private function resoudreLocalisation(int $numeroLigne, string $nom): ?Localisation
    {
        if ($nom === '') {
            return $this->localisationParDefaut;
        }

        $cle = $this->normaliser($nom);

        if ($localisation = $this->localisations->get($cle)) {
            $this->compterLigne('localisations', $cle);

            return $localisation;
        }

        $this->proposer('localisations', $cle, ['localisation' => $nom]);

        if (! $this->autorise('localisations', $cle)) {
            $this->anomalie($numeroLigne, 'creation_refusee', "Localisation « {$nom} » non créée : ligne ignorée.");

            return null;
        }

        $localisation = Localisation::create(['localisation' => mb_substr($nom, 0, 150)]);
        $this->localisations->put($cle, $localisation);

        return $localisation;
    }

    private function resoudreEtat(int $numeroLigne, string $valeur): Etat
    {
        $cle = preg_replace('/[^a-z]/', '', $this->normaliser($valeur));

        $code = match (true) {
            $cle === '' => null,
            str_starts_with($cle, 'neu') => 'NEUF',
            str_starts_with($cle, 'bon') => 'BON',
            str_starts_with($cle, 'mauv') => 'MAUV',
            str_starts_with($cle, 'mo'), str_starts_with($cle, 'my') => 'MOY',
            $cle === 'hs', str_starts_with($cle, 'hors') => 'HS',
            default => null,
        };

        if ($code === null) {
            $this->anomalie($numeroLigne, 'etat_inconnu', 'État « '.($valeur !== '' ? $valeur : 'vide').' » non reconnu : « Moyen » retenu.');
            $code = 'MOY';
        }

        return $this->etatsParCode->get($code);
    }

    /**
     * La colonne « Code proposé » du fichier d'origine mélange quantités (« QTé:2 »),
     * ancienneté (« Neuf », « Ancienne »), années d'acquisition et anciens codes.
     * Le code d'inventaire, lui, est toujours généré par l'application.
     *
     * @return array{0: int, 1: list<string>, 2: ?int}
     */
    private function analyserCodePropose(int $numeroLigne, string $valeur): array
    {
        $cle = $this->normaliser($valeur);

        if ($valeur === '') {
            return [1, [], null];
        }

        if (preg_match('/^qte\s*(\d+)$/', $cle, $correspondance)) {
            return [max(1, (int) $correspondance[1]), [], null];
        }

        if (preg_match('/^(neuf|neuve|ancien|ancienne)$/', $cle, $correspondance)) {
            $origine = in_array($correspondance[1], ['neuf', 'neuve'], true) ? 'Neuf' : 'Ancien';

            return [1, ["Origine : {$origine}"], null];
        }

        if (preg_match('/^\d{4}$/', $valeur) && (int) $valeur >= 1970 && (int) $valeur <= (int) date('Y')) {
            return [1, [], (int) $valeur];
        }

        if (preg_match('#^\d{3}[/-][A-Z]{3}[/-]\d{3,4}[/-]\d{4}#', $valeur)) {
            // Code au format généré : il sera recalculé par l'application.
            return [1, [], null];
        }

        $this->anomalie($numeroLigne, 'code_propose_inconnu', "Code proposé « {$valeur} » non reconnu : conservé en observation.");

        return [1, ["Code proposé : {$valeur}"], null];
    }

    private function resoudreQuantite(int $numeroLigne, string $valeur, int $parDefaut): int
    {
        if ($valeur === '') {
            return $parDefaut;
        }

        $quantite = (int) preg_replace('/\D/', '', $valeur);

        if ($quantite < 1 || $quantite > self::QUANTITE_MAX) {
            $this->anomalie($numeroLigne, 'quantite_invalide', "Quantité « {$valeur} » invalide : 1 retenu (maximum ".self::QUANTITE_MAX.').');

            return 1;
        }

        return $quantite;
    }

    private function resoudreAnnee(int $numeroLigne, string $valeur): ?int
    {
        if ($valeur === '') {
            return null;
        }

        $annee = (int) $valeur;

        if ($annee < 1950 || $annee > (int) date('Y') + 1) {
            $this->anomalie($numeroLigne, 'annee_invalide', "Année « {$valeur} » invalide : année en cours retenue.");

            return null;
        }

        return $annee;
    }

    private function resoudreDesignation(int $numeroLigne, string $libelle, Categorie $categorie): ?Designation
    {
        $cle = $this->normaliser($libelle);

        if ($designation = $this->designations->get($cle)) {
            $this->compterLigne('designations', $cle);

            return $designation;
        }

        $libelle = Str::ucfirst($libelle);
        $this->proposer('designations', $cle, ['designation' => $libelle, 'categorie' => $categorie->categorie]);

        if (! $this->autorise('designations', $cle)) {
            $this->anomalie($numeroLigne, 'creation_refusee', "Désignation « {$libelle} » non créée : ligne ignorée.");

            return null;
        }

        $base = strtoupper(substr(preg_replace('/[^a-z]/', '', $cle), 0, 3)) ?: 'DES';
        $code = $base;
        $codesExistants = $this->designations->pluck('code')->flip();

        for ($suffixe = 2; $codesExistants->has($code); $suffixe++) {
            $code = $base.$suffixe;
        }

        $designation = Designation::create([
            'designation' => $libelle,
            'code' => $code,
            'categorie_id' => $categorie->id,
        ]);

        $this->designations->put($cle, $designation);
        $this->rapport['compteurs']['designations_creees']++;

        return $designation;
    }

    private function resoudreSite(int $numeroLigne, Localisation $localisation, string $cleSite, string $nomSite, ?string $responsable): ?Site
    {
        $cle = $localisation->id.'|'.$this->normaliser($nomSite);
        $site = $this->sites->get($cle);
        $responsable = $responsable !== null ? mb_substr($responsable, 0, 150) : null;

        if (! $site) {
            $this->proposer('sites', $cleSite, [
                'site' => $nomSite,
                'localisation' => $localisation->localisation,
                'responsable' => $responsable,
            ]);

            if (! $this->autorise('sites', $cleSite)) {
                $this->anomalie($numeroLigne, 'creation_refusee', "Site « {$nomSite} » non créé : ligne ignorée.");

                return null;
            }

            $site = Site::create([
                'localisation_id' => $localisation->id,
                'site' => mb_substr($nomSite, 0, 150),
                'code' => $this->prochainCodeSite($localisation),
                'responsable' => $responsable,
            ]);

            $this->sites->put($cle, $site);
            $this->rapport['compteurs']['sites_crees']++;

            return $site;
        }

        $this->compterLigne('sites', $cleSite);

        if ($responsable === null || $this->normaliser((string) $site->responsable) === $this->normaliser($responsable)) {
            return $site;
        }

        // Site existant sans responsable ou avec un autre responsable : mise à jour proposée.
        $this->proposer('responsables', $cleSite, [
            'site' => $site->site,
            'actuel' => $site->responsable,
            'nouveau' => $responsable,
        ]);

        $autorise = $this->autorisations === null
            ? $site->responsable === null
            : $this->autorise('responsables', $cleSite);

        if ($autorise) {
            $site->update(['responsable' => $responsable]);
        } elseif ($site->responsable !== null && $this->autorisations === null) {
            $this->anomalie($numeroLigne, 'responsable_different', "Site « {$nomSite} » : responsable existant « {$site->responsable} » conservé (fichier : « {$responsable} »).");
        }

        return $site;
    }

    private function prochainCodeSite(Localisation $localisation): string
    {
        $prefixe = $localisation->code ?: strtoupper(substr(preg_replace('/[^a-z]/', '', $this->normaliser($localisation->localisation)), 0, 5)) ?: 'SITE';
        $numero = 1 + (int) Site::query()
            ->where('code', 'like', $prefixe.'-%')
            ->pluck('code')
            ->map(fn ($code) => (int) Str::afterLast($code, '-'))
            ->max();

        return sprintf('%s-%03d', $prefixe, $numero);
    }

    private function codeAncien(int $numeroLigne, string $valeur): ?string
    {
        if ($valeur === '') {
            return null;
        }

        if (mb_strlen($valeur) > 40) {
            $this->anomalie($numeroLigne, 'code_ancien_tronque', "Ancien code « {$valeur} » tronqué à 40 caractères.");
        }

        return mb_substr($valeur, 0, 40);
    }

    private function limiter(int $numeroLigne, string $champ, string $valeur, int $longueur): ?string
    {
        if ($valeur === '') {
            return null;
        }

        if (mb_strlen($valeur) > $longueur) {
            $this->anomalie(
                $numeroLigne,
                $champ.'_tronque',
                ucfirst(str_replace('_', ' ', $champ))." tronqué à {$longueur} caractères.",
            );
        }

        return mb_substr($valeur, 0, $longueur);
    }

    /**
     * Clé de regroupement d'un site : localisation et nom normalisés.
     */
    private function cleSite(array $ligne): string
    {
        $site = $ligne['site'] !== '' ? $ligne['site'] : self::SITE_VIDE;

        return $this->normaliser($ligne['localisation'] ?? '').'|'.$this->normaliser($site);
    }

    /**
     * Enregistre une création proposée et compte les lignes qui en dépendent.
     */
    private function proposer(string $type, string $cle, array $details): void
    {
        $proposition = $this->rapport['propositions'][$type][$cle] ?? $details + ['lignes' => 0];
        $proposition['lignes']++;
        $this->rapport['propositions'][$type][$cle] = $proposition;
        $this->crees[$type][$cle] = true;
    }

    /**
     * Une ligne qui réutilise un référentiel créé par cet import compte dans sa proposition.
     */
    private function compterLigne(string $type, string $cle): void
    {
        if (isset($this->crees[$type][$cle])) {
            $this->rapport['propositions'][$type][$cle]['lignes']++;
        }
    }

    private function autorise(string $type, string $cle): bool
    {
        return $this->autorisations === null || isset($this->autorisations[$type][$cle]);
    }

    private function ignorer(int $numeroLigne, string $type, string $message): void
    {
        $this->anomalie($numeroLigne, $type, $message);
        $this->rapport['compteurs']['lignes_ignorees']++;
    }

    private function estVide(array $ligne): bool
    {
        return implode('', $ligne) === '';
    }

    /**
     * Clé de comparaison : minuscules, sans accents ni ponctuation.
     */
    private function normaliser(string $valeur): string
    {
        return Str::of($valeur)
            ->replace(['’', '‘'], "'")
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();
    }

    private function plusFrequent(array $occurrences): string
    {
        arsort($occurrences);

        return (string) array_key_first($occurrences);
    }

    private function anomalie(int $ligne, string $type, string $message): void
    {
        $this->rapport['anomalies'][] = compact('ligne', 'type', 'message');
    }
}
