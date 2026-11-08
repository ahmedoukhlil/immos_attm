<?php

namespace Tests\Feature;

use App\Livewire\Biens\ImportBiens;
use App\Livewire\Biens\FormBien;
use App\Models\User;
use App\Services\ModeleImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class ImportBiensTest extends TestCase
{
    use CreatesLaxSchema;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();

        $this->admin = User::create(['users' => 'admin-import', 'mdp' => 'MotDePasse123', 'role' => 'admin']);
        DB::table('localisations')->insert(['id' => 1, 'localisation' => 'Siège', 'code' => 'SIEGE']);
        DB::table('sites')->insert(['id' => 1, 'localisation_id' => 1, 'site' => 'Salle serveur', 'responsable' => null]);
        DB::table('categories')->insert(['id' => 1, 'categorie' => 'Mobilier de bureau', 'code' => 'MMOB', 'imputation_comptable' => '21800000']);
        DB::table('designations')->insert(['id' => 1, 'designation' => 'Bureau', 'code' => 'BUR', 'categorie_id' => 1]);
        foreach (['NEUF' => 'Neuf', 'BON' => 'Bon', 'MOY' => 'Moyen', 'MAUV' => 'Mauvais', 'HS' => 'Hors service'] as $code => $etat) {
            DB::table('etats')->insert(['code' => $code, 'etat' => $etat]);
        }
    }

    public function test_the_template_lists_the_expected_columns_and_references(): void
    {
        $chemin = app(ModeleImportService::class)->generer();
        $classeur = IOFactory::load($chemin);
        @unlink($chemin);

        $this->assertSame(ModeleImportService::COLONNES, $classeur->getSheetByName('Immo')->rangeToArray('A1:R1')[0]);
        $this->assertSame('Salle serveur', $classeur->getSheetByName('Listes')->getCell('B2')->getValue());
    }

    public function test_an_admin_can_download_the_template(): void
    {
        $this->actingAs($this->admin)
            ->get('/biens/import/modele')
            ->assertOk()
            ->assertDownload('modele_import_immobilisations.xlsx');
    }

    public function test_manual_creation_persists_identification_fields(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FormBien::class)
            ->set('localisation_id', '1')
            ->set('site_id', '1')
            ->set('categorie_id', '1')
            ->set('designation_id', '1')
            ->set('etat_id', '1')
            ->set('annee_acquisition', '2026')
            ->set('quantite', 1)
            ->set('marque', 'Toyota')
            ->set('modele', 'Hilux')
            ->set('numero_chassis', 'MR0ABCD1234567890')
            ->set('numero_attm', 'ATTM-2026-001')
            ->set('code_immo', 'IMMO-2026-001')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('immobilisations', [
            'marque' => 'Toyota',
            'modele' => 'Hilux',
            'numero_chassis' => 'MR0ABCD1234567890',
            'numero_attm' => 'ATTM-2026-001',
            'code_immo' => 'IMMO-2026-001',
        ]);
    }

    public function test_analysis_proposes_missing_references_and_import_creates_only_the_checked_ones(): void
    {
        $fichier = $this->modeleRempli([
            ['Siège', 'Salle serveur', 'Vedel', 'Mobilier de bureau', '21800000', 'Bureau', 'Bois', 'Exemple', 'M-100', 'CH-001', 'ATTM-001', 'IMMO-001', 'Bon', 2024, 2],
            ['Siège', 'Bureau DG', 'Fatimetou', 'Mobilier de bureau', '21800000', 'Armoire', 'Métal', '', '', '', '', '', 'Neuf', '', 1],
            ['Annexe', 'Magasin', '', 'Matériel informatique', '21600000', 'Imprimante', '', '', '', '', '', '', 'Mauvais', '', 1],
            ['', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', ''],
        ]);

        $composant = Livewire::actingAs($this->admin)
            ->test(ImportBiens::class)
            ->set('fichier', $fichier)
            ->call('analyser')
            ->assertHasNoErrors();

        $analyse = $composant->get('analyse');
        $this->assertSame(4, $analyse['compteurs']['biens_crees']);
        $this->assertSame(3, $analyse['compteurs']['lignes_lues']);
        $this->assertSame(0, $analyse['compteurs']['lignes_ignorees']);
        $this->assertSame(['Annexe'], array_column($analyse['propositions']['localisations'], 'localisation'));
        $this->assertEqualsCanonicalizing(['Bureau DG', 'Magasin'], array_column($analyse['propositions']['sites'], 'site'));
        $this->assertSame('Vedel', $analyse['propositions']['responsables'][0]['nouveau']);
        $this->assertSame(['21600000'], array_column($analyse['propositions']['categories'], 'imputation_comptable'));
        $this->assertEqualsCanonicalizing(['Armoire', 'Imprimante'], array_column($analyse['propositions']['designations'], 'designation'));
        $this->assertDatabaseCount('immobilisations', 0);

        // Refuse la nouvelle localisation « Annexe » : sa ligne est ignorée.
        $indexAnnexe = array_search('Annexe', array_column($analyse['propositions']['localisations'], 'localisation'), true);
        $composant->set("choix.localisations.{$indexAnnexe}", false)
            ->call('importer')
            ->assertHasNoErrors()
            ->assertDispatched('referentiels-importes');

        $resultat = $composant->get('resultat');
        $this->assertSame(3, $resultat['compteurs']['biens_crees']);
        $this->assertDatabaseCount('immobilisations', 3);
        $this->assertDatabaseMissing('localisations', ['localisation' => 'Annexe']);
        $this->assertDatabaseHas('sites', ['site' => 'Bureau DG', 'responsable' => 'Fatimetou', 'localisation_id' => 1]);
        $this->assertDatabaseHas('sites', ['id' => 1, 'responsable' => 'Vedel']);
        $this->assertDatabaseHas('designations', ['designation' => 'Armoire']);
        $this->assertDatabaseHas('immobilisations', ['code_propose' => '218/BUR/002/2024']);
        $this->assertDatabaseHas('immobilisations', [
            'marque' => 'Exemple',
            'modele' => 'M-100',
            'numero_chassis' => 'CH-001',
            'numero_attm' => 'ATTM-001',
            'code_immo' => 'IMMO-001',
        ]);

        // Réimporter le même fichier ne crée pas de doublons : seule la ligne refusée reste à importer.
        $reanalyse = Livewire::actingAs($this->admin)
            ->test(ImportBiens::class)
            ->set('fichier', $fichier)
            ->call('analyser')
            ->get('analyse');
        $this->assertSame(3, $reanalyse['compteurs']['deja_importees']);
        $this->assertSame(1, $reanalyse['compteurs']['biens_crees']);
    }

    public function test_the_creation_form_refreshes_its_reference_selects_after_an_import(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FormBien::class)
            ->assertSet('referencesVersion', 0)
            ->dispatch('referentiels-importes')
            ->assertSet('referencesVersion', 1);
    }

    public function test_only_the_checked_immobilisations_are_created(): void
    {
        $fichier = $this->modeleRempli([
            ['Siège', 'Salle serveur', '', 'Mobilier de bureau', '21800000', 'Bureau', 'Bois', '', '', '', '', '', 'Bon', 2024, 1],
            ['Siège', 'Salle serveur', '', 'Mobilier de bureau', '21800000', 'Scanneur', 'Epson', '', '', '', '', '', 'Bon', 2024, 3],
        ]);

        $composant = Livewire::actingAs($this->admin)
            ->test(ImportBiens::class)
            ->set('fichier', $fichier)
            ->call('analyser');

        $immobilisations = $composant->get('analyse')['immobilisations'];
        $this->assertSame([2, 3], array_column($immobilisations, 'ligne'));
        $this->assertSame('218/BUR/001/2024', $immobilisations[0]['codes'][0]);
        $this->assertSame(3, $immobilisations[1]['quantite']);
        $this->assertSame([true, true], $composant->get('retenues'));

        // Tout décoché : rien n'est importé.
        $composant->call('toutCocher', false)->call('importer')->assertHasErrors('retenues');
        $this->assertDatabaseCount('immobilisations', 0);

        // Seul le bureau est retenu : la désignation « Scanneur » n'est pas créée.
        $composant->set('retenues.0', true)->call('importer')->assertHasNoErrors();

        $this->assertDatabaseCount('immobilisations', 1);
        $this->assertDatabaseMissing('designations', ['designation' => 'Scanneur']);
        $this->assertSame(1, $composant->get('resultat')['compteurs']['biens_crees']);

        // Nouvelle analyse : le bureau apparaît comme déjà importé (non cochable), le scanneur reste à créer.
        $reanalyse = Livewire::actingAs($this->admin)
            ->test(ImportBiens::class)
            ->set('fichier', $fichier)
            ->call('analyser');

        [$bureau, $scanneur] = $reanalyse->get('analyse')['immobilisations'];
        $this->assertSame([0, 1, ['218/BUR/001/2024']], [$bureau['quantite'], $bureau['deja_importees'], $bureau['codes_existants']]);
        $this->assertSame(3, $scanneur['quantite']);
        $this->assertSame([false, true], $reanalyse->get('retenues'));
        $reanalyse->assertSee('Déjà importée');
    }

    private function modeleRempli(array $lignes): UploadedFile
    {
        $classeur = new Spreadsheet;
        $classeur->getActiveSheet()->setTitle('Immo')->fromArray([ModeleImportService::COLONNES, ...$lignes]);
        $chemin = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        $contenu = file_get_contents($chemin);
        @unlink($chemin);

        return UploadedFile::fake()->createWithContent('immobilisations.xlsx', $contenu);
    }
}
