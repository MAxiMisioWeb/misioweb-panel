<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\User;
use App\Services\Checklist;
use App\Services\SignalMapper;
use Database\Seeders\SankalpaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $client;

    private User $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SankalpaDemoSeeder::class);
        $this->project = Project::sole();

        $this->client = User::factory()->create();
        $this->client->forceFill(['role' => User::ROLE_CLIENT, 'client_id' => $this->project->client_id])->save();
        $this->agency = User::factory()->create();
        $this->agency->forceFill(['role' => User::ROLE_AGENCY])->save();
    }

    private function checklist(): Checklist
    {
        return app(Checklist::class);
    }

    public function test_groups_follow_the_prototype(): void
    {
        $groups = $this->checklist()->groups($this->project);

        $this->assertSame(['sec', 'blog', 'ig', 'wc', 'cal', 'ev', 'mk'], array_column($groups, 'key'));
        $sec = $groups[0];
        $this->assertSame([4, 9], [$sec['done'], $sec['total']]);
        $this->assertContains('Bloque 1 · Foto', $sec['missing']);
        $this->assertSame(['done' => 7, 'total' => 31, 'percent' => 23], Checklist::totals($groups));
    }

    public function test_text_is_done_when_not_empty_and_check_when_marked(): void
    {
        $c = $this->checklist();

        $this->assertTrue($c->update($this->project, 's2d', '  Clases grupales ')->done);
        $this->assertSame('Clases grupales', ProjectRequirement::where('template_key', 's2d')->value('value'));
        $this->assertFalse($c->update($this->project, 's2d', '   ')->done);
        $this->assertNull(ProjectRequirement::where('template_key', 's2d')->value('value'));

        $this->assertTrue($c->update($this->project, 'mkl', true)->done);
        $this->assertTrue($c->update($this->project, 'mkl', '1')->done);
        $this->assertFalse($c->update($this->project, 'mkl', '0')->done);
    }

    public function test_manual_edit_takes_over_a_plugin_mark(): void
    {
        app(SignalMapper::class)->apply($this->project, ['reels_plugin' => ['ok' => true]]);
        $this->assertSame('plugin', ProjectRequirement::where('template_key', 'igp')->value('source'));

        $this->checklist()->update($this->project, 'igp', '1');

        $this->assertSame('manual', ProjectRequirement::where('template_key', 'igp')->value('source'));
    }

    public function test_requirements_of_unchosen_widgets_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->checklist()->update($this->project, 'waN', '+54 376 4000000'); // WhatsApp no elegido
    }

    public function test_message_lists_what_is_missing_like_the_prototype(): void
    {
        $msg = $this->checklist()->message('Sankalpa', $this->checklist()->groups($this->project));

        $this->assertStringStartsWith("Hola! Para avanzar con la web de Sankalpa nos falta:\n\n*Secciones de la portada*\n- Bloque 1 · Foto\n", $msg);
        $this->assertStringContainsString("*Marca y textos legales*\n- Logo y elementos de marca entregados\n", $msg);
        $this->assertStringNotContainsString('Bloque 1 · Título', $msg); // ya cargado
        $this->assertStringEndsWith("\nCon eso podemos seguir. Gracias!", $msg);
    }

    public function test_client_saves_field_by_field_with_json(): void
    {
        $this->actingAs($this->client)
            ->putJson("/proyectos/{$this->project->id}/requisitos", ['key' => 's2d', 'value' => 'Clases grupales'])
            ->assertOk()
            ->assertJsonPath('totals', ['done' => 8, 'total' => 31, 'percent' => 26])
            ->assertJsonPath('groups.0.key', 'sec')
            ->assertJsonPath('groups.0.done', 5)
            ->assertJsonPath('done', fn ($keys) => in_array('s2d', $keys, true));
    }

    public function test_section_form_saves_everything_without_javascript(): void
    {
        $this->actingAs($this->client)->from('/mi-panel')
            ->put("/proyectos/{$this->project->id}/requisitos", ['values' => ['mkl' => '1', 'mkp' => '0', 'mkw' => '+54 376 4000000']])
            ->assertRedirect('/mi-panel')
            ->assertSessionHas('status', 'Cambios guardados.');

        $this->assertSame(['mkl' => true, 'mkp' => false, 'mkw' => true],
            ProjectRequirement::whereIn('template_key', ['mkl', 'mkp', 'mkw'])->pluck('done', 'template_key')->sortKeys()->all());
    }

    public function test_client_cannot_edit_another_clients_project(): void
    {
        $other = User::factory()->create();
        $other->forceFill(['role' => User::ROLE_CLIENT, 'client_id' => Client::create(['name' => 'Otro'])->id])->save();

        $this->actingAs($other)->putJson("/proyectos/{$this->project->id}/requisitos", ['key' => 's2d', 'value' => 'x'])->assertForbidden();
        $this->actingAs($other)->get("/agencia/proyectos/{$this->project->id}")->assertRedirect(route('client.home'));
    }

    public function test_client_panel_shows_sections_and_progress(): void
    {
        $this->actingAs($this->client)->get('/mi-panel')
            ->assertOk()
            ->assertSee('Secciones de la portada')
            ->assertSee('4 de 9')
            ->assertSee('Faltan')
            ->assertSee('23%')
            ->assertDontSee('Estado del trabajo (interno)')
            ->assertDontSee('WhatsApp con código de país'); // widget no elegido
    }

    public function test_agency_project_page(): void
    {
        $this->actingAs($this->agency)->get("/agencia/proyectos/{$this->project->id}")
            ->assertOk()
            ->assertSee('Podemos trabajar hoy')
            ->assertSee('Esperando al cliente')
            ->assertSee('Estado del trabajo (interno)')
            ->assertSee('$457.470')
            ->assertSee('Para avanzar con la web de Sankalpa nos falta');
    }

    public function test_agency_sets_work_status_updates_proposal_and_publishes(): void
    {
        $this->actingAs($this->agency)
            ->putJson("/agencia/proyectos/{$this->project->id}/secciones/blog", ['work_status' => 'doing'])->assertOk();
        $this->actingAs($this->agency)
            ->putJson("/agencia/proyectos/{$this->project->id}/secciones/blog", ['work_status' => 'otra'])->assertStatus(422);
        $this->assertSame('doing', $this->checklist()->groups($this->project)[1]['work_status']);

        $this->actingAs($this->agency)->put("/agencia/proyectos/{$this->project->id}", [
            'fx_rate' => '1600,5',
            'widgets' => ['blog', 'whatsapp'],
        ])->assertSessionHas('status');
        $p = $this->project->fresh();
        $this->assertSame('1600.5000', (string) $p->fx_rate);
        $this->assertSame(['blog', 'whatsapp'], $p->widgets->pluck('key')->all());

        $this->actingAs($this->agency)->post("/agencia/proyectos/{$this->project->id}/publicar")->assertSessionHas('status');
        $this->assertSame('published', $p->fresh()->status);
        $this->assertNotNull($p->fresh()->published_at);
    }

    public function test_client_cannot_change_prices_or_status(): void
    {
        $this->actingAs($this->client)->put("/agencia/proyectos/{$this->project->id}", ['fx_rate' => '1'])->assertRedirect(route('client.home'));
        $this->assertSame('1530.0000', (string) $this->project->fresh()->fx_rate);
    }

    public function test_agency_sees_the_client_view_of_a_project(): void
    {
        $this->actingAs($this->agency)->get("/agencia/proyectos/{$this->project->id}")->assertSee('Ver como cliente');
        $this->actingAs($this->agency)->get('/agencia')->assertSee(route('agency.project.preview', $this->project), false);

        $this->actingAs($this->agency)->get("/agencia/proyectos/{$this->project->id}/vista-cliente")
            ->assertOk()
            ->assertSee('Vista del cliente.')
            ->assertSee('Secciones de la portada')
            ->assertSee('4 de 9')
            ->assertDontSee('Estado del trabajo (interno)');

        $this->actingAs($this->client)->get("/agencia/proyectos/{$this->project->id}/vista-cliente")
            ->assertRedirect(route('client.home'));
        $this->actingAs($this->client)->get('/mi-panel')->assertDontSee('Vista del cliente.');
    }
}
