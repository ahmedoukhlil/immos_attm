<?php

namespace Tests\Feature;

use App\Models\Etat;
use App\Services\InventaireExcelImporter;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class InventaireExcelImportTest extends TestCase
{
    use CreatesLaxSchema;

    private string $fichier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();

        DB::table('categories')->insert([
            ['categorie' => 'Mobilier de bureau', 'code' => 'MMOB', 'imputation_comptable' => '21800000'],
            ['categorie' => 'Matériel informatique', 'code' => 'MINF', 'imputation_comptable' => '21600000'],
        ]);
        foreach (['NEUF' => 'Neuf', 'BON' => 'Bon', 'MOY' => 'Moyen', 'MAUV' => 'Mauvais', 'HS' => 'Hors service'] as $code => $etat) {
            DB::table('etats')->insert(['code' => $code, 'etat' => $etat]);
        }

        $this->fichier = $this->creerClasseur([
            ['Chef Service Informatique ', 'Matériel de Bureau', 21800000, 'Bureau ', ' En bois rouge ', 'Inv – 16 -216-A492', 'E0685', 'Sid’El Moctar', 'Moyen', '218/BUR/001/2026', null],
            ['chef service informatique', 'Matériel de bureau', '21800000', 'Chaise visiteur', 'En cuir noir', null, null, 'SID’EL MOCTAR', 'H. S', 'QTé:3', null],
            ['Salle serveur  ', 'Matériel informatique', '21600000', 'Imprimante', 'Hp laser', null, null, 'Vedel', 'Bonne ', 'Neuve', null],
            [null, 'Matériel informatique', '21600000', 'Imprimante', 'Hp laser', null, null, null, 'Mauvaise', '2015', 'Écran fissuré'],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->fichier);

        parent::tearDown();
    }

    public function test_simulation_reports_the_import_without_writing_anything(): void
    {
        $rapport = app(InventaireExcelImporter::class)->importer($this->fichier, simulation: true);

        $this->assertSame(6, $rapport['compteurs']['biens_crees']);
        $this->assertSame(3, $rapport['compteurs']['sites_crees']);
        $this->assertDatabaseCount('immobilisations', 0);
        $this->assertDatabaseCount('sites', 0);
        $this->assertDatabaseCount('localisations', 0);
    }

    public function test_import_fills_the_excel_columns_and_can_be_replayed_safely(): void
    {
        $this->artisan('immos:import-excel', ['fichier' => $this->fichier, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('immobilisations', 6);
        $this->assertDatabaseHas('localisations', ['code' => 'SIEGE', 'localisation' => 'Siège']);
        // Les variantes de casse et d'espaces d'un site donnent un seul site.
        $this->assertDatabaseHas('sites', [
            'site' => 'Chef Service Informatique',
            'responsable' => 'Sid’El Moctar',
        ]);
        $this->assertDatabaseHas('sites', ['site' => 'Non renseigné']);
        $this->assertSame(3, DB::table('sites')->count());

        $bureau = DB::table('immobilisations')->where('code_propose', '218/BUR/001/2026')->first();
        $this->assertSame('En bois rouge', $bureau->descriptif);
        $this->assertSame('E0685', $bureau->code_premier_inventaire);
        $this->assertSame('Inv – 16 -216-A492', $bureau->code_dernier_inventaire);
        $this->assertSame(Etat::where('code', 'MOY')->value('id'), $bureau->etat_id);

        // « QTé:3 » crée trois exemplaires hors service.
        $this->assertSame(3, DB::table('immobilisations')
            ->where('etat_id', Etat::where('code', 'HS')->value('id'))
            ->count());
        $this->assertDatabaseHas('immobilisations', ['autres_informations' => 'Origine : Neuf']);
        $this->assertDatabaseHas('immobilisations', [
            'annee_acquisition' => 2015,
            'autres_informations' => 'Écran fissuré',
        ]);

        $rapport = app(InventaireExcelImporter::class)->importer($this->fichier, simulation: false);

        $this->assertSame(0, $rapport['compteurs']['biens_crees']);
        $this->assertSame(6, $rapport['compteurs']['deja_importees']);
        $this->assertDatabaseCount('immobilisations', 6);
    }

    public function test_reference_states_map_to_scan_states(): void
    {
        $constates = Etat::all()->mapWithKeys(fn (Etat $etat) => [$etat->code => $etat->etatConstate()]);

        $this->assertSame([
            'NEUF' => 'neuf',
            'BON' => 'bon_etat',
            'MOY' => 'bon_etat',
            'MAUV' => 'defectueux',
            'HS' => 'defectueux',
        ], $constates->all());
    }

    private function creerClasseur(array $lignes): string
    {
        $classeur = new Spreadsheet;
        $feuille = $classeur->getActiveSheet()->setTitle('Immo');
        $feuille->fromArray([
            ['Site', 'Catégorie', 'Imputation comptable', "Désignation de l'immobilisation", "Descriptifs de l'immobilisation", 'Code dernier inventaire', 'Code premier inventaire', 'Responsable', "État de l'immo", 'Code proposé', 'Autres informations'],
            ...$lignes,
        ]);

        $chemin = tempnam(sys_get_temp_dir(), 'immo').'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        return $chemin;
    }
}
