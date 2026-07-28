<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\Project;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsWorkshopUser();
    }

    public function test_a_project_needs_only_a_name(): void
    {
        $response = $this->post(route('projects.store'), ['name' => 'مانتو اداری']);

        $project = Project::firstOrFail();

        $response->assertRedirect(route('projects.step', [$project, 'design']));

        $this->assertSame('مانتو اداری', $project->name);
        $this->assertSame(1, $project->step);
        $this->assertSame('draft', $project->status);
        $this->assertSame($this->workshop()->id, $project->workshop_id);
        $this->assertNull($project->garment_type_id);
    }

    public function test_a_project_without_a_name_is_rejected(): void
    {
        $this->post(route('projects.store'), ['name' => ''])->assertSessionHasErrors('name');

        $this->assertSame(0, Project::count());
    }

    public function test_optional_choices_are_kept_and_the_default_measurement_set_is_attached(): void
    {
        $customer = Customer::factory()->create(['workshop_id' => $this->workshop()->id]);
        $set = MeasurementSet::factory()->for($customer)->create([
            'workshop_id' => $this->workshop()->id,
            'is_default' => true,
        ]);
        $garment = GarmentType::factory()->create();

        $this->post(route('projects.store'), [
            'name' => 'پیراهن',
            'customer_id' => $customer->id,
            'garment_type_id' => $garment->id,
            'size' => '42',
        ])->assertRedirect();

        $project = Project::firstOrFail();

        $this->assertSame($customer->id, $project->customer_id);
        $this->assertSame($garment->id, $project->garment_type_id);
        $this->assertSame('42', $project->size);
        $this->assertSame($set->id, $project->measurement_set_id);
    }

    public function test_the_index_shows_projects_and_an_empty_state(): void
    {
        $this->get(route('projects.index'))->assertOk()->assertSee('هنوز پروژه‌ای نساخته‌اید');

        Project::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'دامن کلوش']);

        $this->get(route('projects.index'))->assertOk()->assertSee('دامن کلوش');
    }

    public function test_the_index_filters_by_status_and_search(): void
    {
        Project::factory()->create([
            'workshop_id' => $this->workshop()->id, 'name' => 'کار تمام‌شده', 'status' => 'done',
        ]);
        Project::factory()->create([
            'workshop_id' => $this->workshop()->id, 'name' => 'کار در جریان', 'status' => 'in_progress',
        ]);

        $this->get(route('projects.index', ['status' => 'done']))
            ->assertOk()
            ->assertSee('کار تمام‌شده')
            ->assertDontSee('کار در جریان');

        $this->get(route('projects.index', ['q' => 'جریان']))
            ->assertOk()
            ->assertSee('کار در جریان')
            ->assertDontSee('کار تمام‌شده');
    }

    public function test_the_project_page_shows_progress_and_the_scorecard(): void
    {
        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'pattern_id' => Pattern::factory()->withSimplePieces()->create([
                'workshop_id' => $this->workshop()->id,
            ])->id,
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee($project->name)
            ->assertSee('کارنامه‌ی کیفیت')
            ->assertSee('کیفیت الگو');

        $this->assertIsArray($project->fresh()->scorecard);
        $this->assertArrayHasKey('overall', $project->fresh()->scorecard);
    }

    public function test_a_project_can_be_updated(): void
    {
        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);
        $customer = Customer::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->get(route('projects.edit', $project))->assertOk();

        $this->put(route('projects.update', $project), [
            'name' => 'نام تازه',
            'status' => 'done',
            'customer_id' => $customer->id,
            'notes' => 'پارچه را دو بار شسته‌ام.',
        ])->assertRedirect(route('projects.show', $project));

        $project->refresh();

        $this->assertSame('نام تازه', $project->name);
        $this->assertSame('done', $project->status);
        $this->assertSame($customer->id, $project->customer_id);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->put(route('projects.update', $project), ['name' => 'x', 'status' => 'unknown'])
            ->assertSessionHasErrors('status');
    }

    public function test_a_project_can_be_deleted(): void
    {
        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($project);
    }

    public function test_another_workshops_project_is_not_reachable(): void
    {
        $other = Workshop::factory()->create();
        $project = Project::factory()->create(['workshop_id' => $other->id]);

        $this->get(route('projects.show', $project))->assertNotFound();
        $this->get(route('projects.edit', $project))->assertNotFound();
        $this->put(route('projects.update', $project), ['name' => 'x', 'status' => 'done'])->assertNotFound();
        $this->delete(route('projects.destroy', $project))->assertNotFound();
    }

    public function test_the_create_page_offers_the_most_recent_items_first(): void
    {
        $older = Customer::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'مشتری قدیمی']);
        $newer = Customer::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'مشتری تازه']);

        $response = $this->get(route('projects.create'));

        $response->assertOk()->assertSee('مشتری تازه')->assertSee('مشتری قدیمی');

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, $older->name),
            strpos($content, $newer->name),
            'تازه‌ترین مشتری باید بالای فهرست باشد.',
        );
    }
}
