<?php

namespace Tests\Unit;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\Project;
use App\Services\Fit\FitAnalysisService;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected FitAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsWorkshopUser();
        $this->service = app(FitAnalysisService::class);
    }

    /** الگویی با آزادی مشخص روی سایز ۴۰. */
    protected function pattern(array $ease): Pattern
    {
        return Pattern::factory()->withSimplePieces()->create([
            'workshop_id' => $this->workshop()->id,
            'measurements' => Measurements::fromSize('40'),
            'ease' => $ease,
        ]);
    }

    protected function fabric(bool $stretchy = false): Fabric
    {
        $type = $stretchy ? FabricType::factory()->stretchy() : FabricType::factory();

        return Fabric::factory()->for($type)->create([
            'workshop_id' => $this->workshop()->id,
            'gsm' => null,
        ]);
    }

    protected function zone(array $analysis, string $key): array
    {
        foreach ($analysis['zones'] as $zone) {
            if ($zone['key'] === $key) {
                return $zone;
            }
        }

        $this->fail("ناحیه {$key} در نتیجه نیست.");
    }

    public function test_woven_pattern_smaller_than_the_body_is_tight(): void
    {
        $analysis = $this->service->analyze(
            $this->pattern(['bust' => -6]),
            $this->fabric(),
            Measurements::fromSize('40'),
        );

        $bust = $this->zone($analysis, 'bust');

        $this->assertSame('tight', $bust['level']);
        $this->assertSame(-6.0, $bust['ease_cm']);
        $this->assertSame('#dc2626', $bust['color']);
        $this->assertGreaterThan(0.6, $bust['pressure']);
        $this->assertNotEmpty($analysis['warnings']);
        $this->assertStringContainsString('دور سینه', implode(' ', $analysis['warnings']));
    }

    public function test_matching_pattern_fits_well(): void
    {
        $analysis = $this->service->analyze(
            $this->pattern(['bust' => 6, 'waist' => 4, 'hip' => 6]),
            $this->fabric(),
            Measurements::fromSize('40'),
        );

        $this->assertSame('good', $this->zone($analysis, 'bust')['level']);
        $this->assertSame('good', $this->zone($analysis, 'waist')['level']);
        $this->assertSame('good', $this->zone($analysis, 'hip')['level']);
        $this->assertGreaterThan(60, $analysis['fit_score']);
    }

    public function test_stretch_knit_accepts_negative_ease(): void
    {
        $analysis = $this->service->analyze(
            $this->pattern(['bust' => -6]),
            $this->fabric(stretchy: true),
            Measurements::fromSize('40'),
        );

        $bust = $this->zone($analysis, 'bust');

        $this->assertNotSame('tight', $bust['level']);
        $this->assertContains($bust['level'], ['good', 'snug']);
        $this->assertSame(-6.0, $bust['ease_cm'], 'آزادی واقعی باید صادقانه منفی بماند.');
        $this->assertStringContainsString('کشسانی', $bust['note']);
    }

    public function test_arm_raise_pose_tightens_the_armhole(): void
    {
        $pattern = $this->pattern(['bust' => 6, 'armhole' => 2.5]);
        $fabric = $this->fabric();
        $body = Measurements::fromSize('40');

        $stand = $this->zone($this->service->analyze($pattern, $fabric, $body, 'stand'), 'armhole');
        $raised = $this->zone($this->service->analyze($pattern, $fabric, $body, 'arm_raise'), 'armhole');

        $this->assertSame('good', $stand['level']);
        $this->assertSame('snug', $raised['level']);
        $this->assertGreaterThan($stand['pressure'], $raised['pressure']);
    }

    public function test_analysis_shape_is_stable(): void
    {
        $analysis = $this->service->analyze($this->pattern(['bust' => 6]), $this->fabric(), Measurements::fromSize('40'));

        $this->assertSame(['zones', 'warnings', 'suggestions', 'fit_score', 'params'], array_keys($analysis));
        $this->assertCount(count(FitAnalysisService::ZONES), $analysis['zones']);
        $this->assertIsInt($analysis['fit_score']);
        $this->assertArrayHasKey('stretch_weft', $analysis['params']);

        foreach ($analysis['zones'] as $zone) {
            $this->assertSame(
                ['key', 'label', 'garment_cm', 'body_cm', 'ease_cm', 'level', 'color', 'pressure', 'note'],
                array_keys($zone),
            );
        }
    }

    public function test_unknown_pose_falls_back_to_standing(): void
    {
        $pattern = $this->pattern(['bust' => 6, 'armhole' => 2.5]);
        $fabric = $this->fabric();
        $body = Measurements::fromSize('40');

        $this->assertSame(
            $this->zone($this->service->analyze($pattern, $fabric, $body, 'stand'), 'armhole')['level'],
            $this->zone($this->service->analyze($pattern, $fabric, $body, 'دست‌وپا'), 'armhole')['level'],
        );
    }

    public function test_scorecard_covers_every_section_and_is_stored(): void
    {
        $pattern = $this->pattern(['bust' => 6, 'waist' => 4, 'hip' => 6]);

        $set = MeasurementSet::factory()->forSize('40')->create([
            'workshop_id' => $this->workshop()->id,
        ]);

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'pattern_id' => $pattern->id,
            'fabric_id' => $this->fabric()->id,
            'measurement_set_id' => $set->id,
        ]);

        $card = $this->service->scorecard($project);

        $this->assertSame([
            'pattern_quality', 'fabric_compatibility', 'fit',
            'cutting_efficiency', 'production_readiness', 'overall',
        ], array_keys($card));

        foreach ($card as $key => $value) {
            $this->assertIsInt($value, "بخش {$key} باید عدد صحیح باشد.");
            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertLessThanOrEqual(100, $value);
        }

        $this->assertGreaterThan(0, $card['pattern_quality']);
        $this->assertGreaterThan(0, $card['fit']);
        $this->assertSame($card, $project->fresh()->scorecard);
    }

    public function test_a_sheer_fabric_adds_a_lining_warning(): void
    {
        $fabric = Fabric::factory()->for(FabricType::factory()->fluid())->create([
            'workshop_id' => $this->workshop()->id,
            'gsm' => null,
        ]);

        $analysis = $this->service->analyze($this->pattern(['bust' => 6]), $fabric, Measurements::fromSize('40'));

        $this->assertStringContainsString('آستر', implode(' ', $analysis['warnings']));
    }
}
