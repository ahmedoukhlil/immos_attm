<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Immobilisation;
use App\Models\Site;
use App\Models\User;
use App\Services\EtiquetteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class EtiquetteQrPdfTest extends TestCase
{
    use CreatesLaxSchema;

    public function test_a_dynamic_qr_label_sheet_renders_as_a_pdf(): void
    {
        $bien = new Immobilisation;
        $bien->id = 154;
        $bien->setRelation('designation', new Designation(['designation' => 'Ordinateur de bureau']));

        $site = new Site(['site' => 'Bureau 12']);
        $configuration = (new EtiquetteService)->configurationImpression([
            'page_format' => 'a4_portrait',
            'label_width' => 70,
            'label_height' => 30,
            'page_margin' => 10,
            'gap_x' => 2,
            'gap_y' => 2,
            'start_position' => 5,
            'background_color' => '#EEF2FF',
        ]);

        $qr = base64_encode(
            QrCode::format('svg')->size(320)->margin(1)->generate('GS154')
        );

        $etiquette = [
            'bien' => $bien,
            'nom_composant' => null,
            'code' => 'GS154',
            'qr_base64' => $qr,
        ];

        $pdf = Pdf::loadView('pdf.etiquettes-site', [
            'site' => $site,
            'etiquettes' => collect(array_fill(0, 17, $etiquette)),
            'configuration' => $configuration,
        ])->setPaper([0, 0, 210 * 2.834645669, 297 * 2.834645669]);

        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertSame(2, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_admin_can_download_the_site_qr_codes_pdf(): void
    {
        $this->createLaxSchema();
        DB::table('localisations')->insert(['id' => 1, 'localisation' => 'Siège', 'code' => 'LOC-1']);
        DB::table('sites')->insert([
            ['id' => 1, 'site' => 'Bureau 1', 'code' => 'EMP-1', 'localisation_id' => 1, 'responsable' => 'A. Diallo'],
            ['id' => 2, 'site' => 'Bureau 2', 'code' => 'EMP-2', 'localisation_id' => 1, 'responsable' => null],
            ['id' => 3, 'site' => 'Bureau 3', 'code' => 'EMP-3', 'localisation_id' => 1, 'responsable' => null],
        ]);
        DB::table('immobilisations')->insert([['id' => 101, 'site_id' => 1], ['id' => 102, 'site_id' => 1]]);
        $admin = User::create(['users' => 'admin-qr', 'mdp' => 'MotDePasse123', 'role' => 'admin']);

        $reponse = $this->actingAs($admin)->get('/qrcodes/sites/pdf');

        $reponse->assertOk();
        $this->assertStringStartsWith('%PDF-', $reponse->getContent());
    }

    public function test_new_labels_mode_only_returns_assets_added_after_the_previous_generation(): void
    {
        $this->createLaxSchema();
        DB::table('localisations')->insert(['id' => 1, 'localisation' => 'Dépôt', 'code' => 'DEP']);
        DB::table('sites')->insert(['id' => 1, 'site' => 'Magasin', 'code' => 'MAG', 'localisation_id' => 1]);
        DB::table('categories')->insert(['id' => 1, 'categorie' => 'Matériel', 'code' => 'MAT']);
        DB::table('designations')->insert(['id' => 1, 'designation' => 'Armoire', 'code' => 'ARM', 'categorie_id' => 1]);
        DB::table('etats')->insert(['id' => 1, 'etat' => 'Bon', 'code' => 'BON']);
        $admin = User::create(['users' => 'admin-nouvelles-etiquettes', 'mdp' => 'MotDePasse123', 'role' => 'admin']);

        $premierBien = Immobilisation::create([
            'site_id' => 1,
            'categorie_id' => 1,
            'designation_id' => 1,
            'etat_id' => 1,
            'annee_acquisition' => 2026,
        ]);
        $site = Site::findOrFail(1);
        $service = app(EtiquetteService::class);
        $service->tracerGeneration(
            $service->etiquettesPourSite($site),
            $site,
            (int) $admin->getAuthIdentifier(),
            'complete',
        );

        $nouveauBien = Immobilisation::create([
            'site_id' => 1,
            'categorie_id' => 1,
            'designation_id' => 1,
            'etat_id' => 1,
            'annee_acquisition' => 2026,
        ]);

        $nouvelles = $service->etiquettesPourSite($site, 'nouvelles');

        $this->assertSame([$nouveauBien->id], $nouvelles->pluck('bien.id')->all());
        $this->assertNotContains($premierBien->id, $nouvelles->pluck('bien.id')->all());
    }

    public function test_asset_page_shows_the_real_qr_label_and_prints_it(): void
    {
        $this->createLaxSchema();
        DB::table('localisations')->insert(['id' => 1, 'localisation' => 'Siège', 'code' => 'LOC-1']);
        DB::table('sites')->insert(['id' => 1, 'site' => 'Bureau 50', 'code' => 'EMP-1', 'localisation_id' => 1]);
        DB::table('categories')->insert(['id' => 1, 'categorie' => 'Mobilier de bureau', 'code' => 'BUR']);
        DB::table('designations')->insert(['id' => 1, 'designation' => 'Bureau', 'code' => 'BUR', 'categorie_id' => 1]);
        DB::table('etats')->insert(['id' => 1, 'etat' => 'Moyen', 'code' => 'MOY']);
        DB::table('immobilisations')->insert([
            'id' => 555,
            'site_id' => 1,
            'categorie_id' => 1,
            'designation_id' => 1,
            'etat_id' => 1,
            'code_propose' => '218/BUR/050/2026',
        ]);
        $admin = User::create(['users' => 'admin-fiche', 'mdp' => 'MotDePasse123', 'role' => 'admin']);

        $fiche = $this->actingAs($admin)->get('/biens/555');

        $fiche->assertOk()
            ->assertSee('srcdoc=', false)
            ->assertSee('218/BUR/050/2026 | GS555')
            ->assertDontSee('Code 128');

        $pdf = $this->actingAs($admin)->post('/sites/1/etiquettes/pdf', [
            'mode' => 'bien',
            'bien' => 555,
            'page_format' => 'individuelle',
        ]);

        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertDatabaseHas('etiquette_impressions', [
            'immobilisation_id' => 555,
            'code_etiquette' => 'GS555',
        ]);
    }

    public function test_a4_label_sheet_has_exact_a4_pages_and_asks_for_actual_size_printing(): void
    {
        $this->createLaxSchema();
        DB::table('localisations')->insert(['id' => 1, 'localisation' => 'Siège', 'code' => 'LOC-1']);
        DB::table('sites')->insert(['id' => 1, 'site' => 'Bureau 50', 'code' => 'EMP-1', 'localisation_id' => 1]);
        DB::table('immobilisations')->insert([['id' => 601, 'site_id' => 1], ['id' => 602, 'site_id' => 1]]);
        $admin = User::create(['users' => 'admin-planche', 'mdp' => 'MotDePasse123', 'role' => 'admin']);

        $pdf = $this->actingAs($admin)->post('/sites/1/etiquettes/pdf', [
            'mode' => 'complete',
            'page_format' => 'a4_portrait',
            'grid_mode' => 'manual',
            'columns' => 4,
            'rows' => 14,
            'qr_size' => 12,
            'offset_x' => 0.5,
            'offset_y' => -0.5,
        ]);

        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('/MediaBox [0.000 0.000 595.276 841.890]', $pdf->getContent());
        $this->assertStringContainsString('/PrintScaling /None', $pdf->getContent());
    }

    public function test_print_offsets_are_kept_for_sheets_and_ignored_for_single_labels(): void
    {
        $service = new EtiquetteService;

        $planche = $service->configurationImpression(['page_format' => 'a4_portrait', 'offset_x' => 1.5, 'offset_y' => -2]);
        $individuelle = $service->configurationImpression(['page_format' => 'individuelle', 'offset_x' => 1.5, 'offset_y' => -2]);

        $this->assertSame([1.5, -2.0], [$planche['offset_x'], $planche['offset_y']]);
        $this->assertSame([0.0, 0.0], [$individuelle['offset_x'], $individuelle['offset_y']]);
        $this->assertSame(4.0, $planche['printer_margin']);
        $this->assertSame(0.0, $individuelle['printer_margin']);
    }
}
