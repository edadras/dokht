<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Services\Cutting\NestingService;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سه اصل خیاطی که تا امروز اعلام می‌شدند ولی اجرا نمی‌شدند:
 * برش اریب، آب‌رفت پارچه، و اصلاح پوسچر.
 */
class GrainAndPostureTest extends TestCase
{
    use RefreshDatabase;

    protected function rectanglePattern(bool $bias): Pattern
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::create([
            'name' => 'آزمون راستا',
            'measurements' => Measurements::complete([]),
            'ease' => [],
            'params' => [],
            'seam_allowances' => ['default' => 1.0],
            'version' => 1,
        ]);

        $pattern->pieces()->create([
            'code' => 'band',
            'name' => 'نوار',
            'cut_quantity' => 1,
            'outline' => [
                ['x' => 0, 'y' => 0], ['x' => 60, 'y' => 0], ['x' => 60, 'y' => 8], ['x' => 0, 'y' => 8],
            ],
            'meta' => array_filter([
                'part' => 'collar',
                'edges' => ['default', 'default', 'default', 'default'],
                'bias' => $bias ?: null,
            ]),
        ]);

        return $pattern->load('pieces');
    }

    public function test_a_bias_piece_is_laid_at_forty_five_degrees(): void
    {
        $layout = (new NestingService)->nest($this->rectanglePattern(true), null, ['fabric_width_cm' => 140]);
        $place = $layout['placements'][0];

        $this->assertSame(45, $place['rotation'], 'قطعه اریب باید ۴۵ درجه چیده شود.');

        // کادر یک مربع است: (پهنا + بلندی) ÷ √۲
        $this->assertEqualsWithDelta($place['width'], $place['height'], 0.05);
        $this->assertEqualsWithDelta((60 + 2 + 8 + 2) * M_SQRT1_2, $place['width'], 0.2);
    }

    public function test_a_straight_grain_piece_is_not_rotated_to_forty_five(): void
    {
        $layout = (new NestingService)->nest($this->rectanglePattern(false), null, ['fabric_width_cm' => 140]);

        foreach ($layout['placements'] as $place) {
            $this->assertNotSame(45, $place['rotation']);
        }
    }

    public function test_the_transform_of_a_bias_piece_keeps_it_inside_its_own_box(): void
    {
        $layout = (new NestingService)->nest($this->rectanglePattern(true), null, ['fabric_width_cm' => 140]);
        $place = $layout['placements'][0];

        preg_match_all('/-?\d+(?:\.\d+)?/', $place['transform'], $matches);
        [$a, $b, $c, $d, $e, $f] = array_map('floatval', $matches[0]);

        $xs = [];
        $ys = [];

        foreach ([[0, 0], [60, 0], [60, 8], [0, 8]] as [$x, $y]) {
            $xs[] = ($a * $x) + ($c * $y) + $e;
            $ys[] = ($b * $x) + ($d * $y) + $f;
        }

        $this->assertGreaterThanOrEqual($place['x'] - 0.01, min($xs));
        $this->assertLessThanOrEqual($place['x'] + $place['width'] + 0.01, max($xs));
        $this->assertGreaterThanOrEqual($place['y'] - 0.01, min($ys));
        $this->assertLessThanOrEqual($place['y'] + $place['height'] + 0.01, max($ys));
    }

    public function test_the_buy_length_carries_the_shrinkage_of_the_fabric(): void
    {
        $layout = (new NestingService)->nest($this->rectanglePattern(false), null, ['fabric_width_cm' => 140]);

        // بدون پارچه، آب‌رفت صفر است و طول خرید همان طول برش
        $this->assertSame(0.0, $layout['shrinkage_percent']);
        $this->assertSame($layout['required_length_cm'], $layout['buy_length_cm']);
        $this->assertGreaterThan(0, $layout['buy_meters']);
    }

    /* ---------------------------------------------------------------------
     |  اصلاح پوسچر
     * ------------------------------------------------------------------- */

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    protected function blockPanels(array $posture): array
    {
        $generator = GeneratorRegistry::make('bodice_block');
        $measurements = Measurements::complete(array_merge([
            'height' => 168, 'bust' => 92, 'waist' => 74, 'hip' => 98,
            'shoulder_width' => 39, 'arm_length' => 58,
        ], $posture));

        $front = $back = null;

        foreach ($generator->generate($measurements, [], $generator->defaultParams()) as $piece) {
            if (($piece['meta']['part'] ?? null) === 'front_bodice') {
                $front = $piece;
            }

            if (($piece['meta']['part'] ?? null) === 'back_bodice') {
                $back = $piece;
            }
        }

        return [$front, $back];
    }

    /** بلندی مرکز پشت: لبه بسته‌شدن پنل پشت. */
    protected function centerBack(array $back): float
    {
        $edges = array_keys($back['meta']['edges'], 'default', true);

        return Geometry::edgeLength($back['outline'], (int) end($edges));
    }

    public function test_a_rounded_back_makes_the_center_back_longer_and_a_sway_back_shorter(): void
    {
        [, $straight] = $this->blockPanels([]);
        [, $rounded] = $this->blockPanels(['back_curve' => 3]);
        [, $sway] = $this->blockPanels(['sway_back' => 2]);

        $base = $this->centerBack($straight);

        $this->assertEqualsWithDelta($base + 3, $this->centerBack($rounded), 0.05);
        $this->assertEqualsWithDelta($base - 2, $this->centerBack($sway), 0.05);
    }

    public function test_posture_correction_never_breaks_the_side_seam_pairing(): void
    {
        foreach ([[], ['back_curve' => 4], ['sway_back' => 3], ['back_curve' => 2, 'sway_back' => 2]] as $posture) {
            [$front, $back] = $this->blockPanels($posture);
            $walk = PieceOps::walk($front, 'side', $back, 'side', ['tolerance' => 0.15]);

            $this->assertTrue(
                $walk['matched'],
                'اصلاح پوسچر روی مرکز پشت می‌نشیند، نه روی درز پهلو: '.json_encode($posture, JSON_UNESCAPED_UNICODE),
            );
        }
    }

    public function test_a_straight_body_sees_no_change_at_all(): void
    {
        [$plainFront, $plainBack] = $this->blockPanels([]);
        [$zeroFront, $zeroBack] = $this->blockPanels(['back_curve' => 0, 'sway_back' => 0]);

        $this->assertSame($plainFront['outline'], $zeroFront['outline']);
        $this->assertSame($plainBack['outline'], $zeroBack['outline']);
    }
}
