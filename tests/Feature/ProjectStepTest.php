<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Project;
use App\Models\Workshop;
use App\Services\Pattern\PatternBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsWorkshopUser();
    }

    /** پروژه‌ای که همه‌ی پیش‌نیازهای مراحل را دارد. */
    protected function readyProject(array $attributes = []): Project
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
            'step' => 8,
            'status' => 'in_progress',
        ], $attributes));
    }

    public function test_every_step_page_renders(): void
    {
        $project = $this->readyProject();

        foreach (Project::STEPS as $number => $step) {
            $this->get(route('projects.step', [$project, $step['key']]))
                ->assertOk()
                ->assertSee($step['title']);
        }
    }

    public function test_every_step_page_renders_for_a_brand_new_project(): void
    {
        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => null,
            'garment_type_id' => null,
        ]);

        foreach (Project::STEPS as $step) {
            $this->get(route('projects.step', [$project, $step['key']]))->assertOk();
        }
    }

    public function test_an_unknown_step_is_not_found(): void
    {
        $project = $this->readyProject();

        $this->get(route('projects.step', [$project, 'somewhere-else']))->assertNotFound();
        $this->put(route('projects.step.update', [$project, 'somewhere-else']), [])->assertNotFound();
    }

    public function test_another_workshops_project_steps_are_not_reachable(): void
    {
        $other = Workshop::factory()->create();
        $project = Project::factory()->create(['workshop_id' => $other->id]);

        $this->get(route('projects.step', [$project, 'design']))->assertNotFound();
        $this->put(route('projects.step.update', [$project, 'design']), [])->assertNotFound();
        $this->post(route('projects.pattern.generate', $project), [])->assertNotFound();
    }

    public function test_step_one_saves_the_garment_type_and_moves_on(): void
    {
        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'garment_type_id' => null,
            'status' => 'draft',
        ]);

        $garment = GarmentType::factory()->create();

        $this->put(route('projects.step.update', [$project, 'design']), [
            'name' => 'مانتو بهاره',
            'garment_type_id' => $garment->id,
            'ease' => ['bust' => 8],
        ])->assertRedirect(route('projects.step', [$project, 'body']));

        $project->refresh();

        $this->assertSame($garment->id, $project->garment_type_id);
        $this->assertSame('مانتو بهاره', $project->name);
        $this->assertEquals(['bust' => 8.0], $project->ease);
        $this->assertSame('in_progress', $project->status);
        $this->assertSame(2, $project->step);
    }

    public function test_step_one_needs_a_garment_type(): void
    {
        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id, 'garment_type_id' => null]);

        $this->put(route('projects.step.update', [$project, 'design']), ['name' => 'بی‌مدل'])
            ->assertSessionHasErrors('garment_type_id');
    }

    public function test_step_two_with_six_inline_measurements_creates_a_customer_and_a_measurement_set(): void
    {
        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => null,
            'measurement_set_id' => null,
        ]);

        $this->put(route('projects.step.update', [$project, 'body']), [
            'mode' => 'inline',
            'customer_name' => 'زهرا کریمی',
            'height' => 168,
            'bust' => 96,
            'waist' => 78,
            'hip' => 102,
            'shoulder_width' => 40,
            'arm_length' => 59,
        ])->assertRedirect(route('projects.step', [$project, 'fabric']));

        $project->refresh();

        $customer = Customer::where('name', 'زهرا کریمی')->firstOrFail();

        $this->assertSame($customer->id, $project->customer_id);
        $this->assertSame($this->workshop()->id, $customer->workshop_id);
        $this->assertNotNull($project->measurement_set_id);
        $this->assertSame('42', $project->size, 'نزدیک‌ترین سایز به دور سینه ۹۶ سایز ۴۲ است.');
        $this->assertSame(3, $project->step);

        $values = $project->measurementSet->values;

        $this->assertEquals(96.0, $values['bust']);
        $this->assertEquals(168.0, $values['height']);

        // بقیه‌ی اندازه‌ها خودکار تخمین زده می‌شوند
        $this->assertArrayHasKey('bicep', $project->measurementSet->completed());
    }

    public function test_step_two_rejects_missing_measurements(): void
    {
        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->put(route('projects.step.update', [$project, 'body']), ['mode' => 'inline', 'bust' => 90])
            ->assertSessionHasErrors(['height', 'waist', 'hip', 'shoulder_width', 'arm_length']);
    }

    public function test_step_two_can_use_an_existing_measurement_set(): void
    {
        $customer = Customer::factory()->create(['workshop_id' => $this->workshop()->id]);
        $set = MeasurementSet::factory()->for($customer)->forSize('44')
            ->create(['workshop_id' => $this->workshop()->id]);

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'measurement_set_id' => null,
        ]);

        $this->put(route('projects.step.update', [$project, 'body']), [
            'mode' => 'existing',
            'customer_id' => $customer->id,
            'measurement_set_id' => $set->id,
        ])->assertRedirect();

        $project->refresh();

        $this->assertSame($set->id, $project->measurement_set_id);
        $this->assertSame('44', $project->size);
    }

    public function test_step_two_can_use_a_standard_size(): void
    {
        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'measurement_set_id' => null,
        ]);

        $this->put(route('projects.step.update', [$project, 'body']), ['mode' => 'size', 'size' => '46'])
            ->assertRedirect();

        $project->refresh();

        $this->assertSame('46', $project->size);
        $this->assertNotNull($project->measurement_set_id);
        $this->assertEquals(104.0, (float) $project->measurementSet->values['bust']);
    }

    public function test_step_three_saves_the_fabric(): void
    {
        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);
        $fabric = Fabric::factory()->create(['workshop_id' => $this->workshop()->id]);
        $lining = Fabric::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->put(route('projects.step.update', [$project, 'fabric']), [
            'fabric_id' => $fabric->id,
            'lining_fabric_id' => $lining->id,
        ])->assertRedirect();

        $project->refresh();

        $this->assertSame($fabric->id, $project->fabric_id);
        $this->assertSame($lining->id, $project->lining_fabric_id);
    }

    public function test_step_five_saves_seam_allowances_onto_the_pattern(): void
    {
        $project = $this->readyProject();

        $this->put(route('projects.step.update', [$project, 'seam']), [
            'allowances' => ['default' => 1.2, 'hem' => 3.5, 'neck' => 0.6, 'unknown_edge' => 9],
        ])->assertRedirect();

        $allowances = $project->pattern->fresh()->seam_allowances;

        $this->assertEquals(1.2, $allowances['default']);
        $this->assertEquals(3.5, $allowances['hem']);
        $this->assertArrayNotHasKey('unknown_edge', $allowances);
        $this->assertEquals(1.2, $project->pattern->fresh()->seamAllowance());
    }

    public function test_step_five_without_a_pattern_sends_the_user_back(): void
    {
        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id, 'pattern_id' => null]);

        $this->put(route('projects.step.update', [$project, 'seam']), ['allowances' => ['default' => 1]])
            ->assertRedirect(route('projects.step', [$project, 'pattern']))
            ->assertSessionHas('error');
    }

    public function test_step_six_can_add_and_remove_a_virtual_seam(): void
    {
        $project = $this->readyProject();

        $this->put(route('projects.step.update', [$project, 'layout']), [
            'add_from' => 'front:1',
            'add_to' => 'back:1',
        ])->assertRedirect(route('projects.step', [$project, 'layout']));

        $relations = $project->pattern->fresh()->sewing_relations;

        $this->assertCount(1, $relations);
        $this->assertSame('front', $relations[0]['from_piece']);
        $this->assertSame(1, $relations[0]['to_edge']);

        $this->put(route('projects.step.update', [$project, 'layout']), ['remove' => 0])
            ->assertRedirect(route('projects.step', [$project, 'layout']));

        $this->assertSame([], $project->pattern->fresh()->sewing_relations);
    }

    public function test_a_seam_cannot_join_an_edge_to_itself(): void
    {
        $project = $this->readyProject();

        $this->put(route('projects.step.update', [$project, 'layout']), [
            'add_from' => 'front:1',
            'add_to' => 'front:1',
        ])->assertSessionHasErrors('add_to');
    }

    public function test_step_eight_applies_a_suggestion_and_runs_the_analysis_again(): void
    {
        $project = $this->readyProject();

        $this->put(route('projects.step.update', [$project, 'fit']), [
            'action_type' => 'increase_ease',
            'action_key' => 'bust',
            'action_value' => 2,
        ])->assertRedirect(route('projects.step', [$project, 'fit']));

        $this->assertEquals(8.0, $project->pattern->fresh()->ease['bust']);
        $this->assertSame(1, $project->simulations()->count());
        $this->assertNotNull($project->simulations()->first()->fit_score);
    }

    /** الگوی پایه‌ای که تولیدکننده‌اش در ماژول الگو موجود است. */
    protected function template(Project $project, string $generator = 'bodice_block'): PatternTemplate
    {
        return PatternTemplate::create([
            'garment_type_id' => $project->garment_type_id,
            'code' => 'test-'.$generator,
            'name_fa' => 'الگوی پایه آزمون',
            'generator' => $generator,
            'is_public' => true,
        ]);
    }

    public function test_generating_a_pattern_attaches_it_and_moves_to_the_seam_step(): void
    {
        $project = $this->readyProject(['pattern_id' => null]);
        $template = $this->template($project);

        $response = $this->post(route('projects.pattern.generate', $project), [
            'pattern_template_id' => $template->id,
        ]);

        // اگر ماژول الگو در دسترس نباشد باید بی‌خطا و با پیام فارسی برگردد
        if (! class_exists(PatternBuilder::class)) {
            $response->assertRedirect()->assertSessionHas('error');
            $this->assertNull($project->fresh()->pattern_id);

            return;
        }

        $response->assertRedirect(route('projects.step', [$project, 'seam']))->assertSessionHas('status');

        $project->refresh();

        $this->assertNotNull($project->pattern_id);
        $this->assertSame($this->workshop()->id, $project->pattern->workshop_id);
        $this->assertTrue($project->pattern->pieces()->exists());

        // با هندسه‌ی واقعی الگو هم همه‌ی مراحل باید سالم رندر شوند
        foreach (Project::STEPS as $step) {
            $this->get(route('projects.step', [$project, $step['key']]))->assertOk();
        }

        // و تحلیل تناسب باید بتواند اندازه‌ی لباس را از خود قطعه‌ها دربیاورد
        $this->post(route('projects.simulate', $project), ['pose' => 'stand'])->assertRedirect();

        $zones = $project->simulations()->first()->zones;

        $this->assertNotEmpty($zones);

        foreach ($zones as $zone) {
            $this->assertGreaterThan(0, $zone['garment_cm'], "ناحیه {$zone['key']} اندازه ندارد.");
        }
    }

    public function test_generating_a_pattern_with_an_unknown_generator_fails_gracefully(): void
    {
        $project = $this->readyProject(['pattern_id' => null]);
        $template = $this->template($project, 'not-a-real-generator');

        $this->post(route('projects.pattern.generate', $project), ['pattern_template_id' => $template->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($project->fresh()->pattern_id);
    }

    public function test_generating_a_pattern_needs_a_template(): void
    {
        $project = $this->readyProject(['pattern_id' => null]);

        $this->post(route('projects.pattern.generate', $project), [])
            ->assertSessionHasErrors('pattern_template_id');
    }
}
