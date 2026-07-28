<?php

namespace Tests\Unit\Transform;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Services\Pattern\Transform\StyleLineCutter;

/** پیاده‌کردن درز، راست‌سازی، باز و بسته کردن تا، بلندکردن و دوختن دو قطعه. */
class PieceOpsTest extends TransformTestCase
{
    /**
     * دو مستطیل با درز پهلوی نابرابر: A سی سانتی‌متر، B بیست‌وهفت‌ونیم.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function seamPair(): array
    {
        $make = fn (string $code, float $height) => [
            'code' => $code,
            'name' => $code,
            'cut_quantity' => 1,
            'on_fold' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(10, 0),
                Geometry::point(10, $height),
                Geometry::point(0, $height),
            ],
            'grainline' => ['from' => Geometry::point(5, 1), 'to' => Geometry::point(5, $height - 1)],
            'darts' => [],
            'notches' => [],
            'drills' => [],
            'pleats' => [],
            'markers' => [],
            'meta' => ['edges' => ['hem', 'side', 'waist', 'default'], 'fold_edges' => []],
        ];

        return [$make('a', 30.0), $make('b', 27.5)];
    }

    public function test_walking_two_seams_reports_the_real_difference_and_matching_notches(): void
    {
        [$a, $b] = $this->seamPair();

        $walk = PieceOps::walk($a, 'side', $b, 'side');

        $this->assertEqualsWithDelta(30.0, $walk['a']['seam'], self::TOLERANCE);
        $this->assertEqualsWithDelta(27.5, $walk['b']['seam'], self::TOLERANCE);
        $this->assertEqualsWithDelta(2.5, $walk['difference'], self::TOLERANCE);
        $this->assertSame('a', $walk['longer']);
        $this->assertFalse($walk['matched']);

        // نشانه‌ها روی نسبت یکسانی از هر دو درز می‌افتند، پس اضافه پارچه پخش می‌شود
        $this->assertCount(3, $walk['notches']);

        foreach ([[0.25, 7.5, 6.875], [0.5, 15.0, 13.75], [0.75, 22.5, 20.625]] as $index => [$at, $ya, $yb]) {
            $this->assertEqualsWithDelta($at, $walk['notches'][$index]['at'], 1e-6);
            $this->assertEqualsWithDelta($ya, $walk['notches'][$index]['a']['y'], self::TOLERANCE);
            $this->assertEqualsWithDelta($yb, $walk['notches'][$index]['b']['y'], self::TOLERANCE);
        }
    }

    public function test_walking_counts_a_dart_out_of_the_seam_it_sits_on(): void
    {
        $block = $this->block();
        [, $b] = $this->seamPair();

        // درز پهلوی بالاتنه ۲۶ سانتی‌متر است ولی ۴ سانتی‌متر آن ساسون سینه است
        $this->assertEqualsWithDelta(26.0, PieceOps::edgeLength($block, 'side'), self::TOLERANCE);
        $this->assertEqualsWithDelta(22.0, PieceOps::seamLength($block, 'side'), self::TOLERANCE);

        $walk = PieceOps::walk($block, 'side', $b, 'side');
        $this->assertEqualsWithDelta(-5.5, $walk['difference'], self::TOLERANCE);
    }

    public function test_true_seam_brings_two_seams_to_the_same_length(): void
    {
        [$a, $b] = $this->seamPair();

        $trued = PieceOps::trueSeam($a, 'side', $b, 'side');

        $this->assertSame('a', $trued['adjusted'], 'درز بلندتر باید کوتاه شود.');
        $this->assertEqualsWithDelta(27.5, $trued['length'], self::TOLERANCE);
        $this->assertEqualsWithDelta(0.0, $trued['difference'], self::TOLERANCE);
        $this->assertLessThanOrEqual(4, $trued['rounds']);

        $after = PieceOps::walk($trued['a'], 'side', $trued['b'], 'side');
        $this->assertTrue($after['matched'], 'پس از راست‌سازی دو درز باید هم‌اندازه باشند.');
        $this->assertEqualsWithDelta(0.0, $after['difference'], self::TOLERANCE);

        // قطعه دست‌نخورده نباید عوض شود
        $this->assertSame($b['outline'], $trued['b']['outline']);
    }

    public function test_true_seam_can_be_told_which_side_to_move(): void
    {
        [$a, $b] = $this->seamPair();

        $trued = PieceOps::trueSeam($a, 'side', $b, 'side', ['adjust' => 'b']);

        $this->assertSame('b', $trued['adjusted']);
        $this->assertEqualsWithDelta(30.0, $trued['length'], self::TOLERANCE);
        $this->assertEqualsWithDelta(30.0, PieceOps::seamLength($trued['b'], 'side'), self::TOLERANCE);
    }

    public function test_unfolding_a_piece_doubles_it_and_refolding_gives_it_back(): void
    {
        $panel = $this->curvedPanel();
        $area = $this->preciseArea($panel['outline']);

        $this->assertEqualsWithDelta(172.0, $area, 0.001);

        $open = PieceOps::unfold($panel);

        $this->assertEqualsWithDelta(
            2 * $area,
            $this->preciseArea($open['outline']),
            0.005,
            'باز کردن تا باید دقیقاً دو برابر قطعه بدهد؛ اگر انحنای لبه بسته‌شدن گم شود کم می‌آید.',
        );
        $this->assertFalse($open['on_fold']);
        $this->assertSame([], $open['meta']['fold_edges']);
        $this->assertFalse(Geometry::selfIntersects($open['outline']));

        $closed = PieceOps::refold($open);

        $this->assertEqualsWithDelta(
            $area,
            $this->preciseArea($closed['outline']),
            0.01,
            'بستن دوباره تا باید همان نیم‌قطعه نخست را بدهد.',
        );
        $this->assertTrue($closed['on_fold']);
        $this->assertNotSame([], $closed['meta']['fold_edges']);
        $this->assertEqualsWithDelta(
            Geometry::width($panel['outline']),
            Geometry::width($closed['outline']),
            0.01,
        );
    }

    public function test_unfolding_a_bodice_mirrors_its_darts_and_notches(): void
    {
        $block = $this->block();

        $open = PieceOps::unfold($block);

        $this->assertCount(2, $open['darts'], 'ساسون قرینه هم باید افزوده شود.');
        $this->assertCount(2, $open['notches']);
        $this->assertEqualsWithDelta(48.0, Geometry::width($open['outline']), 0.01);
        $this->assertEqualsWithDelta(
            2 * $this->preciseArea($block['outline']),
            $this->preciseArea($open['outline']),
            0.01,
        );
    }

    public function test_extending_the_hem_moves_only_that_edge(): void
    {
        $block = $this->block();

        $longer = PieceOps::extend($block, 'waist', 5.0);

        $this->assertEqualsWithDelta(49.0, Geometry::height($longer['outline']), 0.01);
        $this->assertEqualsWithDelta(24.0, Geometry::width($longer['outline']), 0.01);
        $this->assertEqualsWithDelta(24.0, PieceOps::edgeLength($longer, 'waist'), self::TOLERANCE);
        $this->assertEqualsWithDelta(5.0, (float) $longer['meta']['extended']['waist'], 1e-6);

        $shorter = PieceOps::extend($block, 'waist', -3.0);
        $this->assertEqualsWithDelta(41.0, Geometry::height($shorter['outline']), 0.01);
    }

    public function test_cutting_a_piece_and_sewing_it_back_returns_the_same_outline(): void
    {
        $panel = $this->curvedPanel();
        $area = $this->preciseArea($panel['outline']);

        $halves = StyleLineCutter::cutHorizontal($panel, 2.0, ['tag' => 'waist']);

        $this->assertCount(2, $halves);
        $this->assertEqualsWithDelta(
            $area,
            array_sum(array_map(fn (array $half) => $this->preciseArea($half['outline']), $halves)),
            0.01,
            'برش نباید پارچه بسازد یا از بین ببرد.',
        );

        $merged = PieceOps::merge($halves[0], $halves[1]);

        $this->assertEqualsWithDelta(
            $area,
            $this->preciseArea($merged['outline']),
            0.01,
            'دوختن دوباره باید همان قطعه نخست را بدهد.',
        );
        $this->assertEqualsWithDelta(Geometry::width($panel['outline']), Geometry::width($merged['outline']), 0.01);
        $this->assertEqualsWithDelta(Geometry::height($panel['outline']), Geometry::height($merged['outline']), 0.01);
        $this->assertFalse(Geometry::selfIntersects($merged['outline']));
        $this->assertSame(
            count($merged['outline']),
            count(Geometry::edgeTags($merged)),
            'برچسب لبه‌ها باید هم‌اندازه مسیر بماند.',
        );
        $this->assertArrayNotHasKey('cut_edges', $merged['meta']);
    }

    public function test_point_along_a_seam_walks_the_real_distance(): void
    {
        $block = $this->block();

        $at = PieceOps::pointAlong($block, 'waist', 6.0);

        $this->assertEqualsWithDelta(18.0, $at['x'], self::TOLERANCE);
        $this->assertEqualsWithDelta(44.0, $at['y'], self::TOLERANCE);
        $this->assertSame(6, $at['edge']);

        $reversed = PieceOps::pointAlong($block, 'waist', 6.0, reverse: true);
        $this->assertEqualsWithDelta(6.0, $reversed['x'], self::TOLERANCE);
    }
}
