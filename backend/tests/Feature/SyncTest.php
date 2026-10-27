<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Signal;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\SankalpaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SankalpaDemoSeeder::class);
        $this->site = Project::sole()->sites()->make(['url' => 'https://sankalpa.test']);
        $this->token = $this->site->issueToken();
    }

    /** Cuerpo igual al que manda el plugin v0.2.0. */
    private function body(array $signals, ?int $siteId = null): array
    {
        return [
            'site_id' => (string) ($siteId ?? $this->site->id),
            'site' => 'https://sankalpa.test',
            'plugin' => '0.2.0',
            'wp' => '7.1.3',
            'signals' => $signals,
        ];
    }

    private function sync(array $signals, ?string $token = null, ?int $siteId = null)
    {
        return $this->withToken($token ?? $this->token)->postJson('/api/v1/sync', $this->body($signals, $siteId));
    }

    public function test_sync_marks_requirements_and_returns_progress(): void
    {
        $this->sync([
            'blog_entradas' => ['ok' => true, 'detail' => '2 completas'],
            'paginas_legales' => ['ok' => false, 'detail' => 'privacidad: sí, condiciones: no'],
            'tienda_productos' => ['ok' => true, 'detail' => '12 productos'],
            'tienda_pasarela' => ['ok' => true, 'detail' => 'activo'],
            'reels_plugin' => ['ok' => false, 'detail' => 'no instalado'],
        ])
            ->assertOk()
            // 7 cargados + 4 del blog (b1t y b2t ya estaban) + 1 productos + 2 pasarela = 14 de 36
            ->assertJsonPath('progress', 39)
            ->assertJsonPath('items.1', ['label' => 'Blog (6 de 6)', 'status' => 'done'])
            ->assertJsonPath('items.3', ['label' => 'Tienda (WooCommerce) (3 de 4)', 'status' => 'pending'])
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Faltan 22 puntos'));

        $this->assertSame('plugin', ProjectRequirement::where('template_key', 'wcc')->value('source'));
        $this->assertFalse((bool) ProjectRequirement::where('template_key', 'mkp')->value('done'));
        $this->assertSame(5, Signal::count());
        $this->assertNotNull($this->site->fresh()->last_sync_at);
    }

    public function test_wrong_or_missing_token_is_rejected(): void
    {
        $this->sync(['blog_entradas' => ['ok' => true]], 'otro')->assertUnauthorized();
        $this->postJson('/api/v1/sync', $this->body([]))->assertUnauthorized();
        $this->assertSame(0, Signal::count());
    }

    public function test_site_id_must_match_the_token(): void
    {
        $this->sync(['blog_entradas' => ['ok' => true]], siteId: 999)->assertForbidden();
    }

    public function test_only_flags_and_counts_are_accepted(): void
    {
        $this->sync(['blog_entradas' => ['ok' => 'quizás']])->assertUnprocessable();
        $this->sync(['blog_entradas' => ['ok' => true, 'clave_mp' => 'APP_USR-123']])->assertUnprocessable();
        $this->sync(['../raro' => ['ok' => true]])->assertUnprocessable();
        $this->assertSame(0, Signal::count());
    }

    public function test_empty_and_unknown_signals_are_fine(): void
    {
        $this->sync([])->assertOk()->assertJsonPath('progress', 19);
        $this->sync(['senal_futura' => ['ok' => true, 'detail' => '']])->assertOk();
    }

    public function test_old_signals_are_pruned(): void
    {
        $this->travel(-100)->days();
        $this->sync(['reels_plugin' => ['ok' => false, 'detail' => 'no instalado']]);
        $this->travelBack();

        $this->sync(['reels_plugin' => ['ok' => true, 'detail' => 'activo']]);

        $this->assertSame(['activo'], Signal::pluck('detail')->all());
    }

    public function test_agency_registers_site_and_sees_token_once(): void
    {
        $agency = User::factory()->create();
        $agency->forceFill(['role' => User::ROLE_AGENCY])->save();
        $project = Project::sole();

        $this->actingAs($agency)->post("/agencia/proyectos/{$project->id}/sitio", ['url' => 'http://inseguro.test'])
            ->assertSessionHasErrors('url');

        $response = $this->actingAs($agency)->post("/agencia/proyectos/{$project->id}/sitio", ['url' => 'https://sankalpa.com.ar/']);
        $token = session('site_token');
        $response->assertSessionHas('status');

        $site = Site::where('project_id', $project->id)->sole(); // reemplaza al del setUp
        $this->assertSame('https://sankalpa.com.ar', $site->url);
        $this->assertSame(hash('sha256', $token), $site->token_hash);
        $this->sync([], $this->token)->assertUnauthorized(); // el token anterior ya no sirve
        $this->withToken($token)->postJson('/api/v1/sync', $this->body([], $site->id))->assertOk();

        // Se muestra en la pantalla que sigue a generarlo, y nunca más.
        $this->actingAs($agency)->get("/agencia/proyectos/{$project->id}")->assertSee($token);
        $this->actingAs($agency)->get("/agencia/proyectos/{$project->id}")
            ->assertSee('https://sankalpa.com.ar')
            ->assertDontSee($token);
    }

    public function test_agency_downloads_plugin_zip_and_others_cannot(): void
    {
        $agency = User::factory()->create();
        $agency->forceFill(['role' => User::ROLE_AGENCY])->save();

        $response = $this->actingAs($agency)->get('/agencia/plugin/misioweb-progreso.zip')->assertOk();
        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertNotFalse($zip->locateName('misioweb-progreso/misioweb-progreso.php'));
        $this->assertNotFalse($zip->locateName('misioweb-progreso/uninstall.php'));
        $zip->close();

        $client = User::factory()->create();
        $client->forceFill(['role' => User::ROLE_CLIENT])->save();
        $this->actingAs($client)->get('/agencia/plugin/misioweb-progreso.zip')->assertRedirect(route('client.home'));
    }
}
