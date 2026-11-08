<?php

namespace Tests\Feature;

use App\Models\Immobilisation;
use App\Models\Inventaire;
use App\Models\Sortie;
use App\Models\User;
use App\Services\InventaireService;
use App\Services\SortieService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class SortieImmobilisationTest extends TestCase
{
    use CreatesLaxSchema;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();
        Queue::fake();

        DB::table('localisations')->insert(['id' => 1, 'localisation' => 'Siège', 'code' => 'LOC-1']);
        DB::table('sites')->insert([
            ['id' => 1, 'site' => 'Bureau 1', 'code' => 'EMP-1', 'localisation_id' => 1],
            ['id' => 2, 'site' => 'Magasin', 'code' => 'EMP-2', 'localisation_id' => 1],
        ]);
        DB::table('categories')->insert(['id' => 1, 'categorie' => 'Matériel informatique', 'code' => 'INF', 'imputation_comptable' => '2183']);
        DB::table('designations')->insert(['id' => 1, 'designation' => 'Ordinateur', 'code' => 'ORD', 'categorie_id' => 1]);
        DB::table('etats')->insert([
            ['id' => 1, 'etat' => 'Bon', 'code' => 'BON'],
            ['id' => 2, 'etat' => 'Hors service', 'code' => 'HS'],
        ]);
        DB::table('immobilisations')->insert([
            ['id' => 101, 'site_id' => 1, 'categorie_id' => 1, 'designation_id' => 1, 'numero_article' => 1, 'etat_id' => 1, 'code_propose' => '218/ORD/001/2026', 'code_dernier_inventaire' => 'ANCIEN-1'],
            ['id' => 102, 'site_id' => 1, 'categorie_id' => 1, 'designation_id' => 1, 'numero_article' => 2, 'etat_id' => 1, 'code_propose' => '218/ORD/002/2026', 'code_dernier_inventaire' => 'ANCIEN-2'],
            ['id' => 103, 'site_id' => 1, 'categorie_id' => 1, 'designation_id' => 1, 'numero_article' => 3, 'etat_id' => 1, 'code_propose' => '218/ORD/003/2026', 'code_dernier_inventaire' => 'ANCIEN-3'],
        ]);

        $this->admin = User::create(['users' => 'admin-sorties', 'mdp' => 'MotDePasse123', 'role' => 'admin']);
    }

    public function test_closure_reports_label_codes_and_proposes_missing_or_broken_assets(): void
    {
        $inventaire = $this->inventaireTermine();

        app(InventaireService::class)->cloturerInventaire($inventaire, $this->admin);

        // Les biens retrouvés prennent le code de leur étiquette ; l'absent garde l'ancien.
        $this->assertSame('218/ORD/001/2026', Immobilisation::find(101)->code_dernier_inventaire);
        $this->assertSame('218/ORD/003/2026', Immobilisation::find(103)->code_dernier_inventaire);
        $this->assertSame('ANCIEN-2', Immobilisation::find(102)->code_dernier_inventaire);

        $candidats = app(SortieService::class)->candidats($inventaire->fresh())
            ->mapWithKeys(fn (array $ligne): array => [$ligne['bien']->id => [$ligne['constat'], $ligne['motif_propose']]]);

        $this->assertEquals([
            102 => ['absent', 'perte'],
            103 => ['hors_service', 'reforme'],
        ], $candidats->all());
    }

    public function test_validated_decision_removes_assets_from_the_active_register_only(): void
    {
        $inventaire = $this->inventaireTermine();
        app(InventaireService::class)->cloturerInventaire($inventaire, $this->admin);
        $service = app(SortieService::class);

        $sortie = $service->proposer([103], 'reforme', $this->admin, $inventaire);

        // Un brouillon ne retire rien du registre.
        $this->assertSame('brouillon', $sortie->statut);
        $this->assertNotNull(Immobilisation::find(103));
        $this->assertSame('hors_service', $sortie->lignes()->first()->constat);

        $sortie->update(['date_decision' => '2026-11-05']);
        $service->valider($sortie, $this->admin);

        $this->assertNull(Immobilisation::find(103));
        $this->assertSame([101, 102], Immobilisation::query()->orderBy('id')->pluck('id')->all());

        $sorti = Immobilisation::withoutGlobalScope(Immobilisation::PORTEE_EN_SERVICE)->find(103);
        $this->assertTrue($sorti->estSorti());
        $this->assertSame('2026-11-05', $sorti->date_sortie->format('Y-m-d'));
        $this->assertSame('validee', $sortie->fresh()->statut);

        // Le bien sorti n'est plus proposé et ne peut pas être réinscrit.
        $this->assertFalse(app(SortieService::class)->candidats($inventaire->fresh())->contains(fn (array $ligne): bool => $ligne['bien']->id === 103));
        $this->expectException(ValidationException::class);
        $service->proposer([103], 'vente', $this->admin);
    }

    public function test_a_decision_needs_a_date_and_cannot_be_validated_during_an_inventory(): void
    {
        $service = app(SortieService::class);
        $sortie = $service->proposer([101], 'vol', $this->admin);
        $this->assertSame('manuel', $sortie->lignes()->first()->constat);

        try {
            $service->valider($sortie, $this->admin);
            $this->fail('Une décision sans date ne doit pas être validée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('date_decision', $exception->errors());
        }

        $sortie->update(['date_decision' => '2026-11-05']);
        Inventaire::create(['annee' => 2027, 'statut' => 'en_cours']);

        try {
            $service->valider($sortie, $this->admin);
            $this->fail('Une sortie ne doit pas être validée pendant un inventaire en cours.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('statut', $exception->errors());
        }

        $this->assertNotNull(Immobilisation::find(101));
    }

    public function test_an_asset_cannot_sit_in_two_draft_decisions(): void
    {
        $service = app(SortieService::class);
        $premiere = $service->proposer([101], 'cession', $this->admin);

        $this->assertSame('SORT-'.now()->year.'-001', $premiere->numero);
        $this->assertSame($premiere->id, $service->proposer([102], 'cession', $this->admin)->id);

        $this->expectException(ValidationException::class);
        $service->proposer([101], 'don', $this->admin);
    }

    public function test_admin_pages_and_pv_render(): void
    {
        $inventaire = $this->inventaireTermine();
        app(InventaireService::class)->cloturerInventaire($inventaire, $this->admin);
        $sortie = app(SortieService::class)->proposer([102], 'perte', $this->admin, $inventaire);

        $this->actingAs($this->admin)->get(route('inventaires.report', $inventaire))
            ->assertOk()
            ->assertSee('218/ORD/002/2026')
            ->assertSee('Codes reportés');
        $this->actingAs($this->admin)->get(route('sorties.show', $sortie))->assertOk()->assertSee($sortie->numero);
        $this->actingAs($this->admin)->get(route('sorties.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('sorties.export'))->assertOk();

        $pv = $this->actingAs($this->admin)->get(route('sorties.pv', $sortie));
        $pv->assertOk();
        $this->assertStringStartsWith('%PDF-', $pv->getContent());
    }

    /**
     * Inventaire terminé : 101 présent, 102 absent, 103 trouvé hors service.
     */
    private function inventaireTermine(): Inventaire
    {
        $inventaire = Inventaire::create(['annee' => 2026, 'statut' => 'termine', 'created_by' => $this->admin->idUser]);
        $inventaireLocalisationId = DB::table('inventaire_localisations')->insertGetId([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 1,
            'statut' => 'termine',
        ]);

        foreach ([[101, 'present', 'bon_etat'], [102, 'absent', 'bon_etat'], [103, 'present', 'defectueux']] as [$id, $statut, $etat]) {
            DB::table('inventaire_scans')->insert([
                'inventaire_id' => $inventaire->id,
                'inventaire_localisation_id' => $inventaireLocalisationId,
                'immobilisation_id' => $id,
                'date_scan' => now(),
                'statut_scan' => $statut,
                'site_reel_id' => $statut === 'absent' ? null : 1,
                'etat_constate' => $etat,
            ]);
        }

        return $inventaire;
    }
}
