<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

/**
 * Chaque page de l'administration doit s'afficher (erreur Blade, composant Livewire
 * à plusieurs éléments racines, route manquante…).
 */
class PagesAdministrationTest extends TestCase
{
    use CreatesLaxSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();
    }

    public function test_every_admin_page_renders(): void
    {
        $admin = User::create(['users' => 'admin-pages', 'mdp' => 'MotDePasse123', 'role' => 'admin']);

        foreach ([
            '/dashboard',
            '/profil',
            '/biens',
            '/biens/create',
            '/biens/transfert',
            '/biens/transfert/historique',
            '/localisations',
            '/localisations/create',
            '/sites',
            '/sites/create',
            '/categories',
            '/categories/create',
            '/designations',
            '/designations/create',
            '/qrcodes/sites',
            '/inventaires',
            '/inventaires/create',
            '/users',
            '/users/create',
            '/users/roles',
        ] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_manager_can_access_operations_but_not_user_management(): void
    {
        $manager = User::create([
            'users' => 'gestionnaire-pages',
            'mdp' => 'MotDePasse123',
            'role' => 'gestionnaire',
        ]);

        foreach ([
            '/dashboard',
            '/profil',
            '/biens',
            '/biens/create',
            '/localisations/create',
            '/sites',
            '/categories',
            '/designations',
            '/qrcodes/sites',
            '/inventaires',
            '/inventaires/create',
        ] as $page) {
            $this->actingAs($manager)->get($page)->assertOk();
        }

        foreach (['/users', '/users/create', '/users/roles'] as $page) {
            $this->actingAs($manager)->get($page)
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_admin_can_create_each_supported_user_role(): void
    {
        $admin = User::create([
            'users' => 'admin-creation',
            'mdp' => 'MotDePasse123',
            'role' => 'admin',
        ]);

        foreach (['agent', 'gestionnaire', 'admin'] as $role) {
            Livewire::actingAs($admin)
                ->test(\App\Livewire\Users\FormUser::class)
                ->set('users', "nouveau-{$role}")
                ->set('mdp', 'NouveauPasse123')
                ->set('mdp_confirmation', 'NouveauPasse123')
                ->set('role', $role)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect(route('users.index'));

            $user = User::where('users', "nouveau-{$role}")->firstOrFail();
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check('NouveauPasse123', $user->mdp));
        }
    }
}
