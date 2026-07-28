<?php

namespace Tests\Unit;

use App\Models\CuttingLayout;
use App\Models\Customer;
use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Order;
use App\Models\Pattern;
use App\Models\Project;
use App\Models\Simulation;
use App\Models\TechPack;
use App\Services\Export\BillOfMaterialsBuilder;
use App\Services\Export\MeasurementSheetBuilder;
use App\Services\Export\ProjectArchiveExporter;
use App\Services\Export\SewingInstructionsBuilder;
use App\Services\TechPack\TechPackBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechPackBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected TechPackBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = app(TechPackBuilder::class);
    }

    public function test_it_builds_a_full_tech_pack_for_a_complete_project(): void
    {
        $project = $this->completeProject();

        $data = $this->builder->build($project);

        foreach ([
            'garment', 'fabric', 'pattern', 'measurements', 'construction',
            'materials', 'cutting', 'fit', 'quality', 'notes', 'missing',
        ] as $section) {
            $this->assertArrayHasKey($section, $data);
        }

        $this->assertSame($project->name, $data['garment']['name']);
        $this->assertSame($project->customer->name, $data['garment']['customer']);
        $this->assertSame($project->workshop->name, $data['garment']['workshop']);

        $this->assertSame($project->fabric->displayName(), $data['fabric']['name']);
        $this->assertGreaterThan(0, $data['fabric']['consumption_m']);
        $this->assertNotNull($data['fabric']['cost']);

        $this->assertSame(2, $data['pattern']['piece_count']);
        $this->assertCount(2, $data['pattern']['pieces']);
        $this->assertNotEmpty($data['pattern']['seam_allowances']);

        $this->assertNotEmpty($data['measurements']['rows']);
        $this->assertContains('دور سینه', array_column($data['measurements']['rows'], 'label'));

        $this->assertNotEmpty($data['construction']);
        $this->assertSame(1, $data['construction'][0]['number']);

        $this->assertFalse($data['cutting']['estimated']);
        $this->assertGreaterThan(0, $data['cutting']['length']);

        $this->assertNotNull($data['fit']);
        $this->assertSame(82, $data['fit']['score']);

        $this->assertSame([], $data['missing']);
    }

    public function test_it_builds_a_partial_tech_pack_for_a_bare_project_and_reports_what_is_missing(): void
    {
        $project = Project::factory()->create([
            'customer_id' => null,
            'garment_type_id' => null,
        ]);

        $data = $this->builder->build($project);

        $this->assertNull($data['garment']['customer']);
        $this->assertNull($data['fabric']['name']);
        $this->assertSame(0, $data['pattern']['piece_count']);
        $this->assertSame([], $data['pattern']['pieces']);
        $this->assertSame(0.0, $data['cutting']['meters']);
        $this->assertNull($data['fit']);

        $this->assertNotEmpty($data['missing']);

        $missing = implode(' | ', $data['missing']);

        foreach (['مشتری', 'نوع لباس', 'اندازه', 'پارچه', 'الگو', 'نقشه برش'] as $needle) {
            $this->assertStringContainsString($needle, $missing);
        }

        // برگه اندازه با جدول سایز پر می‌شود تا برگه خالی نماند
        $this->assertNotEmpty($data['measurements']['rows']);
        $this->assertNotEmpty($data['construction']);
    }

    public function test_storing_increments_the_version(): void
    {
        $project = $this->completeProject();

        $first = $this->builder->store($project);
        $second = $this->builder->store($project->fresh());

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame($project->workshop_id, $second->workshop_id);
        $this->assertTrue($second->isComplete());
        $this->assertSame(2, TechPack::where('project_id', $project->id)->count());
    }

    public function test_the_measurement_sheet_marks_derived_values(): void
    {
        $project = $this->completeProject();

        $sheet = app(MeasurementSheetBuilder::class)->build($project);

        $this->assertSame('measurement_set', $sheet['source']);
        $this->assertNotEmpty($sheet['essential_rows']);

        $bust = collect($sheet['rows'])->firstWhere('key', 'bust');
        $neck = collect($sheet['rows'])->firstWhere('key', 'neck');

        $this->assertFalse($bust['derived']);
        $this->assertTrue($neck['derived']);
        $this->assertNotNull($neck['value']);

        $this->assertStringContainsString('دور سینه', app(MeasurementSheetBuilder::class)->csv($project));
    }

    public function test_sewing_instructions_include_fabric_advice(): void
    {
        $project = $this->completeProject();

        $instructions = app(SewingInstructionsBuilder::class)->build($project);

        $this->assertNotEmpty($instructions['steps']);
        $this->assertNotEmpty($instructions['advice']['needle']);
        $this->assertNotEmpty($instructions['advice']['stitch']);
        $this->assertNotEmpty($instructions['advice']['pressing']);

        $titles = array_column($instructions['steps'], 'title');

        $this->assertContains('آماده‌سازی و برش', $titles);
        $this->assertStringContainsString('سوزن', app(SewingInstructionsBuilder::class)->toText($project));
    }

    public function test_the_bill_of_materials_prefers_the_order_items(): void
    {
        $project = $this->completeProject();

        $estimate = app(BillOfMaterialsBuilder::class)->build($project);

        $this->assertFalse($estimate['from_order']);
        $this->assertNotEmpty($estimate['rows']);
        $this->assertGreaterThan(0, $estimate['total']);

        $order = Order::create([
            'workshop_id' => $project->workshop_id,
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'code' => '1405-001',
            'title' => 'دوخت پیراهن',
            'status' => 'cutting',
        ]);

        $order->items()->create([
            'kind' => 'fabric',
            'fabric_id' => $project->fabric_id,
            'description' => 'پارچه اصلی',
            'quantity' => 2.5,
            'unit' => 'متر',
            'unit_price' => 400000,
        ]);

        $withOrder = app(BillOfMaterialsBuilder::class)->build($project->fresh());

        $this->assertTrue($withOrder['from_order']);
        $this->assertSame('1405-001', $withOrder['order_code']);
        $this->assertSame(1000000.0, $withOrder['rows'][0]['total']);
        $this->assertSame(1000000.0, $withOrder['total']);
    }

    public function test_the_archive_contains_every_production_file(): void
    {
        $project = $this->completeProject();

        $files = app(ProjectArchiveExporter::class)->files($project);

        foreach ([
            'pattern.svg', 'pattern.dxf', 'tech-pack.json',
            'cutting-layout.svg', 'measurement-sheet.csv', 'bill-of-materials.csv',
        ] as $name) {
            $this->assertArrayHasKey($name, $files);
            $this->assertNotSame('', $files[$name]);
        }

        $this->assertStringContainsString('<svg', $files['pattern.svg']);
        $this->assertStringContainsString('POLYLINE', $files['pattern.dxf']);
        $this->assertStringContainsString('EOF', $files['pattern.dxf']);

        $json = json_decode($files['tech-pack.json'], true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('garment', $json['data']);
    }

    public function test_the_archive_works_for_a_project_without_a_pattern(): void
    {
        $project = Project::factory()->create();

        $files = app(ProjectArchiveExporter::class)->files($project);

        $this->assertArrayNotHasKey('pattern.svg', $files);
        $this->assertArrayHasKey('tech-pack.json', $files);
        $this->assertArrayHasKey('measurement-sheet.csv', $files);
    }

    /** پروژه‌ای که همه گام‌هایش انجام شده است. */
    protected function completeProject(): Project
    {
        $customer = Customer::factory()->create();
        $garmentType = GarmentType::factory()->create([
            'parts' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'placket'],
        ]);
        $measurementSet = MeasurementSet::factory()->for($customer)->create([
            'workshop_id' => $customer->workshop_id,
        ]);
        $fabric = Fabric::factory()->create([
            'workshop_id' => $customer->workshop_id,
            'width_cm' => 140,
            'price_per_meter' => 500000,
            'stock_meters' => 20,
        ]);
        $pattern = Pattern::factory()->withSimplePieces()->create([
            'workshop_id' => $customer->workshop_id,
        ]);

        $project = Project::factory()->create([
            'workshop_id' => $customer->workshop_id,
            'customer_id' => $customer->id,
            'garment_type_id' => $garmentType->id,
            'measurement_set_id' => $measurementSet->id,
            'fabric_id' => $fabric->id,
            'pattern_id' => $pattern->id,
            'notes' => 'یقه کمی گشادتر باشد.',
        ]);

        Simulation::create([
            'workshop_id' => $project->workshop_id,
            'project_id' => $project->id,
            'pattern_id' => $pattern->id,
            'fabric_id' => $fabric->id,
            'pose' => 'stand',
            'zones' => ['bust' => ['label' => 'سینه', 'level' => 'good', 'value' => 4]],
            'warnings' => [],
            'fit_score' => 82,
        ]);

        CuttingLayout::factory()
            ->nested($pattern, $fabric)
            ->create([
                'workshop_id' => $project->workshop_id,
                'project_id' => $project->id,
            ]);

        return $project->fresh();
    }
}
