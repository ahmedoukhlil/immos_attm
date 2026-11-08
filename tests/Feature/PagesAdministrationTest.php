<?php

namespace Tests\Feature;

use App\Models\User;
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
}
