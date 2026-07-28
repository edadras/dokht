<?php

namespace Tests\Feature;

use App\Models\CuttingLayout;
use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Pattern;
use App\Models\Project;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuttingLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_cutting_layout_and_redirects_with_a_persian_summary(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $response = $this->post(route('projects.cutting', $project), []);

        $layout = CuttingLayout::firstOrFail();

        $response->assertRedirect(route('cutting-layouts.show', $layout));
        $response->assertSessionHas('status', fn (string $status) => str_contains($status, 'متر پارچه لازم است')
            && str_contains($status, 'دورریز'));

        $this->assertSame($this->workshop()->id, $layout->workshop_id);
        $this->assertSame($project->id, $layout->project_id);
        $this->assertSame($project->pattern_id, $layout->pattern_id);
        $this->assertSame($project->fabric_id, $layout->fabric_id);
        $this->assertSame(140.0, $layout->fabric_width_cm);
        $this->assertTrue($layout->folded);
        $this->assertGreaterThan(0, $layout->required_length_cm);
        $this->assertGreaterThan(0, $layout->efficiency);
        $this->assertCount(2, $layout->placements);

        // پروژه به گام برش می‌رود
        $this->assertSame(9, $project->fresh()->step);
    }

    public function test_it_respects_the_options_sent_from_the_form(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $this->post(route('projects.cutting', $project), [
            'fabric_width_cm' => 110,
            'folded' => '0',
            'respect_nap' => '1',
            'match_stripes' => '0',
            'include_allowance' => '1',
            'allow_rotation' => '0',
            'gap_cm' => 2,
        ])->assertRedirect();

        $layout = CuttingLayout::firstOrFail();

        $this->assertSame(110.0, $layout->fabric_width_cm);
        $this->assertFalse($layout->folded);
        $this->assertTrue($layout->respect_nap);
        $this->assertFalse($layout->match_stripes);
        $this->assertSame(110.0, $layout->usableWidthCm());
        $this->assertSame([0, 0], array_column($layout->placements, 'rotation'));
    }

    public function test_it_validates_the_options(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $this->post(route('projects.cutting', $project), ['fabric_width_cm' => 5])
            ->assertSessionHasErrors('fabric_width_cm');

        $this->assertSame(0, CuttingLayout::count());
    }

    public function test_it_refuses_to_nest_a_project_without_a_pattern(): void
    {
        $this->actingAsWorkshopUser();

        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->from(route('projects.show', $project))
            ->post(route('projects.cutting', $project), [])
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'الگو'));

        $this->assertSame(0, CuttingLayout::count());
    }

    public function test_the_layout_page_shows_the_plan_and_the_comparison_tables(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $this->post(route('projects.cutting', $project), []);

        $layout = CuttingLayout::firstOrFail();

        $response = $this->get(route('cutting-layouts.show', $layout));

        $response->assertOk();
        $response->assertSee('نقشه برش');
        $response->assertSee('مصرف در عرض‌های رایج پارچه');
        $response->assertSee('مقایسه چیدمان‌ها');
        $response->assertSee('محاسبه دوباره با تنظیمات دیگر');
        $response->assertSee('<svg', false);
        $response->assertSee('جلو');
    }

    public function test_the_print_page_renders(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $this->post(route('projects.cutting', $project), []);

        $layout = CuttingLayout::firstOrFail();

        $response = $this->get(route('cutting-layouts.print', $layout));

        $response->assertOk();
        $response->assertSee('نقشه برش پارچه');
        $response->assertSee('فهرست قطعه‌ها');
        $response->assertSee('<svg', false);
    }

    public function test_a_narrow_fabric_warning_is_stored_and_shown(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->create(['workshop_id' => $this->workshop()->id]);
        $pattern->pieces()->create([
            'code' => 'cape',
            'name' => 'شنل گشاد',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'outline' => [
                ['x' => 0, 'y' => 0],
                ['x' => 170, 'y' => 0],
                ['x' => 170, 'y' => 190],
                ['x' => 0, 'y' => 190],
            ],
        ]);

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'pattern_id' => $pattern->id,
        ]);

        $this->post(route('projects.cutting', $project), ['fabric_width_cm' => 140]);

        $layout = CuttingLayout::firstOrFail();

        $this->assertNotEmpty($layout->warnings);
        $this->assertStringContainsString('شنل گشاد', implode(' ', $layout->warnings));

        $this->get(route('cutting-layouts.show', $layout))
            ->assertOk()
            ->assertSee('شنل گشاد');
    }

    public function test_another_workshop_layout_is_not_reachable(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();
        $layout = CuttingLayout::factory()->create(['workshop_id' => $other->id]);

        $this->get(route('cutting-layouts.show', $layout))->assertNotFound();
        $this->get(route('cutting-layouts.print', $layout))->assertNotFound();
    }

    public function test_another_workshop_project_cannot_be_nested(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();
        $project = Project::factory()->create(['workshop_id' => $other->id]);

        $this->post(route('projects.cutting', $project), [])->assertNotFound();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $layout = CuttingLayout::factory()->create();

        $this->get(route('cutting-layouts.show', $layout))->assertRedirect('/login');
    }

    /** پروژه‌ای با الگو و پارچه در کارگاه فعال. */
    protected function project(): Project
    {
        $workshopId = $this->workshop()->id;

        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $workshopId]);
        $fabric = Fabric::factory()->create([
            'workshop_id' => $workshopId,
            'width_cm' => 140,
            'price_per_meter' => 450000,
            'surface_pattern' => 'plain',
        ]);

        return Project::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => Customer::factory()->create(['workshop_id' => $workshopId])->id,
            'pattern_id' => $pattern->id,
            'fabric_id' => $fabric->id,
        ]);
    }
}
