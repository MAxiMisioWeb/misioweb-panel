<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RequirementGroup;
use App\Models\RequirementTemplate;
use App\Models\Site;
use App\Models\Widget;
use App\Services\SignalMapper;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SankalpaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_does_not_duplicate(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->assertSame(7, Widget::count());
        $this->assertSame(9, RequirementGroup::count());
        $this->assertSame(40, RequirementTemplate::count());
    }

    public function test_every_signal_maps_to_existing_requirements(): void
    {
        $this->seed(CatalogSeeder::class);

        foreach (SignalMapper::MAP as $signal => $keys) {
            foreach ($keys as $key) {
                $this->assertTrue(RequirementTemplate::where('key', $key)->exists(), "{$signal} → {$key} no existe");
            }
        }
    }

    public function test_widget_groups_point_to_existing_widgets_and_types_are_valid(): void
    {
        $this->seed(CatalogSeeder::class);

        foreach (RequirementGroup::whereNotNull('widget_key')->pluck('widget_key') as $key) {
            $this->assertTrue(Widget::where('key', $key)->exists(), "widget {$key}");
        }
        $this->assertSame([], RequirementTemplate::whereNotIn('type', RequirementTemplate::TYPES)->pluck('key')->all());
    }

    public function test_sankalpa_demo_loads_the_prototype_case(): void
    {
        $this->seed(SankalpaDemoSeeder::class);
        $this->seed(SankalpaDemoSeeder::class);

        $project = Project::sole();
        $this->assertSame(['blog', 'calendario', 'reels', 'eventos', 'tienda'], $project->widgets->pluck('key')->all());
        $this->assertSame(7, $project->requirements()->where('done', true)->count());
        $this->assertSame(['sec', 'blog', 'ig', 'wc', 'cal', 'ev', 'mk'], $project->requirementGroups()->pluck('key')->all());
    }

    public function test_site_token_is_stored_only_as_hash(): void
    {
        $this->seed(SankalpaDemoSeeder::class);
        $site = new Site(['project_id' => Project::sole()->id, 'url' => 'https://sankalpa.test']);

        $token = $site->issueToken();

        $this->assertSame(48, strlen($token));
        $this->assertSame(hash('sha256', $token), $site->fresh()->token_hash);
        $this->assertTrue(Site::findByToken($token)->is($site));
        $this->assertNull(Site::findByToken('otro'));
        $this->assertArrayNotHasKey('token_hash', $site->toArray());
    }
}
