<?php

namespace Tests\Feature;

use App\Http\Middleware\CanManageInventaire;
use App\Http\Middleware\CheckSessionTimeout;
use App\Http\Middleware\IsAdmin;
use App\Models\Inventaire;
use App\Models\InventaireLocalisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use CreatesLaxSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();
    }

    public function test_security_headers_are_applied_to_web_responses(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_pwa_uses_local_bundled_dependencies(): void
    {
        $response = $this->get('/pwa/index-v2.html');

        $response
            ->assertOk()
            ->assertSee('pwa-dependencies-', false)
            ->assertSee('connectivity-status', false)
            ->assertDontSee('cdn.tailwindcss.com', false)
            ->assertDontSee('cdn.jsdelivr.net', false);
    }

    public function test_service_workers_do_not_delete_or_intercept_each_others_caches(): void
    {
        $mainWorker = file_get_contents(public_path('sw.js'));
        $scannerWorker = file_get_contents(public_path('pwa/service-worker.js'));

        $this->assertStringContainsString("cacheName.startsWith('inventaire-pro-')", $mainWorker);
        $this->assertStringContainsString("url.pathname.startsWith('/pwa/')", $mainWorker);
        $this->assertStringContainsString("cacheName.startsWith('inventaire-scanner-')", $scannerWorker);
    }

    public function test_main_web_manifest_only_references_existing_assets_and_routes(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        foreach ($manifest['shortcuts'] as $shortcut) {
            foreach ($shortcut['icons'] ?? [] as $icon) {
                $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            }
        }

        $shortcutUrls = collect($manifest['shortcuts'])->pluck('url')->all();

        $this->assertContains(route('dashboard', absolute: false), $shortcutUrls);
        $this->assertContains(route('biens.index', absolute: false), $shortcutUrls);
        $this->assertContains(route('localisations.index', absolute: false), $shortcutUrls);
    }

    public function test_custom_authorization_middleware_remains_active_during_livewire_updates(): void
    {
        $persistentMiddleware = app(PersistentMiddleware::class)->getPersistentMiddleware();

        $this->assertContains(IsAdmin::class, $persistentMiddleware);
        $this->assertContains(CanManageInventaire::class, $persistentMiddleware);
        $this->assertContains(CheckSessionTimeout::class, $persistentMiddleware);
    }

    public function test_user_passwords_are_hashed_by_the_model(): void
    {
        $user = User::create([
            'users' => 'agent-securise',
            'mdp' => 'MotDePasseSecurise123',
            'role' => 'agent',
        ]);

        $storedPassword = DB::table('users')->where('idUser', $user->idUser)->value('mdp');

        $this->assertNotSame('MotDePasseSecurise123', $storedPassword);
        $this->assertTrue(Hash::check('MotDePasseSecurise123', $storedPassword));
    }

    public function test_api_login_issues_a_scoped_expiring_token(): void
    {
        User::create([
            'users' => 'scanner',
            'mdp' => 'MotDePasseSecurise123',
            'role' => 'agent',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'users' => 'scanner',
            'mdp' => 'MotDePasseSecurise123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.users', 'scanner')
            ->assertJsonStructure(['token', 'user']);

        $token = PersonalAccessToken::query()->sole();

        $this->assertSame(['inventory:read', 'inventory:scan'], $token->abilities);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_api_login_rejects_a_legacy_plaintext_password(): void
    {
        DB::table('users')->insert([
            'users' => 'legacy',
            'mdp' => 'mot-de-passe-en-clair',
            'role' => 'agent',
        ]);

        $this->postJson('/api/v1/login', [
            'users' => 'legacy',
            'mdp' => 'mot-de-passe-en-clair',
        ])->assertUnprocessable();
    }

    public function test_agent_cannot_read_an_unassigned_localisation(): void
    {
        $agent = User::create([
            'users' => 'agent-non-assigne',
            'mdp' => 'MotDePasseSecurise123',
            'role' => 'agent',
        ]);

        DB::table('localisations')->insert([
            'id' => 10,
            'localisation' => 'Zone réservée',
            'code' => 'ZONE-10',
        ]);
        DB::table('sites')->insert([
            'id' => 10,
            'site' => 'Bureau réservé',
            'code' => 'EMP-10',
            'localisation_id' => 10,
        ]);
        $inventaire = Inventaire::create([
            'annee' => 2025,
            'date_debut' => now(),
            'statut' => 'en_cours',
        ]);
        InventaireLocalisation::create([
            'inventaire_id' => $inventaire->id,
            'localisation_id' => 10,
            'statut' => 'en_attente',
        ]);

        Sanctum::actingAs($agent, ['inventory:read']);

        $this->getJson('/api/v1/emplacements/10/biens')->assertForbidden();
    }
}
