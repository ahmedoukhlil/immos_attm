<?php

namespace Tests\Feature;

use App\Livewire\Biens\DetailBien;
use App\Models\Immobilisation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class ImmobilisationPhotoTest extends TestCase
{
    use CreatesLaxSchema;

    private Immobilisation $bien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();

        DB::table('localisations')->insert(['id' => 1, 'localisation' => 'Siège', 'code' => 'SIEGE']);
        DB::table('sites')->insert(['id' => 1, 'localisation_id' => 1, 'site' => 'Atelier', 'responsable' => null]);
        DB::table('categories')->insert(['id' => 1, 'categorie' => 'Matériel', 'code' => 'MAT', 'imputation_comptable' => '21800000']);
        DB::table('designations')->insert(['id' => 1, 'designation' => 'Véhicule', 'code' => 'VEH', 'categorie_id' => 1]);
        DB::table('etats')->insert(['id' => 1, 'code' => 'BON', 'etat' => 'Bon']);

        $this->bien = Immobilisation::create([
            'site_id' => 1,
            'categorie_id' => 1,
            'designation_id' => 1,
            'etat_id' => 1,
            'annee_acquisition' => 2026,
        ]);
    }

    public function test_an_admin_can_add_replace_and_delete_a_photo(): void
    {
        Storage::fake('public');
        $admin = User::create(['users' => 'admin-photo', 'mdp' => 'MotDePasse123', 'role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(DetailBien::class, ['bien' => $this->bien])
            ->set('nouvellePhoto', UploadedFile::fake()->image('bien.jpg', 900, 600))
            ->call('enregistrerPhoto')
            ->assertHasNoErrors();

        $premierChemin = $this->bien->fresh()->photo_path;
        $this->assertNotNull($premierChemin);
        Storage::disk('public')->assertExists($premierChemin);

        Livewire::actingAs($admin)
            ->test(DetailBien::class, ['bien' => $this->bien->fresh()])
            ->set('nouvellePhoto', UploadedFile::fake()->image('remplacement.png', 800, 800))
            ->call('enregistrerPhoto')
            ->assertHasNoErrors();

        $secondChemin = $this->bien->fresh()->photo_path;
        $this->assertNotSame($premierChemin, $secondChemin);
        Storage::disk('public')->assertMissing($premierChemin);
        Storage::disk('public')->assertExists($secondChemin);

        Livewire::actingAs($admin)
            ->test(DetailBien::class, ['bien' => $this->bien->fresh()])
            ->call('supprimerPhoto')
            ->assertHasNoErrors();

        $this->assertNull($this->bien->fresh()->photo_path);
        Storage::disk('public')->assertMissing($secondChemin);
    }

    public function test_an_agent_cannot_modify_the_asset_photo(): void
    {
        Storage::fake('public');
        $agent = User::create(['users' => 'agent-photo', 'mdp' => 'MotDePasse123', 'role' => 'agent']);
        Storage::disk('public')->put('immobilisations/1/photo.jpg', 'photo');
        $this->bien->update(['photo_path' => 'immobilisations/1/photo.jpg']);

        Livewire::actingAs($agent)
            ->test(DetailBien::class, ['bien' => $this->bien->fresh()])
            ->call('supprimerPhoto')
            ->assertForbidden();

        $this->assertSame('immobilisations/1/photo.jpg', $this->bien->fresh()->photo_path);
        Storage::disk('public')->assertExists('immobilisations/1/photo.jpg');
    }

    public function test_the_photo_must_be_a_supported_image_under_five_megabytes(): void
    {
        Storage::fake('public');
        $manager = User::create(['users' => 'gestionnaire-photo', 'mdp' => 'MotDePasse123', 'role' => 'gestionnaire']);

        Livewire::actingAs($manager)
            ->test(DetailBien::class, ['bien' => $this->bien])
            ->set('nouvellePhoto', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
            ->assertHasErrors(['nouvellePhoto']);

        $this->assertNull($this->bien->fresh()->photo_path);
    }
}
