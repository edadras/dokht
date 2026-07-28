<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\Project;
use App\Models\Simulation;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsWorkshopUser();
    }

    protected function project(array $attributes = []): Project
    {
        $workshopId = $this->workshop()->id;
        $customer = Customer::factory()->create(['workshop_id' => $workshopId]);

        return Project::factory()->create(array_merge([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'garment_type_id' => GarmentType::factory()->create()->id,
            'measurement_set_id' => MeasurementSet::factory()->for($customer)->forSize('40')
                ->create(['workshop_id' => $workshopId])->id,
            'fabric_id' => Fabric::factory()->create(['workshop_id' => $workshopId])->id,
            'pattern_id' => Pattern::factory()->withSimplePieces()->create([
                'workshop_id' => $workshopId,
                'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
            ])->id,
        ], $attributes));
    }

    public function test_a_simulation_stores_zones_a_score_and_refreshes_the_scorecard(): void
    {
        $project = $this->project();

        $this->post(route('projects.simulate', $project), ['pose' => 'stand'])
            ->assertRedirect(route('projects.step', [$project, 'fit']))
            ->assertSessionHas('status');

        $simulation = Simulation::firstOrFail();

        $this->assertSame($project->id, $simulation->project_id);
        $this->assertSame($this->workshop()->id, $simulation->workshop_id);
        $this->assertSame('stand', $simulation->pose);
        $this->assertNotEmpty($simulation->zones);
        $this->assertIsInt($simulation->fit_score);
        $this->assertArrayHasKey('stretch_weft', $simulation->params);

        foreach ($simulation->zones as $zone) {
            $this->assertArrayHasKey('level', $zone);
            $this->assertArrayHasKey('color', $zone);
            $this->assertArrayHasKey('pressure', $zone);
        }

        $project->refresh();

        $this->assertIsArray($project->scorecard);
        $this->assertArrayHasKey('fit', $project->scorecard);
        $this->assertSame($simulation->fit_score, $project->scorecard['fit']);
        $this->assertGreaterThanOrEqual(8, $project->step);
        $this->assertSame('stand', $project->settings['pose']);
    }

    public function test_every_pose_can_be_simulated(): void
    {
        $project = $this->project();

        foreach (array_keys(Simulation::POSES) as $pose) {
            $this->post(route('projects.simulate', $project), ['pose' => $pose])->assertRedirect();
        }

        $this->assertSame(count(Simulation::POSES), $project->simulations()->count());
    }

    public function test_an_unknown_pose_is_rejected(): void
    {
        $project = $this->project();

        $this->post(route('projects.simulate', $project), ['pose' => 'ایستاده روی یک پا'])
            ->assertSessionHasErrors('pose');

        $this->assertSame(0, Simulation::count());
    }

    public function test_a_project_without_a_pattern_cannot_be_simulated(): void
    {
        $project = $this->project(['pattern_id' => null]);

        $this->post(route('projects.simulate', $project), ['pose' => 'stand'])
            ->assertRedirect(route('projects.step', [$project, 'pattern']))
            ->assertSessionHas('error');

        $this->assertSame(0, Simulation::count());
    }

    public function test_a_knit_fabric_scores_better_than_a_woven_one_on_a_tight_pattern(): void
    {
        $workshopId = $this->workshop()->id;

        $tightPattern = fn () => Pattern::factory()->withSimplePieces()->create([
            'workshop_id' => $workshopId,
            'ease' => ['bust' => -5, 'waist' => -4, 'hip' => -5],
        ])->id;

        $woven = $this->project(['pattern_id' => $tightPattern()]);
        $knit = $this->project([
            'pattern_id' => $tightPattern(),
            'fabric_id' => Fabric::factory()->for(FabricType::factory()->stretchy())
                ->create(['workshop_id' => $workshopId, 'gsm' => null])->id,
        ]);

        $this->post(route('projects.simulate', $woven), ['pose' => 'stand']);
        $this->post(route('projects.simulate', $knit), ['pose' => 'stand']);

        $wovenScore = $woven->simulations()->first()->fit_score;
        $knitScore = $knit->simulations()->first()->fit_score;

        $this->assertGreaterThan($wovenScore, $knitScore);
    }

    public function test_the_report_page_renders(): void
    {
        $project = $this->project();

        $this->post(route('projects.simulate', $project), ['pose' => 'walk']);

        $simulation = Simulation::firstOrFail();

        $this->get(route('simulations.show', $simulation))
            ->assertOk()
            ->assertSee('گزارش تست تناسب')
            ->assertSee('راه رفتن')
            ->assertSee('دور سینه');
    }

    public function test_a_stored_simulation_from_the_factory_renders(): void
    {
        $project = $this->project();

        $simulation = Simulation::factory()->tight()->create([
            'workshop_id' => $this->workshop()->id,
            'project_id' => $project->id,
            'pattern_id' => $project->pattern_id,
            'fabric_id' => $project->fabric_id,
        ]);

        $this->get(route('simulations.show', $simulation))
            ->assertOk()
            ->assertSee('تنگ')
            ->assertSee('ناحیه «دور سینه» تنگ است.');
    }

    public function test_another_workshops_simulation_is_not_reachable(): void
    {
        $other = Workshop::factory()->create();
        $project = Project::factory()->create(['workshop_id' => $other->id]);
        $simulation = Simulation::factory()->create([
            'workshop_id' => $other->id,
            'project_id' => $project->id,
        ]);

        $this->get(route('simulations.show', $simulation))->assertNotFound();
        $this->post(route('projects.simulate', $project), ['pose' => 'stand'])->assertNotFound();
    }
}
