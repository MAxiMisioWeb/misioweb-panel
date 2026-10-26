<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Widget;
use App\Services\SignalMapper;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignalMapperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    private function project(string ...$widgets): Project
    {
        $project = Project::create([
            'client_id' => Client::create(['name' => 'Prueba'])->id,
            'name' => 'Sitio',
            'fx_rate' => '1530',
        ]);
        $project->widgets()->sync(Widget::whereIn('key', $widgets)->pluck('id'));

        return $project;
    }

    private function req(Project $project, string $key): ?ProjectRequirement
    {
        return ProjectRequirement::where(['project_id' => $project->id, 'template_key' => $key])->first();
    }

    public function test_ok_marks_requirements_as_done_by_plugin(): void
    {
        $project = $this->project('blog', 'tienda');

        $changed = app(SignalMapper::class)->apply($project, [
            'blog_entradas' => ['ok' => true, 'detail' => '2 completas'],
            'tienda_pasarela' => ['ok' => true, 'detail' => 'activo'],
        ]);

        $this->assertSame(['b1t', 'b1x', 'b1i', 'b2t', 'b2x', 'b2i', 'wcm', 'wcc'], $changed);
        $this->assertTrue($this->req($project, 'wcc')->done);
        $this->assertSame('plugin', $this->req($project, 'wcc')->source);
        $this->assertNull($this->req($project, 'b1t')->value, 'el detalle no se copia al campo');
    }

    public function test_legal_pages_mark_brand_requirement(): void
    {
        $project = $this->project();

        app(SignalMapper::class)->apply($project, ['paginas_legales' => ['ok' => true]]);

        $this->assertTrue($this->req($project, 'mkp')->done);
    }

    public function test_not_ok_never_unmarks_manual_work(): void
    {
        $project = $this->project('tienda');
        ProjectRequirement::create(['project_id' => $project->id, 'template_key' => 'wcp', 'value' => '30', 'done' => true, 'source' => 'manual']);

        $changed = app(SignalMapper::class)->apply($project, ['tienda_productos' => ['ok' => false, 'detail' => '0 productos']]);

        $this->assertSame([], $changed);
        $this->assertTrue($this->req($project, 'wcp')->done);
        $this->assertSame('manual', $this->req($project, 'wcp')->source);
    }

    public function test_not_ok_unmarks_what_the_plugin_marked(): void
    {
        $project = $this->project('reels');
        $mapper = app(SignalMapper::class);

        $mapper->apply($project, ['reels_plugin' => ['ok' => true]]);
        $this->assertTrue($this->req($project, 'igp')->done);

        $this->assertSame(['igp'], $mapper->apply($project, ['reels_plugin' => ['ok' => false]]));
        $this->assertFalse($this->req($project, 'igp')->done);
    }

    public function test_manual_done_is_not_taken_over_by_plugin(): void
    {
        $project = $this->project('reels');
        ProjectRequirement::create(['project_id' => $project->id, 'template_key' => 'igp', 'done' => true, 'source' => 'manual']);

        $mapper = app(SignalMapper::class);
        $this->assertSame([], $mapper->apply($project, ['reels_plugin' => ['ok' => true]]));
        $this->assertSame([], $mapper->apply($project, ['reels_plugin' => ['ok' => false]]));
        $this->assertTrue($this->req($project, 'igp')->done);
    }

    public function test_signals_for_widgets_not_chosen_are_ignored(): void
    {
        $project = $this->project('blog');

        $changed = app(SignalMapper::class)->apply($project, [
            'tienda_productos' => ['ok' => true],
            'tienda_pasarela' => ['ok' => true],
            'reels_plugin' => ['ok' => true],
            'senal_nueva' => ['ok' => true],
        ]);

        $this->assertSame([], $changed);
        $this->assertSame(0, ProjectRequirement::count());
    }

    public function test_applying_twice_changes_nothing_the_second_time(): void
    {
        $project = $this->project('blog');
        $mapper = app(SignalMapper::class);
        $signals = ['blog_entradas' => ['ok' => true]];

        $mapper->apply($project, $signals);
        $this->assertSame([], $mapper->apply($project, $signals));
    }

    public function test_progress_counts_only_applicable_requirements(): void
    {
        $project = $this->project('reels');
        $before = $project->progress();

        app(SignalMapper::class)->apply($project, [
            'reels_plugin' => ['ok' => true],
            'blog_entradas' => ['ok' => true], // blog no elegido: no cuenta
        ]);
        $after = $project->progress();

        // Base: portada (9) + marca (3); reels: 5.
        $this->assertSame(17, $before['total']);
        $this->assertSame(0, $before['done']);
        $this->assertSame(1, $after['done']);
        $this->assertSame(6, $after['percent']);
    }
}
