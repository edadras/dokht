<?php

namespace Tests\Feature;

use App\Models\CuttingLayout;
use App\Models\Customer;
use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\Project;
use App\Models\Simulation;
use App\Models\TechPack;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tech_pack_page_shows_a_preview_before_anything_is_stored(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $response = $this->get(route('tech-packs.show', $project));

        $response->assertOk();
        $response->assertSee('کارت فنی');
        $response->assertSee('خلاصه فنی');
        $response->assertSee('برگه اندازه');
        $response->assertSee('ترتیب دوخت');
        $response->assertSee('مواد و متعلقات');
        $response->assertSee('هنوز ذخیره نشده');

        $this->assertSame(0, TechPack::count());
    }

    public function test_storing_builds_a_versioned_tech_pack(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $this->post(route('tech-packs.store', $project))
            ->assertRedirect(route('tech-packs.show', $project))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'کارت فنی'));

        $this->post(route('tech-packs.store', $project));

        $versions = TechPack::where('project_id', $project->id)->orderBy('version')->pluck('version')->all();

        $this->assertSame([1, 2], $versions);

        $latest = TechPack::where('project_id', $project->id)->orderByDesc('version')->first();

        $this->assertSame($this->workshop()->id, $latest->workshop_id);
        $this->assertArrayHasKey('garment', $latest->data);
        $this->assertSame(10, $project->fresh()->step);

        $this->get(route('tech-packs.show', $project))
            ->assertOk()
            ->assertSee('نسخه ۲');
    }

    public function test_the_missing_checklist_is_shown_for_a_bare_project(): void
    {
        $this->actingAsWorkshopUser();

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => null,
            'garment_type_id' => null,
        ]);

        $this->get(route('tech-packs.show', $project))
            ->assertOk()
            ->assertSee('برای کامل شدن کارت فنی این موارد مانده است')
            ->assertSee('الگو ساخته نشده است؛ فهرست قطعه‌ها و نقشه برش خالی است.')
            ->assertSee('نقشه برش ساخته نشده');
    }

    public function test_the_print_page_contains_every_section(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $response = $this->get(route('tech-packs.print', $project));

        $response->assertOk();
        $response->assertSee('کارت فنی تولید');
        $response->assertSee('۱. خلاصه فنی');
        $response->assertSee('۲. برگه اندازه');
        $response->assertSee('۳. قطعه‌های الگو');
        $response->assertSee('۴. نقشه برش');
        $response->assertSee('۵. ترتیب دوخت');
        $response->assertSee('۶. مواد و متعلقات');
        $response->assertSee('۷. تناسب و کیفیت');
        $response->assertSee('<svg', false);
    }

    public function test_the_export_streams_a_zip_archive(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $response = $this->get(route('projects.export', $project));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));

        ob_start();
        $response->sendContent();
        $contents = (string) ob_get_clean();

        $this->assertGreaterThan(0, strlen($contents));
        $this->assertStringStartsWith('PK', $contents);
    }

    public function test_individual_formats_can_be_downloaded(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->project();

        $json = $this->get(route('projects.export', [$project, 'format' => 'json']));
        $json->assertOk();
        $this->assertArrayHasKey('garment', json_decode($json->getContent(), true)['data']);

        $svg = $this->get(route('projects.export', [$project, 'format' => 'svg']));
        $svg->assertOk();
        $this->assertStringContainsString('<svg', $svg->getContent());

        $dxf = $this->get(route('projects.export', [$project, 'format' => 'dxf']));
        $dxf->assertOk();
        $this->assertStringContainsString('POLYLINE', $dxf->getContent());

        $csv = $this->get(route('projects.export', [$project, 'format' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('دور سینه', $csv->getContent());
    }

    public function test_pattern_formats_need_a_pattern(): void
    {
        $this->actingAsWorkshopUser();

        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->from(route('tech-packs.show', $project))
            ->get(route('projects.export', [$project, 'format' => 'svg']))
            ->assertRedirect(route('tech-packs.show', $project))
            ->assertSessionHas('error');
    }

    public function test_another_workshop_project_is_not_reachable(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();
        $project = Project::factory()->create(['workshop_id' => $other->id]);

        $this->get(route('tech-packs.show', $project))->assertNotFound();
        $this->post(route('tech-packs.store', $project))->assertNotFound();
        $this->get(route('tech-packs.print', $project))->assertNotFound();
        $this->get(route('projects.export', $project))->assertNotFound();
    }

    public function test_a_stored_tech_pack_of_another_workshop_is_not_listed(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();
        $foreign = TechPack::factory()->create([
            'workshop_id' => $other->id,
            'project_id' => Project::factory()->create(['workshop_id' => $other->id])->id,
        ]);

        $this->assertSame(0, TechPack::where('id', $foreign->id)->count());
    }

    /** پروژه‌ای کامل با الگو، پارچه، اندازه، شبیه‌سازی و نقشه برش. */
    protected function project(): Project
    {
        $workshopId = $this->workshop()->id;

        $customer = Customer::factory()->create(['workshop_id' => $workshopId]);
        $garmentType = GarmentType::factory()->create([
            'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar'],
        ]);
        $measurementSet = MeasurementSet::factory()->for($customer)->create(['workshop_id' => $workshopId]);
        $fabric = Fabric::factory()->create([
            'workshop_id' => $workshopId,
            'width_cm' => 140,
            'price_per_meter' => 380000,
            'surface_pattern' => 'plain',
        ]);
        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $workshopId]);

        $project = Project::factory()->create([
            'workshop_id' => $workshopId,
            'customer_id' => $customer->id,
            'garment_type_id' => $garmentType->id,
            'measurement_set_id' => $measurementSet->id,
            'fabric_id' => $fabric->id,
            'pattern_id' => $pattern->id,
        ]);

        Simulation::create([
            'workshop_id' => $workshopId,
            'project_id' => $project->id,
            'pattern_id' => $pattern->id,
            'fabric_id' => $fabric->id,
            'pose' => 'stand',
            'zones' => ['bust' => ['label' => 'سینه', 'level' => 'good', 'value' => 4]],
            'fit_score' => 88,
        ]);

        CuttingLayout::factory()
            ->nested($pattern, $fabric)
            ->create([
                'workshop_id' => $workshopId,
                'project_id' => $project->id,
            ]);

        return $project->fresh();
    }
}
