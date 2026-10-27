<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\RequirementTemplate;
use App\Models\User;
use App\Services\Checklist;
use Database\Seeders\SankalpaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequirementImageTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(SankalpaDemoSeeder::class);
        $this->project = Project::sole();
        $this->client = User::factory()->create();
        $this->client->forceFill(['role' => User::ROLE_CLIENT, 'client_id' => $this->project->client_id])->save();
    }

    private function url(string $key): string
    {
        return "/proyectos/{$this->project->id}/requisitos/{$key}/imagen";
    }

    public function test_client_uploads_replaces_and_removes_a_photo(): void
    {
        $this->actingAs($this->client)
            ->post($this->url('s1f'), ['image' => UploadedFile::fake()->image('portada.jpg', 1200, 800)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('groups.0.done', 5)
            ->assertJsonPath('image_url', fn ($u) => str_contains($u, '/requisitos/s1f/imagen?v='));

        $first = ProjectRequirement::where('template_key', 's1f')->value('value');
        $this->assertStringStartsWith("projects/{$this->project->id}/s1f-", $first);
        Storage::disk('local')->assertExists($first);
        $this->get($this->url('s1f'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Reemplazar borra la anterior.
        $this->post($this->url('s1f'), ['image' => UploadedFile::fake()->image('otra.png')], ['Accept' => 'application/json'])->assertOk();
        $second = ProjectRequirement::where('template_key', 's1f')->value('value');
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        // Quitar.
        $this->delete($this->url('s1f'), [], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('groups.0.done', 4);
        Storage::disk('local')->assertMissing($second);
        $this->assertFalse((bool) ProjectRequirement::where('template_key', 's1f')->value('done'));
        $this->get($this->url('s1f'))->assertNotFound();
    }

    public function test_without_javascript_the_form_upload_redirects_back(): void
    {
        $this->actingAs($this->client)->from('/mi-panel')
            ->post($this->url('b1i'), ['image' => UploadedFile::fake()->image('entrada.webp')])
            ->assertRedirect('/mi-panel')
            ->assertSessionHas('status', 'Foto guardada.');
        $this->assertTrue((bool) ProjectRequirement::where('template_key', 'b1i')->value('done'));
    }

    public function test_only_images_and_only_image_fields(): void
    {
        $this->actingAs($this->client);

        $this->post($this->url('s1f'), ['image' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.image.0', 'La foto tiene que ser JPG, PNG o WebP.');
        $this->post($this->url('s1f'), ['image' => UploadedFile::fake()->image('enorme.jpg')->size(11000)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.image.0', 'La foto pesa más de 10 MB.');
        $this->post($this->url('s1t'), ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertUnprocessable(); // el título no es una imagen
        $this->post($this->url('waN'), ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertUnprocessable(); // widget no elegido

        // Un campo de imagen no acepta texto.
        $this->putJson("/proyectos/{$this->project->id}/requisitos", ['key' => 's1f', 'value' => 'foto.jpg'])->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_photos_are_private_to_the_project(): void
    {
        $this->actingAs($this->client)->post($this->url('s1f'), ['image' => UploadedFile::fake()->image('p.jpg')], ['Accept' => 'application/json']);

        $other = User::factory()->create();
        $other->forceFill(['role' => User::ROLE_CLIENT, 'client_id' => Client::create(['name' => 'Otro'])->id])->save();
        $this->actingAs($other)->get($this->url('s1f'))->assertForbidden();
        $this->actingAs($other)->post($this->url('s1f'), ['image' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])->assertForbidden();

        $agency = User::factory()->create();
        $agency->forceFill(['role' => User::ROLE_AGENCY])->save();
        $this->actingAs($agency)->get($this->url('s1f'))->assertOk();

        $this->post('/salir');
        $this->get($this->url('s1f'))->assertRedirect(route('login'));
    }

    public function test_panel_shows_upload_buttons_and_thumbnail(): void
    {
        $this->actingAs($this->client)->get('/mi-panel')->assertSee('Subir foto')->assertDontSee('data-img-box >', false);

        $this->post($this->url('s1f'), ['image' => UploadedFile::fake()->image('p.jpg')], ['Accept' => 'application/json']);

        $this->get('/mi-panel')->assertSee('Cambiar foto')->assertSee('/requisitos/s1f/imagen?v=', false);
        $this->assertTrue(Checklist::isStoredImage(ProjectRequirement::where('template_key', 's1f')->value('value')));
    }

    public function test_cropped_photo_keeps_original_and_checks_format(): void
    {
        $agency = User::factory()->create();
        $agency->forceFill(['role' => User::ROLE_AGENCY])->save();

        // Recortada a 7:5 desde el navegador, con el original.
        $this->actingAs($this->client)->post($this->url('s1f'), [
            'image' => UploadedFile::fake()->image('bloque.jpg', 1400, 1000),
            'original' => UploadedFile::fake()->image('bloque-original.jpg', 3000, 2000),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('aspect_ok', true);

        $path = ProjectRequirement::where('template_key', 's1f')->value('value');
        $original = Checklist::originalPath($path);
        $this->assertNotNull($original);
        Storage::disk('local')->assertExists($original);
        $this->get($this->url('s1f').'?original=1')->assertOk();

        // La agencia ve el enlace al original; el cliente no.
        $this->actingAs($agency)->get("/agencia/proyectos/{$this->project->id}")->assertSee('Ver la foto original, sin recortar');
        $this->actingAs($this->client)->get('/mi-panel')->assertDontSee('Ver la foto original')->assertSee('Formato 7:5 (1400 × 1000 px)');

        // Subida sin recortar (sin JavaScript): se guarda pero avisa del formato.
        $this->post($this->url('s1m'), ['image' => UploadedFile::fake()->image('ancha.jpg', 1600, 900)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('aspect_ok', false);
        $this->get('/mi-panel')->assertSee('Esta foto no tiene el formato 4:5.');

        // Quitar borra la recortada y el original.
        $this->delete($this->url('s1f'), [], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertMissing($original);
    }

    public function test_catalog_formats(): void
    {
        $aspects = RequirementTemplate::where('type', 'image')->pluck('aspect', 'key')->all();

        $this->assertSame(['bnd' => '16:9', 'bnm' => '4:5', 's1f' => '7:5', 's1m' => '4:5', 'b1i' => '16:9'],
            array_intersect_key($aspects, array_flip(['bnd', 'bnm', 's1f', 's1m', 'b1i'])));
        $this->assertCount(10, $aspects);
    }
}
