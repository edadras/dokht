<?php

namespace Tests\Unit;

use App\Models\PatternTemplate;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\GradingService;
use App\Services\Pattern\PatternBuilder;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingTest extends TestCase
{
    use RefreshDatabase;

    protected function pattern(string $size = '38', string $generator = 'bodice_block')
    {
        $this->actingAsWorkshopUser();

        $template = PatternTemplate::factory()->generator($generator)->create();

        return app(PatternBuilder::class)->createPattern($template, [
            'name' => 'الگوی آزمون',
            'base_size' => $size,
        ], [
            'measurements' => Measurements::fromSize($size),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4],
        ]);
    }

    public function test_grading_up_widens_pieces_monotonically(): void
    {
        $pattern = $this->pattern('38');
        $graded = app(GradingService::class)->grade($pattern, ['38', '40', '42', '44']);

        $this->assertSame(['38', '40', '42', '44'], array_map('strval', array_keys($graded)));

        $previous = null;

        foreach ($graded as $size => $pieces) {
            $width = Geometry::width($pieces[0]['outline']);

            if ($previous !== null) {
                $this->assertGreaterThan($previous, $width, "سایز {$size} باید پهن‌تر از سایز قبلی باشد.");
            }

            $previous = $width;
        }

        // رشد افقی هر سایز حدود یک‌چهارم اختلاف دور سینه است
        $baseWidth = Geometry::width($pattern->pieces->first()->outline);
        $size44 = Geometry::width($graded['44'][0]['outline']);
        $expected = $baseWidth + ((100 - 88) / 4);

        $this->assertEqualsWithDelta($expected, $size44, 1.5);
    }

    public function test_grading_down_shrinks_pieces(): void
    {
        $pattern = $this->pattern('42');
        $pieces = app(GradingService::class)->gradeToSize($pattern, '36');

        $this->assertLessThan(
            Geometry::width($pattern->pieces->first()->outline),
            Geometry::width($pieces[0]['outline']),
        );
    }

    public function test_graded_pieces_keep_structure_and_normalised_origin(): void
    {
        $pattern = $this->pattern('40');
        $pieces = app(GradingService::class)->gradeToSize($pattern, '46');

        $this->assertCount($pattern->pieces->count(), $pieces);

        foreach ($pieces as $index => $piece) {
            $source = $pattern->pieces[$index];

            $this->assertSame($source->code, $piece['code']);
            $this->assertCount(count($source->outline), $piece['outline']);
            $this->assertSame(count($source->darts ?? []), count($piece['darts'] ?? []));
            $this->assertNotEmpty($piece['markers']);

            [$minX, $minY] = Geometry::bounds($piece['outline']);
            $this->assertEqualsWithDelta(0, $minX, 0.02);
            $this->assertEqualsWithDelta(0, $minY, 0.02);
        }
    }

    public function test_taller_size_makes_pieces_longer(): void
    {
        $pattern = $this->pattern('36'); // قد ۱۶۵
        $pieces = app(GradingService::class)->gradeToSize($pattern, '42'); // قد ۱۷۰

        $this->assertGreaterThan(
            Geometry::height($pattern->pieces->first()->outline),
            Geometry::height($pieces[0]['outline']),
        );
    }

    public function test_create_graded_pattern_saves_a_new_pattern(): void
    {
        $pattern = $this->pattern('38');
        $graded = app(GradingService::class)->createGradedPattern($pattern, '44');

        $this->assertNotSame($pattern->id, $graded->id);
        $this->assertSame('44', $graded->base_size);
        $this->assertSame($pattern->workshop_id, $graded->workshop_id);
        $this->assertCount($pattern->pieces->count(), $graded->pieces);
        $this->assertSame(100.0, (float) $graded->measurements['bust']);
        $this->assertStringContainsString('سایز 44', $graded->name);
        $this->assertNotEmpty($graded->pieces->first()->edge_allowances);

        // الگوی مبنا دست‌نخورده می‌ماند
        $this->assertSame('38', $pattern->fresh()->base_size);
    }

    public function test_deltas_read_the_size_table(): void
    {
        $deltas = app(GradingService::class)->deltas('38', '44');

        $this->assertSame(12.0, $deltas['bust']);
        $this->assertSame(13.0, $deltas['waist']);
        $this->assertSame(12.0, $deltas['hip']);
        $this->assertSame(2.0, $deltas['height']);
    }

    public function test_pants_grading_also_grows(): void
    {
        $pattern = $this->pattern('38', 'pants_straight');
        $pieces = app(GradingService::class)->gradeToSize($pattern, '46');

        $this->assertGreaterThan(
            Geometry::width($pattern->pieces->first()->outline),
            Geometry::width($pieces[0]['outline']),
        );
    }
}
