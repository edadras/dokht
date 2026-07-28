<?php

namespace Tests\Unit\Transform;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Services\Pattern\Transform\StyleLineCutter;
use InvalidArgumentException;

/** برش سبک‌خط: یوک، خط سینه، کمر افتاده و هر درزی که در بلوک پایه نیست. */
class StyleLineCutterTest extends TransformTestCase
{
    public function test_a_horizontal_cut_through_a_curved_edge_keeps_the_total_area(): void
    {
        $panel = $this->curvedPanel();
        $area = $this->preciseArea($panel['outline']);

        $halves = StyleLineCutter::cutHorizontal($panel, 2.0, ['tag' => 'waist']);

        $this->assertCount(2, $halves);

        $sum = array_sum(array_map(fn (array $half) => $this->preciseArea($half['outline']), $halves));

        $this->assertEqualsWithDelta(
            $area,
            $sum,
            0.005,
            'برش از روی لبه منحنی باید با دو کاستلژو انجام شود تا خمیدگی و مساحت سالم بماند.',
        );

        // قطعه بالایی اول برمی‌گردد
        $this->assertLessThan(
            Geometry::centroid($halves[1]['outline'])['y'] + Geometry::bounds($halves[1]['outline'])[1],
            Geometry::centroid($halves[0]['outline'])['y'] + Geometry::bounds($halves[0]['outline'])[1],
        );

        foreach ($halves as $half) {
            $this->assertSame([], Geometry::validatePiece($half));
            $this->assertFalse(Geometry::selfIntersects($half['outline']));
            $this->assertNotSame([], $half['meta']['cut_edges']);
        }

        // دو درز تازه باید دقیقاً هم‌اندازه باشند وگرنه به هم دوخته نمی‌شوند
        $this->assertEqualsWithDelta(
            PieceOps::edgeLength($halves[0], $halves[0]['meta']['cut_edges']),
            PieceOps::edgeLength($halves[1], $halves[1]['meta']['cut_edges']),
            0.02,
        );
    }

    public function test_the_cut_keeps_the_curve_of_the_edge_it_passes_through(): void
    {
        $panel = $this->curvedPanel();

        // لبه راست از (0,0) با نقطه کنترل (6,0) به (10,4) می‌رود؛ طول آن ثابت است
        $before = Geometry::edgeLength($panel['outline'], 0);

        $halves = StyleLineCutter::cutHorizontal($panel, 2.0, ['tag' => 'waist']);

        $after = 0.0;

        foreach ($halves as $half) {
            foreach (Geometry::edgesWithTag($half, 'neck') as $edge) {
                $after += Geometry::edgeLength($half['outline'], $edge);
            }
        }

        $this->assertEqualsWithDelta(
            $before,
            $after,
            0.02,
            'دو نیمه لبه منحنی روی هم باید همان طول لبه نخست را بدهند.',
        );
    }

    public function test_a_cut_carries_darts_notches_and_markers_to_the_half_they_belong_to(): void
    {
        $block = $this->block();

        $halves = StyleLineCutter::cutHorizontal($block, 34.0, ['tag' => 'waist']);

        [$top, $bottom] = $halves;

        $this->assertEqualsWithDelta(34.0, Geometry::height($top['outline']), 0.01, 'نیمه بالا باید اول برگردد.');
        $this->assertEqualsWithDelta(10.0, Geometry::height($bottom['outline']), 0.01);

        $this->assertCount(1, $top['darts'], 'ساسون سینه باید در نیمه بالا بماند.');
        $this->assertSame([], $bottom['darts']);

        $shoulder = array_filter($top['notches'], fn ($notch) => ($notch['pair'] ?? null) === 'shoulder');
        $this->assertCount(1, $shoulder, 'نشانه سرشانه باید با نیمه بالا برود.');
        $this->assertCount(1, $top['markers'], 'خط سینه باید با نیمه بالا برود.');
        $this->assertSame([], $bottom['markers']);

        foreach ($halves as $half) {
            $this->assertNotEmpty($half['grainline']['from'] ?? null);
            [$minX, $minY, $maxX, $maxY] = Geometry::bounds($half['outline']);
            $this->assertGreaterThanOrEqual($minX - 0.01, (float) $half['grainline']['from']['x']);
            $this->assertLessThanOrEqual($maxX + 0.01, (float) $half['grainline']['to']['x']);
            $this->assertGreaterThanOrEqual($minY - 0.01, (float) $half['grainline']['from']['y']);
            $this->assertLessThanOrEqual($maxY + 0.01, (float) $half['grainline']['to']['y']);
        }
    }

    public function test_paired_notches_land_opposite_each_other_on_the_new_seam(): void
    {
        $panel = $this->curvedPanel();

        $halves = StyleLineCutter::cutHorizontal($panel, 10.0, ['tag' => 'waist', 'notches' => [0.5]]);

        $found = 0;

        // هر دو نیمه به مبدأ خودشان برده می‌شوند، پس نشانه را روی خودِ درز برش می‌سنجیم
        foreach ($halves as $half) {
            $pairs = array_values(array_filter(
                $half['notches'],
                fn ($notch) => str_contains((string) ($notch['pair'] ?? ''), 'style-'),
            ));

            $this->assertCount(1, $pairs);
            $found++;

            $edges = $half['meta']['cut_edges'];
            $middle = PieceOps::pointAlong($half, $edges, PieceOps::edgeLength($half, $edges) / 2);

            $this->assertEqualsWithDelta($middle['x'], (float) $pairs[0]['x'], 0.05);
            $this->assertEqualsWithDelta($middle['y'], (float) $pairs[0]['y'], 0.05);
        }

        $this->assertSame(2, $found);
    }

    public function test_on_fold_survives_a_cut_that_keeps_the_centre_line(): void
    {
        $block = $this->block();

        $halves = StyleLineCutter::cutHorizontal($block, 34.0, ['tag' => 'waist']);

        foreach ($halves as $half) {
            $this->assertTrue($half['on_fold'], 'هر دو نیمه هنوز خط مرکز جلو را دارند.');
            $this->assertNotSame([], $half['meta']['fold_edges']);
        }
    }

    public function test_a_line_that_misses_the_piece_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StyleLineCutter::cutHorizontal($this->curvedPanel(), 100.0);
    }

    public function test_crossings_finds_where_a_horizontal_line_meets_the_outline(): void
    {
        $panel = $this->curvedPanel();

        $found = StyleLineCutter::crossings($panel['outline'], 10.0);

        $this->assertCount(2, $found);

        usort($found, fn ($a, $b) => $a['x'] <=> $b['x']);

        $this->assertEqualsWithDelta(0.0, $found[0]['x'], 0.01);
        $this->assertEqualsWithDelta(9.25, $found[1]['x'], 0.05);
    }
}
