<?php

namespace Tests\Feature;

use App\Exceptions\InventaireWorkflowException;
use App\Exports\InventaireExport;
use App\Exports\Sheets\BiensAbsentsSheet;
use App\Exports\Sheets\BiensDeplacesSheet;
use App\Exports\Sheets\BiensNonScannesSheet;
use App\Exports\Sheets\BiensPresentsSheet;
use App\Exports\Sheets\LocalisationsSheet;
use App\Exports\Sheets\MouvementsSheet;
use App\Exports\Sheets\PerformanceAgentsSheet;
use App\Jobs\GenerateInventaireExport;
use App\Livewire\Biens\ListeBiens;
use App\Livewire\Dashboard as MainDashboard;
use App\Livewire\Inventaires\DashboardInventaire;
use App\Livewire\Inventaires\RapportInventaire;
use App\Livewire\Sites\ListeSites;
use App\Livewire\Traits\WithCachedOptions;
use App\Livewire\Traits\WithSafeTableControls;
use App\Livewire\Users\ListeUsers as UserList;
use App\Models\Inventaire;
use App\Models\InventaireExportRequest;
use App\Models\InventaireImmobilisationAttendue;
use App\Models\InventaireLocalisation;
use App\Models\InventaireScan;
use App\Models\InventaireSite;
use App\Models\Localisation;
use App\Models\Site;
use App\Models\User;
use App\Services\InventaireService;
use App\Services\RapportService;
use App\Services\SiteScanService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use CreatesLaxSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();
    }

    public function test_an_inventory_cannot_be_closed_before_it_is_finished(): void
    {
        $inventaire = $this->createInventory('en_cours');

        $this->expectException(\LogicException::class);

        $inventaire->cloturer();
    }

    public function test_a_started_or_closed_inventory_cannot_be_deleted(): void
    {
        $inventaire = $this->createInventory('cloture');

        $this->expectException(\LogicException::class);

        $inventaire->delete();
    }

    public function test_inventory_creation_is_rolled_back_when_an_assignment_is_invalid(): void
    {
        $admin = $this->createUser('inventory-creator-admin');
        $admin->update(['role' => 'admin']);

        DB::table('localisations')->insert([
            'id' => 1,
            'localisation' => 'Siège',
            'code' => 'LOC-1',
        ]);
        DB::table('sites')->insert([
            'id' => 1,
            'site' => 'Bureau',
            'code' => 'EMP-1',
            'localisation_id' => 1,
        ]);

        try {
            app(InventaireService::class)->creerInventaire([
                'annee' => 2025,
                'date_debut' => now(),
                'localisations' => [1],
                'assignations' => [1 => [999]],
            ], $admin);
            $this->fail('Une affectation invalide aurait dû interrompre la création.');
        } catch (\Exception) {
            $this->assertDatabaseCount('inventaires', 0);
            $this->assertDatabaseCount('inventaire_localisations', 0);
        }
    }

    public function test_inventory_creation_rejects_a_non_admin_user(): void
    {
        $agent = $this->createUser('inventory-creator-agent');

        try {
            app(InventaireService::class)->creerInventaire([
                'annee' => 2025,
                'date_debut' => now(),
                'localisations' => [],
                'assignations' => [],
            ], $agent);
            $this->fail('Un agent ne devrait pas pouvoir créer un inventaire.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('administrateur', $exception->getMessage());
            $this->assertDatabaseCount('inventaires', 0);
            $this->assertDatabaseCount('inventaire_localisations', 0);
        }
    }

    public function test_inventory_creation_rejects_an_empty_localisation_selection(): void
    {
        $admin = $this->createUser('empty-inventory-admin');
        $admin->update(['role' => 'admin']);

        try {
            app(InventaireService::class)->creerInventaire([
                'annee' => 2025,
                'date_debut' => now(),
                'localisations' => [],
                'assignations' => [],
            ], $admin);
            $this->fail('Un inventaire sans localisation ne devrait pas être créé.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('localisations', $exception->errors());
            $this->assertDatabaseCount('inventaires', 0);
        }
    }

    public function test_all_localisations_must_be_assigned_before_starting(): void
    {
        $admin = $this->createUser('start-admin');
        $admin->update(['role' => 'admin']);
        $inventaire = $this->createInventory('en_preparation');
        InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 1,
            'statut' => 'en_attente',
        ]);

        $this->expectException(\Exception::class);

        app(InventaireService::class)->demarrerInventaire($inventaire, $admin);
    }

    public function test_localisation_reassignment_updates_the_primary_agent_and_assignment(): void
    {
        [, , $inventaireLocalisation] = $this->createAssignedWorkflow();
        $admin = $this->createUser('reassignment-admin');
        $admin->update(['role' => 'admin']);
        $newAgent = $this->createUser('reassignment-agent');

        app(InventaireService::class)->reassignerLocalisation(
            $inventaireLocalisation,
            $newAgent,
            $admin,
        );

        $this->assertSame($newAgent->idUser, $inventaireLocalisation->user_id);
        $this->assertTrue($inventaireLocalisation->agents()->whereKey($newAgent->idUser)->exists());
    }

    public function test_localisation_reassignment_rejects_a_non_admin_user(): void
    {
        [$agent, , $inventaireLocalisation] = $this->createAssignedWorkflow();
        $newAgent = $this->createUser('unauthorized-reassignment-agent');

        try {
            app(InventaireService::class)->reassignerLocalisation(
                $inventaireLocalisation,
                $newAgent,
                $agent,
            );
            $this->fail('Un agent ne devrait pas pouvoir réassigner une localisation.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('administrateur', $exception->getMessage());
            $this->assertSame($agent->idUser, $inventaireLocalisation->fresh()->user_id);
            $this->assertFalse($inventaireLocalisation->agents()->whereKey($newAgent->idUser)->exists());
        }
    }

    public function test_localisation_agent_toggle_keeps_primary_assignment_consistent(): void
    {
        [$primaryAgent, , $inventaireLocalisation] = $this->createAssignedWorkflow();
        $admin = $this->createUser('toggle-admin');
        $admin->update(['role' => 'admin']);
        $additionalAgent = $this->createUser('toggle-additional-agent');
        $service = app(InventaireService::class);

        $this->assertTrue($service->basculerAgentLocalisation(
            $inventaireLocalisation,
            $additionalAgent,
            $admin,
        ));
        $this->assertSame($primaryAgent->idUser, $inventaireLocalisation->fresh()->user_id);
        $this->assertTrue($inventaireLocalisation->agents()->whereKey($additionalAgent->idUser)->exists());

        $this->assertFalse($service->basculerAgentLocalisation(
            $inventaireLocalisation,
            $additionalAgent,
            $admin,
        ));
        $this->assertFalse($inventaireLocalisation->agents()->whereKey($additionalAgent->idUser)->exists());
        $this->assertSame($primaryAgent->idUser, $inventaireLocalisation->fresh()->user_id);

        $this->assertFalse($service->basculerAgentLocalisation(
            $inventaireLocalisation,
            $primaryAgent,
            $admin,
        ));
        $this->assertNull($inventaireLocalisation->fresh()->user_id);
        $this->assertDatabaseCount('inventaire_localisation_user', 0);
    }

    public function test_localisation_agent_toggle_rejects_a_non_admin_user(): void
    {
        [$agent, , $inventaireLocalisation] = $this->createAssignedWorkflow();
        $additionalAgent = $this->createUser('unauthorized-toggle-agent');

        try {
            app(InventaireService::class)->basculerAgentLocalisation(
                $inventaireLocalisation,
                $additionalAgent,
                $agent,
            );
            $this->fail('Un agent ne devrait pas pouvoir modifier les assignations.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('administrateur', $exception->getMessage());
            $this->assertFalse($inventaireLocalisation->agents()->whereKey($additionalAgent->idUser)->exists());
        }
    }

    public function test_localisation_agent_toggle_rejects_an_inactive_inventory(): void
    {
        [, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $admin = $this->createUser('inactive-toggle-admin');
        $admin->update(['role' => 'admin']);
        $additionalAgent = $this->createUser('inactive-toggle-agent');
        $inventaire->update(['statut' => 'termine']);

        try {
            app(InventaireService::class)->basculerAgentLocalisation(
                $inventaireLocalisation,
                $additionalAgent,
                $admin,
            );
            $this->fail('Les assignations d’un inventaire terminé ne devraient plus être modifiables.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('plus modifiables', $exception->getMessage());
            $this->assertFalse($inventaireLocalisation->agents()->whereKey($additionalAgent->idUser)->exists());
        }
    }

    public function test_agent_can_be_assigned_to_all_inventory_localisations_atomically(): void
    {
        [, $inventaire] = $this->createAssignedWorkflow();
        $admin = $this->createUser('bulk-assignment-admin');
        $admin->update(['role' => 'admin']);
        $agent = $this->createUser('bulk-assignment-agent');
        $secondLocalisation = InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 2,
            'statut' => 'en_attente',
        ]);

        $count = app(InventaireService::class)->assignerAgentAToutesLesLocalisations(
            $inventaire,
            $agent,
            $admin,
        );

        $this->assertSame(2, $count);
        $this->assertSame(2, DB::table('inventaire_localisation_user')
            ->where('user_id', $agent->idUser)
            ->count());
        $this->assertSame($agent->idUser, $secondLocalisation->fresh()->user_id);
    }

    public function test_inventory_deletion_requires_an_admin_and_a_fresh_preparation_state(): void
    {
        $agent = $this->createUser('delete-inventory-agent');
        $admin = $this->createUser('delete-inventory-admin');
        $admin->update(['role' => 'admin']);
        $inventaire = $this->createInventory('en_preparation');

        try {
            app(InventaireService::class)->supprimerInventaire($inventaire, $agent);
            $this->fail('Un agent ne devrait pas pouvoir supprimer un inventaire.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('administrateur', $exception->getMessage());
            $this->assertDatabaseHas('inventaires', ['id' => $inventaire->id]);
        }

        $staleInventaire = $inventaire->replicate();
        $staleInventaire->id = $inventaire->id;
        $inventaire->update(['statut' => 'en_cours']);

        try {
            app(InventaireService::class)->supprimerInventaire($staleInventaire, $admin);
            $this->fail('Un modèle obsolète ne devrait pas permettre de supprimer un inventaire démarré.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('en préparation', $exception->getMessage());
            $this->assertDatabaseHas('inventaires', ['id' => $inventaire->id]);
        }
    }

    public function test_inventory_start_rejects_a_stale_preparation_model(): void
    {
        [$admin, $inventaire] = $this->createAssignedWorkflow();
        $admin->update(['role' => 'admin']);
        $inventaire->setAttribute('statut', 'en_preparation');

        try {
            app(InventaireService::class)->demarrerInventaire($inventaire, $admin);
            $this->fail('Un modèle périmé ne doit pas permettre de redémarrer un inventaire.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Statut actuel : en_cours', $exception->getMessage());
            $this->assertDatabaseCount('inventaire_immobilisations_attendues', 0);
            $this->assertNull($inventaire->fresh()->referentiel_frozen_at);
        }
    }

    public function test_inventory_start_rejects_a_non_admin_user(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        $inventaire->update(['statut' => 'en_preparation']);

        try {
            app(InventaireService::class)->demarrerInventaire($inventaire, $agent);
            $this->fail('Un agent ne doit pas pouvoir démarrer un inventaire.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('administrateur', $exception->getMessage());
            $this->assertSame('en_preparation', $inventaire->fresh()->statut);
            $this->assertNull($inventaire->fresh()->referentiel_frozen_at);
            $this->assertDatabaseCount('inventaire_immobilisations_attendues', 0);
        }
    }

    public function test_inventory_completion_rejects_a_stale_in_progress_model(): void
    {
        [$admin, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $admin->update(['role' => 'admin']);
        $inventaireLocalisation->update(['statut' => 'termine']);
        DB::table('inventaires')->where('id', $inventaire->id)->update(['statut' => 'termine']);

        try {
            app(InventaireService::class)->terminerInventaire($inventaire, $admin);
            $this->fail('Un modèle périmé ne doit pas permettre de terminer deux fois un inventaire.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Statut actuel : termine', $exception->getMessage());
            $this->assertNull($inventaire->fresh()->date_fin);
        }
    }

    public function test_inventory_completion_rejects_a_non_admin_user(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();

        try {
            app(InventaireService::class)->terminerInventaire($inventaire, $agent);
            $this->fail('Un agent ne doit pas pouvoir terminer un inventaire.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('administrateur', $exception->getMessage());
            $this->assertSame('en_cours', $inventaire->fresh()->statut);
            $this->assertNull($inventaire->fresh()->date_fin);
        }
    }

    public function test_inventory_closure_rejects_a_stale_completed_model(): void
    {
        [$originalCloser, $inventaire] = $this->createAssignedWorkflow();
        $newCloser = $this->createUser('second-closer');
        $newCloser->update(['role' => 'admin']);
        $closedAt = '2025-02-03';
        DB::table('inventaires')->where('id', $inventaire->id)->update([
            'statut' => 'cloture',
            'date_fin' => $closedAt,
            'closed_by' => $originalCloser->idUser,
        ]);
        $inventaire->setAttribute('statut', 'termine');

        try {
            app(InventaireService::class)->cloturerInventaire($inventaire, $newCloser);
            $this->fail('Un modèle périmé ne doit pas permettre de clôturer deux fois un inventaire.');
        } catch (\Exception $exception) {
            $inventaireCloture = $inventaire->fresh();

            $this->assertStringContainsString('Statut actuel : cloture', $exception->getMessage());
            $this->assertSame($originalCloser->idUser, $inventaireCloture->closed_by);
            $this->assertSame($closedAt, $inventaireCloture->date_fin->format('Y-m-d'));
        }
    }

    public function test_inventory_closure_rejects_a_non_admin_user(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        $inventaire->update(['statut' => 'termine']);

        try {
            app(InventaireService::class)->cloturerInventaire($inventaire, $agent);
            $this->fail('Un agent ne doit pas pouvoir clôturer définitivement un inventaire.');
        } catch (AuthorizationException $exception) {
            $inventaireNonCloture = $inventaire->fresh();

            $this->assertStringContainsString('administrateur', $exception->getMessage());
            $this->assertSame('termine', $inventaireNonCloture->statut);
            $this->assertNull($inventaireNonCloture->closed_by);
            $this->assertNull($inventaireNonCloture->date_fin);
        }
    }

    public function test_inventory_model_cannot_close_without_an_explicit_actor(): void
    {
        $inventaire = $this->createInventory('termine');

        try {
            $inventaire->cloturer();
            $this->fail('Une clôture sans administrateur explicite ne devrait pas être acceptée.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('doit être précisé', $exception->getMessage());
            $this->assertSame('termine', $inventaire->fresh()->statut);
            $this->assertNull($inventaire->fresh()->closed_by);
        }
    }

    public function test_interactive_report_uses_frozen_asset_labels(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $this->seedAssetCodeReferenceData();
        DB::table('immobilisations')->where('id', 101)->update([
            'designation_id' => 1,
            'categorie_id' => 1,
        ]);
        app(InventaireService::class)->figerReferentiel($inventaire);
        $inventaireLocalisation->update(['statut' => 'en_cours']);
        InventaireScan::create([
            'inventaire_id' => $inventaire->id,
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'immobilisation_id' => 101,
            'date_scan' => now(),
            'statut_scan' => 'present',
            'localisation_reelle_id' => 1,
            'etat_constate' => 'bon_etat',
            'user_id' => $agent->idUser,
        ]);

        DB::table('designations')->where('id', 1)->update(['designation' => 'Chaise modifiée']);
        DB::table('categories')->where('id', 1)->update(['categorie' => 'Catégorie modifiée']);
        DB::table('localisations')->where('id', 1)->update(['code' => 'LOC-NEW']);

        $component = new RapportInventaire;
        $component->inventaire = $inventaire->fresh();
        $scan = $component->getBiensPresentsProperty()->first();

        $this->assertSame('Chaise', $scan->designation);
        $this->assertSame('Mobilier', $scan->categorie);
        $this->assertSame('LOC-1', $scan->localisation_code);
    }

    public function test_finishing_one_emplacement_does_not_finish_its_localisation(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [['num_ordre' => 101, 'etat_constate' => 'bon_etat']],
        ])->assertOk();

        $this->assertDatabaseHas('inventaire_sites', ['site_id' => 1, 'statut' => 'termine']);
        $this->assertDatabaseHas('inventaire_sites', ['site_id' => 2, 'statut' => 'en_attente']);
        $this->assertDatabaseHas('inventaire_localisations', ['id' => $inventaireLocalisation->id, 'statut' => 'en_cours']);
        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
            'statut_scan' => 'present',
            'site_reel_id' => 1,
        ]);
    }

    public function test_finishing_the_last_emplacement_records_missing_assets_as_absent(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [['num_ordre' => 101, 'etat_constate' => 'bon_etat']],
        ])->assertOk();
        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [],
        ])->assertOk();

        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 102,
            'statut_scan' => 'absent',
            'site_reel_id' => null,
        ]);
        $this->assertDatabaseHas('inventaire_localisations', [
            'id' => $inventaireLocalisation->id,
            'statut' => 'termine',
        ]);
    }

    public function test_emplacement_finalisation_reports_moved_missing_and_unknown_assets(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        DB::table('etats')->insert([
            'id' => 2,
            'etat' => 'Neuf',
            'code' => null,
        ]);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $response = $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [
                ['num_ordre' => 102, 'etat_id' => 2],
                ['num_ordre' => 999, 'etat_constate' => 'bon_etat'],
            ],
        ])->assertOk()
            ->assertJsonPath('statistiques.total_attendu', 1)
            ->assertJsonPath('statistiques.total_scanne', 1)
            ->assertJsonPath('statistiques.total_manquant', 1)
            ->assertJsonPath('statistiques.total_en_trop', 1)
            ->assertJsonPath('statistiques.total_introuvables', 1)
            ->assertJsonPath('biens_introuvables.0', 999)
            ->assertJsonPath('biens_en_trop.0.num_ordre', 102)
            ->assertJsonPath('biens_manquants.0.num_ordre', 101);

        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 102,
            'statut_scan' => 'deplace',
            'etat_constate' => 'neuf',
            'site_reel_id' => 1,
        ]);
        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
            'statut_scan' => 'absent',
        ]);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [],
        ])->assertOk()
            ->assertJsonPath('already_finalized', true);

        $response->assertJsonCount(1, 'biens_introuvables');
    }

    public function test_emplacement_finalisation_preloads_states_and_existing_scans(): void
    {
        [$agent] = $this->createAssignedWorkflow();
        DB::table('etats')->insert([
            'id' => 1,
            'etat' => 'Bon état',
            'code' => 'BE',
        ]);
        DB::table('immobilisations')->insert(collect(range(103, 112))->map(fn (int $numOrdre) => [
            'id' => $numOrdre,
            'site_id' => 1,
        ])->all());
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $biensScannes = collect([101, ...range(103, 112)])
            ->map(fn (int $numOrdre) => ['num_ordre' => $numOrdre, 'etat_id' => 1])
            ->all();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => $biensScannes,
        ])->assertOk()
            ->assertJsonPath('statistiques.total_attendu', 11)
            ->assertJsonPath('statistiques.total_scanne', 11);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $etatQueries = collect($queries)->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'from "etat"'),
        );
        $existingScanQueries = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'from "inventaire_scans"')
                && str_contains($sql, '"bien_id" in');
        });
        $scanWriteQueries = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'insert') && str_contains($sql, 'inventaire_scans');
        });

        $this->assertLessThanOrEqual(2, $etatQueries->count());
        $this->assertLessThanOrEqual(4, $existingScanQueries->count());
        $this->assertCount(1, $scanWriteQueries);
        $this->assertDatabaseCount('inventaire_scans', 11);
        $this->assertDatabaseHas('inventaire_scans', [
            'immobilisation_id' => 112,
            'etat_constate' => 'bon_etat',
        ]);
    }

    public function test_emplacement_finalisation_reports_each_invalid_state(): void
    {
        [$agent] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [
                ['num_ordre' => 101, 'etat_id' => 999],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('biens_scannes.0.etat_id');

        $this->assertDatabaseCount('inventaire_scans', 0);
    }

    public function test_scanning_an_asset_later_replaces_its_absent_record(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [],
        ])->assertOk();
        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
            'statut_scan' => 'absent',
        ]);

        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [
                ['num_ordre' => 101, 'etat_constate' => 'bon_etat'],
                ['num_ordre' => 102, 'etat_constate' => 'bon_etat'],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('inventaire_scans', 2);
        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
            'statut_scan' => 'deplace',
            'site_reel_id' => 2,
        ]);
    }

    public function test_moved_assets_update_all_localisation_counters_in_bulk(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        $movedAssetIds = [];
        $lastLocalisation = null;

        foreach (range(2, 11) as $localisationId) {
            $assetId = 200 + $localisationId;
            $emplacementId = 100 + $localisationId;
            $movedAssetIds[] = $assetId;
            DB::table('localisations')->insert([
                'id' => $localisationId,
                'localisation' => "Site {$localisationId}",
                'code' => "LOC-{$localisationId}",
            ]);
            DB::table('sites')->insert([
                'id' => $emplacementId,
                'site' => "Bureau {$localisationId}",
                'code' => "EMP-{$localisationId}",
                'localisation_id' => $localisationId,
            ]);
            DB::table('immobilisations')->insert([
                'id' => $assetId,
                'site_id' => $emplacementId,
            ]);
            $lastLocalisation = InventaireLocalisation::create([
                'inventaire_id' => $inventaire->id,
                'localisation_id' => $localisationId,
                'statut' => 'en_cours',
                'nombre_biens_attendus' => 1,
                'user_id' => $agent->idUser,
            ]);
            InventaireScan::create([
                'inventaire_id' => $inventaire->id,
                'inventaire_localisation_id' => $lastLocalisation->id,
                'immobilisation_id' => $assetId,
                'date_scan' => now(),
                'statut_scan' => 'absent',
                'etat_constate' => 'bon_etat',
                'user_id' => $agent->idUser,
            ]);
        }

        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);
        $payload = collect([101, ...$movedAssetIds])
            ->map(fn (int $numOrdre) => ['num_ordre' => $numOrdre, 'etat_constate' => 'bon_etat'])
            ->all();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => $payload,
        ])->assertOk()
            ->assertJsonPath('statistiques.total_en_trop', 10);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $aggregateQueries = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'count(distinct inventaire_scans.immobilisation_id)')
                && str_contains($sql, 'join "sites"');
        });
        $counterUpserts = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'insert') && str_contains($sql, 'inventaire_localisations');
        });

        $this->assertCount(1, $aggregateQueries);
        $this->assertCount(1, $counterUpserts);
        $this->assertSame(1, $lastLocalisation?->fresh()->nombre_biens_scannes);
    }

    public function test_moved_asset_counter_stays_attached_to_its_frozen_localisation(): void
    {
        [$agent, $inventaire, $originalLocalisation] = $this->createAssignedWorkflow();
        app(InventaireService::class)->figerReferentiel($inventaire);

        DB::table('localisations')->insert([
            'id' => 2,
            'localisation' => 'Annexe',
            'code' => 'LOC-2',
        ]);
        DB::table('sites')->insert([
            'id' => 3,
            'site' => 'Bureau annexe',
            'code' => 'EMP-3',
            'localisation_id' => 2,
        ]);
        $scanLocalisation = InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 2,
            'statut' => 'en_cours',
            'nombre_biens_attendus' => 0,
            'user_id' => $agent->idUser,
        ]);
        $scanLocalisation->agents()->attach($agent->idUser);
        InventaireSite::create([
            'inventaire_localisation_id' => $scanLocalisation->id,
            'site_id' => 3,
            'statut' => 'en_attente',
            'nombre_biens_attendus' => 0,
        ]);
        DB::table('immobilisations')->where('id', 101)->update(['site_id' => 3]);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/3/terminer', [
            'biens_scannes' => [
                ['num_ordre' => 101, 'etat_constate' => 'bon_etat'],
            ],
        ])->assertOk();

        $this->assertSame(1, $originalLocalisation->fresh()->nombre_biens_scannes);
        $this->assertSame(0, $scanLocalisation->fresh()->nombre_biens_scannes);
    }

    public function test_emplacement_finalisation_uses_the_frozen_expected_placement(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        app(InventaireService::class)->figerReferentiel($inventaire);
        DB::table('localisations')->insert([
            'id' => 2,
            'localisation' => 'Annexe',
            'code' => 'LOC-2',
        ]);
        DB::table('sites')->where('id', 2)->update(['localisation_id' => 2]);
        DB::table('immobilisations')->where('id', 101)->update(['site_id' => 2]);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [
                ['num_ordre' => 101, 'etat_constate' => 'bon_etat'],
            ],
        ])->assertOk()
            ->assertJsonPath('statistiques.total_attendu', 1)
            ->assertJsonPath('statistiques.total_scanne', 1)
            ->assertJsonPath('statistiques.total_en_trop', 0)
            ->assertJsonPath('statistiques.total_manquant', 0);

        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
            'statut_scan' => 'present',
            'site_reel_id' => 1,
        ]);
    }

    public function test_missing_assets_are_recorded_with_one_bulk_insert(): void
    {
        [$agent] = $this->createAssignedWorkflow();
        DB::table('immobilisations')->insert(collect(range(103, 126))->map(fn (int $numOrdre) => [
            'id' => $numOrdre,
            'site_id' => 1,
        ])->all());
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [],
        ])->assertOk()
            ->assertJsonPath('statistiques.total_manquant', 25)
            ->assertJsonCount(25, 'biens_manquants');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $scanInsertQueries = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'insert') && str_contains($sql, 'inventaire_scans');
        });

        $this->assertCount(1, $scanInsertQueries);
        $this->assertDatabaseCount('inventaire_scans', 25);
    }

    public function test_an_unassigned_agent_cannot_open_a_historical_report(): void
    {
        $assignedAgent = $this->createUser('assigned');
        $otherAgent = $this->createUser('other');
        $inventaire = $this->createInventory('termine');
        $localisation = InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 1,
            'statut' => 'termine',
            'user_id' => $assignedAgent->idUser,
        ]);
        $localisation->agents()->attach($assignedAgent->idUser);

        $this->actingAs($otherAgent)
            ->get('/inventaires/'.$inventaire->id.'/rapport')
            ->assertForbidden();
    }

    public function test_inventory_report_and_dashboard_distinguish_physical_scans_from_processed_assets(): void
    {
        $agent = $this->createUser('rapport-agent');

        DB::table('localisations')->insert([
            'id' => 1,
            'localisation' => 'Siège',
            'code' => 'LOC-1',
        ]);
        DB::table('sites')->insert([
            'id' => 1,
            'site' => 'Magasin',
            'code' => 'EMP-1',
            'localisation_id' => 1,
        ]);
        DB::table('immobilisations')->insert([
            ['id' => 101, 'site_id' => 1],
            ['id' => 102, 'site_id' => 1],
            ['id' => 103, 'site_id' => 1],
            ['id' => 104, 'site_id' => 1],
        ]);

        $inventaire = $this->createInventory('termine');
        $inventaireLocalisation = InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 1,
            'statut' => 'termine',
            'nombre_biens_attendus' => 4,
            'nombre_biens_scannes' => 3,
            'user_id' => $agent->idUser,
        ]);

        foreach ([
            [101, 'present', 'bon_etat'],
            [102, 'deplace', 'defectueux'],
            [103, 'absent', 'bon_etat'],
        ] as [$bienId, $statut, $etat]) {
            InventaireScan::create([
                'inventaire_id' => $inventaire->id,
                'inventaire_localisation_id' => $inventaireLocalisation->id,
                'immobilisation_id' => $bienId,
                'date_scan' => now(),
                'statut_scan' => $statut,
                'etat_constate' => $etat,
                'user_id' => $agent->idUser,
            ]);
        }

        $statistiques = app(InventaireService::class)->calculerStatistiques($inventaire->fresh());

        $this->assertSame(4, $statistiques['total_biens_attendus']);
        $this->assertSame(3, $statistiques['total_biens_traites']);
        $this->assertSame(2, $statistiques['total_biens_scannes']);
        $this->assertSame(1, $statistiques['biens_non_scannes']);
        $this->assertSame(25.0, $statistiques['taux_conformite']);
        $this->assertSame(50.0, $statistiques['taux_couverture']);
        $this->assertSame(75.0, $statistiques['taux_rapprochement']);
        $this->assertSame(25.0, $statistiques['taux_absence']);
        $this->assertSame(2, $statistiques['total_anomalies']);
        $this->assertSame(50.0, $statistiques['taux_anomalies']);

        $component = new DashboardInventaire;
        $component->inventaire = $inventaire->fresh()->load([
            'inventaireLocalisations.localisation',
            'inventaireLocalisations.agent',
            'inventaireScans',
        ]);
        $dashboard = $component->getStatistiquesProperty();

        $this->assertSame(3, $dashboard['total_biens_traites']);
        $this->assertSame(2, $dashboard['total_biens_scannes']);
        $this->assertSame(1, $dashboard['biens_non_verifies']);
        $this->assertSame(75.0, $dashboard['progression_globale']);
        $this->assertSame(100.0, $dashboard['progression_localisations']);
        $this->assertSame(2, $dashboard['scans_aujourdhui']);
    }

    public function test_inventory_report_uses_the_frozen_reference_after_asset_changes(): void
    {
        $agent = $this->createUser('snapshot-agent');
        DB::table('localisations')->insert([
            'id' => 1,
            'localisation' => 'Siège historique',
            'code' => 'LOC-HIST',
        ]);
        DB::table('sites')->insert([
            'id' => 1,
            'site' => 'Bureau historique',
            'code' => 'EMP-HIST',
            'localisation_id' => 1,
        ]);
        DB::table('categories')->insert([
            'id' => 1,
            'categorie' => 'Catégorie historique',
            'code' => 'CAT-H',
        ]);
        DB::table('designations')->insert([
            'id' => 1,
            'designation' => 'Bien historique',
            'code' => 'BIEN-H',
        ]);
        DB::table('immobilisations')->insert([
            'id' => 501,
            'designation_id' => 1,
            'categorie_id' => 1,
            'site_id' => 1,
        ]);

        $inventaire = $this->createInventory('en_preparation');
        $inventaireLocalisation = InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 1,
            'statut' => 'en_attente',
            'nombre_biens_attendus' => 1,
            'user_id' => $agent->idUser,
        ]);
        $inventaireLocalisation->agents()->attach($agent->idUser);
        InventaireSite::create([
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'site_id' => 1,
            'nombre_biens_attendus' => 1,
        ]);

        $admin = $this->createUser('snapshot-admin');
        $admin->update(['role' => 'admin']);
        app(InventaireService::class)->demarrerInventaire($inventaire, $admin);

        DB::table('immobilisations')->where('id', 501)->update(['descriptif' => 'Descriptif modifié']);
        DB::table('designations')->where('id', 1)->update(['designation' => 'Bien modifié']);
        DB::table('sites')->where('id', 1)->update(['site' => 'Bureau modifié']);

        $snapshot = InventaireImmobilisationAttendue::query()->sole();
        $detail = app(RapportService::class)->getDetailParSite($inventaire->fresh());
        $statistiques = app(InventaireService::class)->calculerStatistiques($inventaire->fresh());

        $this->assertSame('Bien historique', $snapshot->designation);
        $this->assertSame('Bureau historique', $detail[0]['designation']);
        $this->assertSame('Bien historique', $detail[0]['lignes_attendues'][0]['designation']);
        $this->assertSame(1, $statistiques['total_biens_attendus']);
    }

    public function test_inventory_summary_is_calculated_with_two_aggregate_queries(): void
    {
        $inventaire = $this->createInventory('en_cours');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $statistiques = $inventaire->getStatistiques();
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(2, $queries);
        $this->assertSame(0, $statistiques['total_localisations']);
        $this->assertSame(0, $statistiques['total_scans']);
        $this->assertSame(0, $statistiques['progression']);
        $this->assertSame(0, $statistiques['taux_conformite']);
    }

    public function test_empty_frozen_reference_does_not_adopt_assets_added_later(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        DB::table('immobilisations')->delete();
        app(InventaireService::class)->figerReferentiel($inventaire);
        DB::table('immobilisations')->insert([
            'id' => 103,
            'site_id' => 1,
        ]);

        $statistiques = app(InventaireService::class)->calculerStatistiques($inventaire->fresh());

        $this->assertNotNull($inventaire->fresh()->referentiel_frozen_at);
        $this->assertSame(0, $statistiques['total_biens_attendus']);

        Sanctum::actingAs($agent, ['inventory:read']);
        $this->getJson('/api/v1/emplacements/1/biens')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'biens');
    }

    public function test_freezing_reference_rolls_back_when_snapshot_insertion_fails(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Le déclencheur d’échec contrôlé utilise la syntaxe SQLite.');
        }

        [, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $frozenAt = '2025-01-01 00:00:00';
        $inventaire->update(['referentiel_frozen_at' => $frozenAt]);
        InventaireImmobilisationAttendue::create([
            'inventaire_id' => $inventaire->id,
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'immobilisation_id' => 999,
            'site_id' => 1,
            'localisation_id' => 1,
        ]);
        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_inventory_snapshot_insert
            BEFORE INSERT ON inventaire_immobilisations_attendues
            WHEN NEW.immobilisation_id = 102
            BEGIN
                SELECT RAISE(ABORT, 'snapshot insertion interrupted');
            END
            SQL);

        try {
            app(InventaireService::class)->figerReferentiel($inventaire);
            $this->fail('Le gel aurait dû échouer.');
        } catch (QueryException) {
            $this->assertDatabaseHas('inventaire_immobilisations_attendues', [
                'inventaire_id' => $inventaire->id,
                'immobilisation_id' => 999,
            ]);
            $this->assertDatabaseCount('inventaire_immobilisations_attendues', 1);
            $this->assertSame($frozenAt, $inventaire->fresh()->referentiel_frozen_at->format('Y-m-d H:i:s'));
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_inventory_snapshot_insert');
        }
    }

    public function test_inventory_dashboard_does_not_rebuild_an_empty_frozen_reference(): void
    {
        [, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        DB::table('immobilisations')->delete();
        app(InventaireService::class)->figerReferentiel($inventaire);
        $inventaireLocalisation->update(['nombre_biens_attendus' => 0]);

        DB::table('immobilisations')->insert([
            'id' => 103,
            'site_id' => 1,
        ]);

        $component = new DashboardInventaire;
        $component->inventaire = $inventaire->fresh()->load([
            'inventaireLocalisations.localisation',
            'inventaireScans',
        ]);

        $this->assertSame(0, $component->getStatistiquesProperty()['total_biens_attendus']);
        $this->assertSame(0, $inventaireLocalisation->fresh()->nombre_biens_attendus);
    }

    public function test_inventory_dashboard_repairs_legacy_unfrozen_zero_counters(): void
    {
        [, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaireLocalisation->update(['nombre_biens_attendus' => 0]);

        $component = new DashboardInventaire;
        $component->inventaire = $inventaire->fresh()->load([
            'inventaireLocalisations.localisation',
            'inventaireScans',
        ]);

        $this->assertSame(2, $component->getStatistiquesProperty()['total_biens_attendus']);
        $this->assertSame(0, $inventaireLocalisation->fresh()->nombre_biens_attendus);
    }

    public function test_main_dashboard_only_uses_inventories_accessible_to_the_agent(): void
    {
        [$agent, $activeInventory] = $this->createAssignedWorkflow();
        $otherAgent = $this->createUser('intrus-dashboard');

        DB::table('localisations')->insert([
            'id' => 2,
            'localisation' => 'Site privé',
            'code' => 'LOC-PRIVE',
        ]);

        $inaccessibleInventory = Inventaire::create([
            'annee' => 2027,
            'date_debut' => now(),
            'statut' => 'en_cours',
            'created_by' => $otherAgent->idUser,
        ]);
        InventaireLocalisation::create([
            'inventaire_id' => $inaccessibleInventory->id,
            'localisation_id' => 2,
            'statut' => 'en_cours',
            'user_id' => $otherAgent->idUser,
        ]);
        $closedAccessibleInventory = Inventaire::create([
            'annee' => 2026,
            'date_debut' => now(),
            'date_fin' => now(),
            'statut' => 'cloture',
            'created_by' => $agent->idUser,
        ]);
        InventaireLocalisation::create([
            'inventaire_id' => $closedAccessibleInventory->id,
            'localisation_id' => 1,
            'statut' => 'termine',
            'user_id' => $agent->idUser,
        ]);

        $this->actingAs($agent);
        $component = new MainDashboard;
        $component->mount();

        $this->assertSame($activeInventory->id, $component->inventaireEnCours->id);
        $this->assertStringNotContainsString(
            'intrus-dashboard',
            collect($component->dernieresActions)->pluck('message')->implode(' '),
        );
    }

    public function test_dashboard_sidebar_does_not_render_html_attributes_as_visible_text(): void
    {
        $admin = $this->createUser('dashboard-layout-admin');
        $admin->update(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');
        $visibleText = html_entity_decode(strip_tags($response->getContent()));

        $response->assertOk();
        $this->assertStringNotContainsString('class="w-full flex items-center justify-between', $visibleText);
        $this->assertStringNotContainsString(':aria-expanded="openMenu', $visibleText);
    }

    public function test_primary_navigation_uses_livewire_navigation_without_a_document_reload(): void
    {
        $admin = $this->createUser('dashboard-navigation-admin');
        $admin->update(['role' => 'admin']);

        $content = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        foreach (['dashboard', 'biens.index', 'localisations.index', 'inventaires.index', 'users.index', 'profil'] as $routeName) {
            $this->assertStringContainsString(
                'href="'.route($routeName).'" wire:navigate',
                $content,
                "La route {$routeName} doit utiliser la navigation Livewire.",
            );
        }
    }

    public function test_inventory_status_actions_use_the_embedded_confirmation_dialog(): void
    {
        $admin = $this->createUser('inventory-dialog-admin');
        $admin->update(['role' => 'admin']);
        $inventaire = $this->createInventory('en_preparation');

        $content = $this->actingAs($admin)->get(route('inventaires.show', $inventaire))->assertOk()->getContent();

        $this->assertStringNotContainsString('wire:confirm', $content);
        $this->assertStringContainsString("action: 'passerEnCours'", $content);
        $this->assertStringContainsString('role="dialog"', $content);
        $this->assertStringContainsString('x-trap.inert.noscroll="confirmation"', $content);

        $inventaire->update(['statut' => 'en_cours']);
        $content = $this->get(route('inventaires.show', $inventaire))->assertOk()->getContent();
        $this->assertStringContainsString("action: 'terminerInventaire'", $content);
        $this->assertStringNotContainsString('wire:confirm', $content);

        $inventaire->update(['statut' => 'termine']);
        $content = $this->get(route('inventaires.show', $inventaire))->assertOk()->getContent();
        $this->assertStringContainsString("action: 'cloturerInventaire'", $content);
        $this->assertStringNotContainsString('wire:confirm', $content);
    }

    public function test_main_dashboard_uses_compact_bounded_location_progress(): void
    {
        $this->createAssignedWorkflow();
        $admin = $this->createUser('compact-dashboard-admin');
        $admin->update(['role' => 'admin']);

        $content = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Progression par localisation', $content);
        $this->assertStringContainsString('max-h-56', $content);
        $this->assertStringContainsString('role="progressbar"', $content);
        $this->assertStringNotContainsString('id="servicesChart"', $content);
    }

    public function test_main_dashboard_counts_only_physical_scans_and_uses_reference_emplacements(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        app(InventaireService::class)->figerReferentiel($inventaire);

        foreach ([[101, 'present'], [102, 'absent']] as [$bienId, $statut]) {
            InventaireScan::create([
                'inventaire_id' => $inventaire->id,
                'inventaire_localisation_id' => $inventaireLocalisation->id,
                'immobilisation_id' => $bienId,
                'date_scan' => now(),
                'statut_scan' => $statut,
                'localisation_reelle_id' => $statut === 'present' ? 1 : null,
                'etat_constate' => 'bon_etat',
                'user_id' => $agent->idUser,
            ]);
        }

        // Le bien est déplacé dans le parc courant après le gel du référentiel.
        DB::table('immobilisations')->where('id', 101)->update(['site_id' => 2]);

        $this->actingAs($agent);
        $component = new MainDashboard;
        $component->mount();

        $this->assertSame(1, $component->statistiquesInventaire['total_scans']);
        $this->assertSame(1, $component->statistiquesInventaire['scans_absents']);
        $this->assertSame(1, collect($component->emplacementsInventories)->firstWhere('code', 'EMP-1')['biens_scannes']);
        $this->assertSame(0, collect($component->emplacementsInventories)->firstWhere('code', 'EMP-2')['biens_scannes']);
    }

    public function test_inventory_dashboard_alerts_and_graph_use_recent_physical_activity(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaireLocalisation->update([
            'statut' => 'en_cours',
            'date_debut_scan' => now()->subDays(2),
        ]);

        foreach ([[101, 'present'], [102, 'absent']] as [$bienId, $statut]) {
            InventaireScan::create([
                'inventaire_id' => $inventaire->id,
                'inventaire_localisation_id' => $inventaireLocalisation->id,
                'immobilisation_id' => $bienId,
                'date_scan' => now(),
                'statut_scan' => $statut,
                'etat_constate' => 'bon_etat',
                'user_id' => $agent->idUser,
            ]);
        }

        $component = new DashboardInventaire;
        $component->inventaire = $inventaire->fresh()->load([
            'inventaireLocalisations.localisation',
            'inventaireScans',
        ]);

        $this->assertSame([], $component->getAlertesProperty()['localisations_bloquees']);
        $this->assertSame([['date' => now()->toDateString(), 'total' => 1]], $component->getScansGraphDataProperty());
    }

    public function test_inventory_dashboard_only_offers_assignable_roles(): void
    {
        $agent = $this->createUser('assignable-agent');
        $admin = $this->createUser('assignable-admin');
        $admin->update(['role' => 'admin']);
        $component = new DashboardInventaire;
        $ids = $component->getAgentsDisponiblesProperty()->pluck('idUser');

        $this->assertTrue($ids->contains($agent->idUser));
        $this->assertTrue($ids->contains($admin->idUser));
    }

    public function test_api_scan_uses_the_inventory_service_and_updates_the_counter(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaireLocalisation->update(['statut' => 'en_cours']);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson("/api/v1/inventaires/{$inventaire->id}/scan", [
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'bien_id' => 101,
            'statut_scan' => 'present',
            'localisation_reelle_id' => 1,
            'etat_constate' => 'bon_etat',
        ])->assertCreated();

        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
            'user_id' => $agent->idUser,
        ]);
        $this->assertSame(1, $inventaireLocalisation->fresh()->nombre_biens_scannes);
    }

    public function test_inventory_service_rejects_a_scan_from_an_unassigned_agent(): void
    {
        [, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $unassignedAgent = $this->createUser('unassigned-scanner');
        $inventaireLocalisation->update(['statut' => 'en_cours']);

        try {
            app(InventaireService::class)->enregistrerScan([
                'inventaire_id' => $inventaire->id,
                'inventaire_localisation_id' => $inventaireLocalisation->id,
                'immobilisation_id' => 101,
                'localisation_reelle_id' => 1,
                'etat_constate' => 'bon_etat',
            ], $unassignedAgent);
            $this->fail('Un agent non affecté ne doit pas pouvoir enregistrer un scan.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString("n'êtes pas assigné", $exception->getMessage());
            $this->assertDatabaseMissing('inventaire_scans', [
                'inventaire_id' => $inventaire->id,
                'immobilisation_id' => 101,
            ]);
            $this->assertSame(0, $inventaireLocalisation->fresh()->nombre_biens_scannes);
        }
    }

    public function test_emplacement_scan_service_rejects_an_unassigned_agent(): void
    {
        [, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $unassignedAgent = $this->createUser('unassigned-emplacement-scanner');
        $inventaireEmplacement = InventaireSite::query()
            ->where('inventaire_localisation_id', $inventaireLocalisation->id)
            ->where('site_id', 1)
            ->firstOrFail();
        $emplacement = Site::findOrFail(1);

        try {
            app(SiteScanService::class)->finaliser(
                $inventaire,
                $inventaireLocalisation,
                $inventaireEmplacement,
                $emplacement,
                $unassignedAgent,
                [],
                'inventaire:'.$inventaire->id.':emplacement:'.$inventaireEmplacement->id.':user:'.$unassignedAgent->idUser,
            );
            $this->fail('Un agent non affecté ne doit pas pouvoir finaliser un emplacement.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString("n'êtes pas assigné", $exception->getMessage());
            $this->assertSame('en_attente', $inventaireEmplacement->fresh()->statut);
            $this->assertDatabaseCount('inventaire_scans', 0);
        }
    }

    public function test_api_scan_derives_status_and_counter_from_the_frozen_reference(): void
    {
        [$agent, $inventaire, $originalLocalisation] = $this->createAssignedWorkflow();
        app(InventaireService::class)->figerReferentiel($inventaire);

        DB::table('localisations')->insert([
            'id' => 2,
            'localisation' => 'Annexe',
            'code' => 'LOC-2',
        ]);
        DB::table('sites')->insert([
            'id' => 3,
            'site' => 'Bureau annexe',
            'code' => 'EMP-3',
            'localisation_id' => 2,
        ]);
        DB::table('immobilisations')->where('id', 101)->update(['site_id' => 3]);
        $scanLocalisation = InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 2,
            'statut' => 'en_cours',
            'nombre_biens_attendus' => 0,
            'user_id' => $agent->idUser,
        ]);
        $scanLocalisation->agents()->attach($agent->idUser);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson("/api/v1/inventaires/{$inventaire->id}/scan", [
            'inventaire_localisation_id' => $scanLocalisation->id,
            'bien_id' => 101,
            'statut_scan' => 'present',
            'localisation_reelle_id' => 2,
            'etat_constate' => 'bon_etat',
        ])->assertCreated()
            ->assertJsonPath('scan.statut_scan', 'deplace');

        $this->assertSame(1, $originalLocalisation->fresh()->nombre_biens_scannes);
        $this->assertSame(0, $scanLocalisation->fresh()->nombre_biens_scannes);
    }

    public function test_api_scan_replaces_an_absent_record_without_incrementing_twice(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaireLocalisation->update([
            'statut' => 'en_cours',
            'nombre_biens_scannes' => 1,
        ]);
        $absent = InventaireScan::create([
            'inventaire_id' => $inventaire->id,
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'immobilisation_id' => 101,
            'date_scan' => now()->subMinute(),
            'statut_scan' => 'absent',
            'etat_constate' => 'bon_etat',
            'user_id' => $agent->idUser,
        ]);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson("/api/v1/inventaires/{$inventaire->id}/scan", [
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'bien_id' => 101,
            'localisation_reelle_id' => 1,
            'etat_constate' => 'bon_etat',
        ])->assertCreated()
            ->assertJsonPath('scan.id', $absent->id)
            ->assertJsonPath('scan.statut_scan', 'present');

        $this->assertDatabaseCount('inventaire_scans', 1);
        $this->assertSame(1, $inventaireLocalisation->fresh()->nombre_biens_scannes);
    }

    public function test_api_duplicate_scan_is_reported_as_a_conflict(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaireLocalisation->update(['statut' => 'en_cours']);
        InventaireScan::create([
            'inventaire_id' => $inventaire->id,
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'immobilisation_id' => 101,
            'date_scan' => now(),
            'statut_scan' => 'present',
            'localisation_reelle_id' => 1,
            'etat_constate' => 'bon_etat',
            'user_id' => $agent->idUser,
        ]);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson("/api/v1/inventaires/{$inventaire->id}/scan", [
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'bien_id' => 101,
            'localisation_reelle_id' => 1,
            'etat_constate' => 'bon_etat',
        ])->assertConflict()
            ->assertJsonPath('message', 'Ce bien a déjà été scanné dans cet inventaire');

        $this->assertDatabaseCount('inventaire_scans', 1);
    }

    public function test_emplacement_asset_api_does_not_run_discarded_count_queries(): void
    {
        [$agent] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read']);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/emplacements/1/biens')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('inventaire_localisation.biens_restants', 1);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $countQueries = collect($queries)->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'count(')
        );

        $this->assertCount(0, $countQueries);
    }

    public function test_emplacement_asset_api_uses_the_frozen_reference_and_keeps_absences_retryable(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        DB::table('immobilisations')->where('id', 101)->update(['code_propose' => '218/BUR/001/2026']);
        app(InventaireService::class)->figerReferentiel($inventaire);
        DB::table('immobilisations')->where('id', 101)->update(['site_id' => 2]);
        DB::table('immobilisations')->insert([
            'id' => 103,
            'site_id' => 1,
        ]);
        InventaireScan::create([
            'inventaire_id' => $inventaire->id,
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'immobilisation_id' => 101,
            'date_scan' => now(),
            'statut_scan' => 'absent',
            'etat_constate' => 'bon_etat',
            'user_id' => $agent->idUser,
        ]);
        Sanctum::actingAs($agent, ['inventory:read']);

        $response = $this->getJson('/api/v1/emplacements/1/biens')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('biens.0.num_ordre', 101)
            // Code d'inventaire figé, utilisé par la PWA si un QR ne contient pas de GS<id>.
            ->assertJsonPath('biens.0.code', '218/BUR/001/2026')
            ->assertJsonCount(0, 'biens_deja_scannes')
            ->assertJsonPath('inventaire_localisation.biens_restants', 1);

        $this->assertNotContains(103, collect($response->json('biens'))
            ->pluck('num_ordre')
            ->all());
    }

    public function test_invalid_scan_photo_is_rejected_without_writing_a_file_or_scan(): void
    {
        Storage::fake('public');
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaireLocalisation->update(['statut' => 'en_cours']);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson("/api/v1/inventaires/{$inventaire->id}/scan", [
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'bien_id' => 101,
            'statut_scan' => 'present',
            'localisation_reelle_id' => 1,
            'etat_constate' => 'bon_etat',
            'photo' => 'data:image/png;base64,ceci-n-est-pas-une-image',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('inventaire_scans', 0);
        Storage::disk('public')->assertDirectoryEmpty('photos');
    }

    public function test_oversized_scan_photo_is_rejected_during_request_validation(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaireLocalisation->update(['statut' => 'en_cours']);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson("/api/v1/inventaires/{$inventaire->id}/scan", [
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'bien_id' => 101,
            'statut_scan' => 'present',
            'localisation_reelle_id' => 1,
            'etat_constate' => 'bon_etat',
            'photo' => str_repeat('a', 7_000_001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('inventaire_scans', 0);
    }

    public function test_etat_reference_api_uses_one_query_and_identifies_required_photos(): void
    {
        [$agent] = $this->createAssignedWorkflow();
        DB::table('etats')->insert([
            ['id' => 1, 'etat' => 'Bon état', 'code' => 'BE'],
            ['id' => 2, 'etat' => 'Neuf', 'code' => 'NF'],
            ['id' => 3, 'etat' => 'Défectueuse', 'code' => null],
        ]);
        Sanctum::actingAs($agent, ['inventory:read']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/etats')
            ->assertOk()
            ->assertJsonFragment(['id' => 1, 'require_photo' => false])
            ->assertJsonFragment(['id' => 3, 'require_photo' => true]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }

    public function test_large_inventory_export_is_queued_once(): void
    {
        Queue::fake();
        config()->set('inventory.async_export_threshold', 1);
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $inventaire->update(['statut' => 'termine']);
        $inventaireLocalisation->update(['statut' => 'termine']);

        $this->actingAs($agent)
            ->post("/inventaires/{$inventaire->id}/export-pdf")
            ->assertRedirect("/inventaires/{$inventaire->id}/rapport");

        $this->actingAs($agent)
            ->post("/inventaires/{$inventaire->id}/export-pdf")
            ->assertRedirect("/inventaires/{$inventaire->id}/rapport");

        $this->assertDatabaseCount('inventaire_exports', 1);
        $exportRequest = InventaireExportRequest::query()->sole();
        $this->assertSame('pdf', $exportRequest->format);
        Queue::assertPushed(GenerateInventaireExport::class, 1);
    }

    public function test_closure_queues_permanent_pdf_and_excel_reports_once(): void
    {
        Queue::fake();
        $admin = $this->createUser('report-closer');
        $admin->update(['role' => 'admin']);
        $inventaire = $this->createInventory('termine');
        $inventaire->update([
            'date_fin' => '2025-12-31',
            'finished_at' => '2025-12-31 18:00:00',
        ]);

        app(InventaireService::class)->cloturerInventaire($inventaire, $admin);

        $inventaire->refresh();
        $this->assertSame('cloture', $inventaire->statut);
        $this->assertSame('2025-12-31', $inventaire->date_fin->format('Y-m-d'));
        $this->assertNotNull($inventaire->closed_at);
        $this->assertDatabaseCount('inventaire_exports', 2);
        $this->assertEqualsCanonicalizing(
            ['pdf', 'xlsx'],
            InventaireExportRequest::query()->pluck('format')->all(),
        );
        $this->assertSame(2, InventaireExportRequest::query()->where('automatic', true)->count());
        Queue::assertPushed(GenerateInventaireExport::class, 2);
    }

    public function test_a_defective_asset_requires_a_photo_on_the_server(): void
    {
        [$agent] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [[
                'num_ordre' => 101,
                'etat_constate' => 'defectueux',
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('biens_scannes.0.photo');

        $this->assertDatabaseCount('inventaire_scans', 0);
    }

    public function test_agent_cannot_mutate_the_asset_reference(): void
    {
        $agent = $this->createUser('readonly-reference-agent');

        $this->actingAs($agent)
            ->get('/biens/create')
            ->assertRedirect('/dashboard');

        $this->actingAs($agent)
            ->get('/biens/transfert')
            ->assertRedirect('/dashboard');
    }

    public function test_excel_detail_sheets_do_not_load_report_data_in_their_constructors(): void
    {
        $inventaire = $this->createInventory('termine');

        DB::flushQueryLog();
        DB::enableQueryLog();

        new BiensPresentsSheet($inventaire);
        new BiensDeplacesSheet($inventaire);
        new BiensAbsentsSheet($inventaire);
        new BiensNonScannesSheet($inventaire);
        new LocalisationsSheet($inventaire);
        new PerformanceAgentsSheet($inventaire);
        new MouvementsSheet($inventaire);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_inventory_excel_workbook_can_be_generated(): void
    {
        $inventaire = $this->createInventory('termine');

        $workbook = Excel::raw(new InventaireExport($inventaire), ExcelFormat::XLSX);

        $this->assertNotEmpty($workbook);
        $this->assertStringStartsWith('PK', $workbook);
    }

    public function test_report_sections_reuse_the_same_reference_and_scan_context(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        app(InventaireService::class)->figerReferentiel($inventaire);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [['num_ordre' => 101, 'etat_constate' => 'bon_etat']],
        ])->assertOk();
        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [],
        ])->assertOk();

        $service = app(RapportService::class);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $service->getDetailParSite($inventaire);
        $service->getBiensPresents($inventaire);
        $service->getBiensDeplaces($inventaire);
        $service->getBiensAbsents($inventaire);
        $service->getBiensNonScannes($inventaire);
        $service->getPerformanceLocalisations($inventaire);
        $service->getPerformanceAgents($inventaire);
        $service->getAnalyseMouvements($inventaire);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(22, count($queries));
    }

    public function test_present_asset_report_uses_the_frozen_expected_localisation_for_conformity(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        app(InventaireService::class)->figerReferentiel($inventaire);
        DB::table('localisations')->insert([
            'id' => 2,
            'localisation' => 'Annexe',
            'code' => 'LOC-2',
        ]);
        DB::table('sites')->insert([
            'id' => 3,
            'site' => 'Bureau annexe',
            'code' => 'EMP-3',
            'localisation_id' => 2,
        ]);
        DB::table('immobilisations')->where('id', 101)->update(['site_id' => 3]);
        InventaireScan::create([
            'inventaire_id' => $inventaire->id,
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'immobilisation_id' => 101,
            'date_scan' => now(),
            'statut_scan' => 'present',
            'localisation_reelle_id' => 1,
            'site_reel_id' => 1,
            'etat_constate' => 'bon_etat',
            'user_id' => $agent->idUser,
        ]);

        $bienPresent = app(RapportService::class)->getBiensPresents($inventaire)->sole();

        $this->assertTrue($bienPresent['conforme']);
        $this->assertSame('LOC-1', $bienPresent['localisation']);
    }

    public function test_scan_conformity_accessor_is_stable_and_does_not_lazy_load_current_location(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $scan = InventaireScan::create([
            'inventaire_id' => $inventaire->id,
            'inventaire_localisation_id' => $inventaireLocalisation->id,
            'immobilisation_id' => 101,
            'date_scan' => now(),
            'statut_scan' => 'present',
            'localisation_reelle_id' => 1,
            'site_reel_id' => 1,
            'etat_constate' => 'bon_etat',
            'user_id' => $agent->idUser,
        ]);
        DB::table('localisations')->insert([
            'id' => 2,
            'localisation' => 'Annexe',
            'code' => 'LOC-2',
        ]);
        DB::table('sites')->where('id', 2)->update(['localisation_id' => 2]);
        DB::table('immobilisations')->where('id', 101)->update(['site_id' => 2]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertTrue($scan->is_conforme);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, $queries);
    }

    public function test_anomaly_detection_uses_a_constant_number_of_queries_for_localisations(): void
    {
        $agent = $this->createUser('anomaly-agent');
        $inventaire = $this->createInventory('en_cours');

        foreach (range(1, 6) as $localisationId) {
            DB::table('localisations')->insert([
                'id' => $localisationId,
                'localisation' => "Zone {$localisationId}",
                'code' => "Z-{$localisationId}",
            ]);
            InventaireLocalisation::create([
                'inventaire_id' => $inventaire->id,
                'localisation_id' => $localisationId,
                'statut' => 'en_cours',
                'date_debut_scan' => now()->subDays(2),
                'nombre_biens_attendus' => 10,
                'user_id' => $agent->idUser,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $anomalies = app(InventaireService::class)->detecterAnomalies($inventaire);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(6, $anomalies['localisations_bloquees']);
        $this->assertLessThanOrEqual(8, count($queries));
    }

    public function test_complete_pdf_report_dataset_has_a_bounded_query_count(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        app(InventaireService::class)->figerReferentiel($inventaire);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [['num_ordre' => 101, 'etat_constate' => 'bon_etat']],
        ])->assertOk();
        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [],
        ])->assertOk();
        $inventaire->update(['statut' => 'termine', 'date_fin' => now()]);

        $service = new class extends RapportService
        {
            public function prepare(Inventaire $inventaire): array
            {
                return $this->preparerDonneesRapport($inventaire);
            }
        };

        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = $service->prepare($inventaire->fresh());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(2, $data['statistiques']['total_biens_attendus']);
        $this->assertCount(2, $data['detailParSite']);
        $this->assertLessThanOrEqual(18, count($queries));
    }

    public function test_asset_list_uses_exact_inventory_number_search_and_safe_sorting(): void
    {
        $this->seedAssetListReferenceData();
        DB::table('immobilisations')->insert([
            ['id' => 101, 'designation_id' => 1, 'categorie_id' => 1, 'etat_id' => 1, 'site_id' => 1],
            ['id' => 1101, 'designation_id' => 1, 'categorie_id' => 1, 'etat_id' => 1, 'site_id' => 1],
        ]);

        $component = $this->assetListComponent();
        $component->search = '101';
        $component->sortBy('NumOrdre desc, (select 1)');

        $this->assertSame('id', $component->sortField);
        $this->assertSame([101], $component->queryForTest()->pluck('id')->all());
    }

    public function test_asset_list_rejects_unbounded_page_sizes(): void
    {
        $component = $this->assetListComponent();

        $component->updatedPerPage(100000);
        $this->assertSame(20, $component->perPage);

        $component->updatedPerPage(50);
        $this->assertSame(50, $component->perPage);
    }

    public function test_asset_list_relations_have_a_constant_query_count(): void
    {
        $this->seedAssetListReferenceData();
        DB::table('immobilisations')->insert(array_map(fn (int $number): array => [
            'id' => $number,
            'designation_id' => 1,
            'categorie_id' => 1,
            'etat_id' => 1,
            'site_id' => 1,
        ], range(1, 30)));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $biens = $this->assetListComponent()->queryForTest()->limit(20)->get();

        foreach ($biens as $bien) {
            $bien->code_formate;
            $bien->emplacement?->localisation?->Localisation;
            $bien->emplacement?->affectation?->Affectation;
            $bien->etat?->Etat;
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(20, $biens);
        $this->assertLessThanOrEqual(9, count($queries));
    }

    public function test_reference_option_cache_is_invalidated_after_a_model_update(): void
    {
        DB::table('localisations')->insert([
            'id' => 1,
            'localisation' => 'Ancien siège',
            'code' => 'LOC-1',
        ]);

        $component = new class extends Component
        {
            use WithCachedOptions;

            public function localisationOptions(): array
            {
                return $this->getCachedLocalisationOptions(3600);
            }
        };

        $this->assertSame('LOC-1 - Ancien siège', $component->localisationOptions()[0]['text']);

        Localisation::query()->findOrFail(1)->update(['localisation' => 'Nouveau siège']);

        $this->assertSame('LOC-1 - Nouveau siège', $component->localisationOptions()[0]['text']);
    }

    public function test_detailed_site_cache_is_invalidated_when_its_localisation_changes(): void
    {
        DB::table('localisations')->insert([
            'id' => 1,
            'localisation' => 'Ancien siège',
            'code' => 'LOC-1',
        ]);
        DB::table('sites')->insert([
            'id' => 1,
            'site' => 'Bureau',
            'code' => 'BUR-1',
            'localisation_id' => 1,
        ]);

        $component = new class extends Component
        {
            use WithCachedOptions;

            public function siteOptions(): array
            {
                return $this->getCachedSiteOptions(null, 3600, true);
            }
        };

        $this->assertStringContainsString('Ancien siège', $component->siteOptions()[0]['text']);

        Localisation::query()->findOrFail(1)->update(['localisation' => 'Nouveau siège']);

        $this->assertStringContainsString('Nouveau siège', $component->siteOptions()[0]['text']);
    }

    public function test_shared_table_controls_reject_unsafe_sorting_and_pagination_values(): void
    {
        $component = new class extends Component
        {
            use WithPagination, WithSafeTableControls;

            public $sortField = 'name';

            public $sortDirection = 'asc';

            public $perPage = 20;

            public function sortBy(string $field): void
            {
                $this->changeSort($field, ['name', 'created_at']);
            }

            public function resolvedSort(): array
            {
                return [
                    $this->resolveSortField(['name', 'created_at'], 'name'),
                    $this->resolveSortDirection(),
                ];
            }

            public function resolvedPageSize(): int
            {
                return $this->resolvePageSize();
            }
        };

        $component->sortBy('name desc, (select 1)');
        $this->assertSame('name', $component->sortField);

        $component->sortField = 'invalid-column';
        $component->sortDirection = 'invalid-direction';
        $this->assertSame(['name', 'asc'], $component->resolvedSort());

        $component->updatedPerPage(100000);
        $this->assertSame(20, $component->perPage);
        $this->assertSame(20, $component->resolvedPageSize());

        $component->updatedPerPage(50);
        $this->assertSame(50, $component->perPage);
    }

    public function test_site_list_uses_counts_without_hydrating_immobilisations(): void
    {
        $this->seedAssetListReferenceData();

        foreach (range(1, 5) as $siteId) {
            if ($siteId > 1) {
                DB::table('sites')->insert([
                    'id' => $siteId,
                    'site' => "Bureau {$siteId}",
                    'localisation_id' => 1,
                ]);
            }
            foreach (range(1, 3) as $offset) {
                DB::table('immobilisations')->insert([
                    'id' => (($siteId - 1) * 3) + $offset,
                    'site_id' => $siteId,
                ]);
            }
        }

        $component = new class extends ListeSites
        {
            public function queryForTest(): Builder
            {
                return $this->getSitesQuery();
            }
        };

        DB::flushQueryLog();
        DB::enableQueryLog();
        $sites = $component->queryForTest()->get();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(5, $sites);
        $this->assertSame(3, $sites->first()->immobilisations_count);
        $this->assertFalse($sites->first()->relationLoaded('immobilisations'));
        $this->assertLessThanOrEqual(3, count($queries));
    }

    public function test_user_statistics_are_loaded_with_one_aggregate_query(): void
    {
        $this->createUser('admin-stat')->update(['role' => 'admin']);
        $this->createUser('agent-stat');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $stats = (new UserList)->getStatsProperty();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(['total' => 2, 'admins' => 1, 'agents' => 1], $stats);
        $this->assertCount(1, $queries);
    }

    public function test_invalid_asset_export_ids_are_rejected_instead_of_exporting_everything(): void
    {
        $agent = $this->createUser('export-validation-agent');
        $agent->update(['role' => 'admin']);
        $this->seedAssetListReferenceData();
        DB::table('immobilisations')->insert([
            ['id' => 101, 'site_id' => 1],
            ['id' => 102, 'site_id' => 1],
        ]);

        $this->actingAs($agent)
            ->getJson('/biens/export/excel?ids=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.0');
    }

    public function test_asset_csv_export_streams_only_the_selected_assets(): void
    {
        $agent = $this->createUser('asset-export-agent');
        $agent->update(['role' => 'admin']);
        $this->seedAssetListReferenceData();
        DB::table('immobilisations')->insert([
            ['id' => 101, 'designation_id' => 1, 'categorie_id' => 1, 'etat_id' => 1, 'site_id' => 1],
            ['id' => 102, 'designation_id' => 1, 'categorie_id' => 1, 'etat_id' => 1, 'site_id' => 1],
        ]);

        $response = $this->actingAs($agent)
            ->get('/biens/export/excel?ids=101')
            ->assertOk()
            ->assertStreamed();
        $content = $response->streamedContent();

        $this->assertStringContainsString(';GS101', $content);
        $this->assertStringNotContainsString(';GS102', $content);
    }

    public function test_localisation_csv_export_counts_emplacements_without_n_plus_one_queries(): void
    {
        $agent = $this->createUser('location-export-agent');
        $agent->update(['role' => 'admin']);

        foreach (range(1, 12) as $id) {
            DB::table('localisations')->insert([
                'id' => $id,
                'localisation' => "Zone {$id}",
                'code' => "LOC-{$id}",
            ]);
            DB::table('sites')->insert([
                'id' => $id,
                'site' => "Bureau {$id}",
                'localisation_id' => $id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($agent)
            ->get('/localisations/export/excel')
            ->assertOk()
            ->assertStreamed();
        $content = $response->streamedContent();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertStringContainsString('LOC-12', $content);
        $this->assertLessThanOrEqual(3, count($queries));
    }

    public function test_admin_can_reopen_a_site_finished_by_mistake_and_rescan_it(): void
    {
        [$agent, $inventaire, $inventaireLocalisation] = $this->createAssignedWorkflow();
        $admin = $this->createUser('admin');
        $admin->update(['role' => 'admin']);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', ['biens_scannes' => []])->assertOk();
        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [['num_ordre' => 102, 'etat_constate' => 'bon_etat']],
        ])->assertOk();
        $inventaireSite = InventaireSite::where('site_id', 1)->firstOrFail();
        $this->assertSame('termine', $inventaireLocalisation->fresh()->statut);

        app(SiteScanService::class)->rouvrir($inventaireSite, $admin->fresh());

        $this->assertDatabaseMissing('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
        ]);
        $this->assertDatabaseHas('inventaire_sites', [
            'id' => $inventaireSite->id,
            'statut' => 'en_cours',
            'finalization_key' => null,
            'nombre_biens_scannes' => 0,
        ]);
        $this->assertDatabaseHas('inventaire_localisations', [
            'id' => $inventaireLocalisation->id,
            'statut' => 'en_cours',
            'nombre_biens_scannes' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'site_reopened',
            'auditable_id' => (string) $inventaireSite->id,
        ]);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [['num_ordre' => 101, 'etat_constate' => 'bon_etat']],
        ])->assertOk();

        $this->assertDatabaseHas('inventaire_scans', [
            'inventaire_id' => $inventaire->id,
            'immobilisation_id' => 101,
            'statut_scan' => 'present',
        ]);
        $this->assertSame('termine', $inventaireLocalisation->fresh()->statut);
    }

    public function test_reopening_a_site_puts_a_finished_inventory_back_in_progress(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        $admin = $this->createUser('admin');
        $admin->update(['role' => 'admin']);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);
        $this->postJson('/api/v1/emplacements/1/terminer', ['biens_scannes' => []])->assertOk();
        $inventaire->update(['statut' => 'termine', 'date_fin' => now(), 'finished_at' => now()]);

        app(SiteScanService::class)->rouvrir(InventaireSite::where('site_id', 1)->firstOrFail(), $admin->fresh());

        $inventaire->refresh();
        $this->assertSame('en_cours', $inventaire->statut);
        $this->assertNull($inventaire->finished_at);
    }

    public function test_site_reopening_is_refused_to_agents_and_on_closed_inventories(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        $admin = $this->createUser('admin');
        $admin->update(['role' => 'admin']);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);
        $this->postJson('/api/v1/emplacements/1/terminer', ['biens_scannes' => []])->assertOk();
        $inventaireSite = InventaireSite::where('site_id', 1)->firstOrFail();

        try {
            app(SiteScanService::class)->rouvrir($inventaireSite, $agent);
            $this->fail('Un agent ne doit pas pouvoir rouvrir un site.');
        } catch (AuthorizationException) {
        }

        try {
            app(SiteScanService::class)->rouvrir(InventaireSite::where('site_id', 2)->firstOrFail(), $admin->fresh());
            $this->fail('Un site non terminé ne doit pas pouvoir être rouvert.');
        } catch (InventaireWorkflowException) {
        }

        $inventaire->update(['statut' => 'cloture']);
        try {
            app(SiteScanService::class)->rouvrir($inventaireSite, $admin->fresh());
            $this->fail('Un inventaire clôturé ne doit pas pouvoir être modifié.');
        } catch (InventaireWorkflowException) {
        }

        $this->assertSame('termine', $inventaireSite->fresh()->statut);
        $this->assertDatabaseHas('inventaire_scans', [
            'immobilisation_id' => 101,
            'statut_scan' => 'absent',
        ]);
    }

    public function test_closing_an_inventory_applies_moves_and_observed_states_to_the_register(): void
    {
        Queue::fake();
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        $admin = $this->createUser('admin');
        $admin->update(['role' => 'admin']);
        DB::table('etats')->insert([
            ['id' => 1, 'etat' => 'Bon', 'code' => 'BON'],
            ['id' => 2, 'etat' => 'Neuf', 'code' => 'NEUF'],
        ]);
        DB::table('immobilisations')->update(['etat_id' => 1]);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [
                ['num_ordre' => 101, 'etat_constate' => 'neuf'],
                ['num_ordre' => 102, 'etat_constate' => 'bon_etat'],
            ],
        ])->assertOk();
        $this->postJson('/api/v1/emplacements/1/terminer', ['biens_scannes' => []])->assertOk();

        $service = app(InventaireService::class);
        $service->terminerInventaire($inventaire, $admin->fresh());
        $service->cloturerInventaire($inventaire, $admin->fresh());

        $this->assertDatabaseHas('immobilisations', ['id' => 101, 'site_id' => 2, 'etat_id' => 2]);
        $this->assertDatabaseHas('immobilisations', ['id' => 102, 'site_id' => 2, 'etat_id' => 1]);
        $this->assertDatabaseHas('historique_transferts', [
            'immobilisation_id' => 101,
            'ancien_site_id' => 1,
            'nouveau_site_id' => 2,
            'groupe_transfert_id' => "inventaire-{$inventaire->id}",
        ]);
        $this->assertDatabaseCount('historique_transferts', 1);
    }

    public function test_assigned_localisations_expose_each_site_progress_to_the_pwa(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', [
            'biens_scannes' => [['num_ordre' => 101, 'etat_constate' => 'bon_etat']],
        ])->assertOk();

        $sites = collect($this->getJson("/api/v1/inventaires/{$inventaire->id}/mes-localisations")
            ->assertOk()
            ->json('localisations.0.localisation.emplacements'))
            ->keyBy('id');

        $this->assertSame('termine', $sites[1]['statut']);
        $this->assertSame(1, $sites[1]['nombre_biens_scannes']);
        $this->assertSame('en_attente', $sites[2]['statut']);
        $this->assertSame(0, $sites[2]['nombre_biens_scannes']);
    }

    public function test_agent_statistics_count_physical_scans_only(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        $this->postJson('/api/v1/emplacements/1/terminer', ['biens_scannes' => []])->assertOk();
        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [['num_ordre' => 102, 'etat_constate' => 'bon_etat']],
        ])->assertOk();

        $parAgent = app(InventaireService::class)->calculerStatistiques($inventaire->fresh())['par_agent'];

        // 101 a été déclaré absent à la fin du site 1 : ce n'est pas un scan de l'agent.
        $this->assertCount(1, $parAgent);
        $this->assertSame(1, $parAgent[0]['biens_scannes']);
        $this->assertSame(1, $parAgent[0]['localisations']);
    }

    public function test_inventory_dashboard_lists_the_scanned_assets_with_filters(): void
    {
        [$agent, $inventaire] = $this->createAssignedWorkflow();
        DB::table('immobilisations')->where('id', 101)->update(['code_propose' => '218/BUR/001/2026']);
        DB::table('immobilisations')->where('id', 102)->update(['code_propose' => '218/BUR/002/2026']);
        app(InventaireService::class)->figerReferentiel($inventaire);
        Sanctum::actingAs($agent, ['inventory:read', 'inventory:scan']);

        // 101 est trouvé au site 2 (déplacé), 102 n'est pas vu (absent).
        $this->postJson('/api/v1/emplacements/2/terminer', [
            'biens_scannes' => [['num_ordre' => 101, 'etat_constate' => 'neuf']],
        ])->assertOk();

        $admin = $this->createUser('admin-detail');
        $admin->update(['role' => 'admin']);
        $this->actingAs($admin);
        $composant = new DashboardInventaire;
        $composant->inventaire = $inventaire->fresh();

        $scannes = $composant->getScansDetailProperty();
        $this->assertSame([101], $scannes->pluck('immobilisation_id')->all());
        $this->assertSame('deplace', $scannes->first()->statut_scan);
        $this->assertSame('218/BUR/001/2026', $scannes->first()->code_fige);
        $this->assertSame('Bureau 1', $scannes->first()->site_attendu);

        $composant->scanStatut = 'tous';
        $this->assertSame(2, $composant->getScansDetailProperty()->total());

        $composant->scanRecherche = '218/BUR/002';
        $this->assertSame([102], $composant->getScansDetailProperty()->pluck('immobilisation_id')->all());

        $composant->scanRecherche = 'GS101';
        $this->assertSame([101], $composant->getScansDetailProperty()->pluck('immobilisation_id')->all());

        $composant->scanRecherche = '';
        $composant->scanEtat = 'neuf';
        $this->assertSame([101], $composant->getScansDetailProperty()->pluck('immobilisation_id')->all());
    }

    private function createAssignedWorkflow(): array
    {
        $agent = $this->createUser('agent');
        DB::table('localisations')->insert([
            'id' => 1,
            'localisation' => 'Siège',
            'code' => 'LOC-1',
        ]);
        DB::table('sites')->insert([
            ['id' => 1, 'site' => 'Bureau 1', 'code' => 'EMP-1', 'localisation_id' => 1],
            ['id' => 2, 'site' => 'Bureau 2', 'code' => 'EMP-2', 'localisation_id' => 1],
        ]);
        DB::table('immobilisations')->insert([
            ['id' => 101, 'site_id' => 1],
            ['id' => 102, 'site_id' => 2],
        ]);

        $inventaire = $this->createInventory('en_cours');
        $inventaireLocalisation = InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 1,
            'statut' => 'en_attente',
            'nombre_biens_attendus' => 2,
            'user_id' => $agent->idUser,
        ]);
        $inventaireLocalisation->agents()->attach($agent->idUser);

        foreach ([1, 2] as $emplacementId) {
            InventaireSite::create([
                'inventaire_localisation_id' => $inventaireLocalisation->id,
                'site_id' => $emplacementId,
                'statut' => 'en_attente',
                'nombre_biens_attendus' => 1,
            ]);
        }

        return [$agent, $inventaire, $inventaireLocalisation];
    }

    private function assetListComponent(): ListeBiens
    {
        return new class extends ListeBiens
        {
            public function queryForTest(): Builder
            {
                return $this->getBiensQuery();
            }
        };
    }

    private function seedAssetListReferenceData(): void
    {
        DB::table('localisations')->insert([
            'id' => 1,
            'localisation' => 'Siège',
            'code' => 'LOC-1',
        ]);
        DB::table('sites')->insert([
            'id' => 1,
            'site' => 'Bureau',
            'code' => 'EMP-1',
            'localisation_id' => 1,
        ]);
        DB::table('designations')->insert([
            'id' => 1,
            'designation' => 'Chaise',
            'code' => 'CHAISE',
        ]);
        DB::table('categories')->insert([
            'id' => 1,
            'categorie' => 'Mobilier',
            'code' => 'MOB',
        ]);
        DB::table('etats')->insert([
            'id' => 1,
            'etat' => 'Bon état',
            'code' => 'BON',
        ]);
    }

    private function seedAssetCodeReferenceData(): void
    {
        DB::table('designations')->insert([
            'id' => 1,
            'designation' => 'Chaise',
            'code' => 'CHAISE',
        ]);
        DB::table('categories')->insert([
            'id' => 1,
            'categorie' => 'Mobilier',
            'code' => 'MOB',
        ]);
        DB::table('etats')->insert([
            'id' => 1,
            'etat' => 'Bon état',
            'code' => 'BON',
        ]);
    }

    private function createInventory(string $status): Inventaire
    {
        return Inventaire::create([
            'annee' => 2025,
            'date_debut' => now(),
            'statut' => $status,
        ]);
    }

    private function createUser(string $username): User
    {
        return User::create([
            'users' => $username,
            'mdp' => 'MotDePasse123',
            'role' => 'agent',
        ]);
    }
}
