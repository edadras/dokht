<?php

namespace Tests\Unit\Transform;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\Outline;

/**
 * دفترداری مسیر قطعه: لنگر، شکستن لبه، برعکس کردن زنجیره و چسباندن.
 *
 * ایرادهای این کلاس در همه عملیات بالادست (چرخاندن ساسون، برش سبک‌خط، باز کردن
 * تا) پخش می‌شود، پس اینجا با عددهای دستی سنجیده می‌شود.
 */
class OutlineTest extends TransformTestCase
{
    /** مربع ۱۰×۱۰ با برچسب لبه‌های مشخص. */
    protected function square(): array
    {
        return [
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(10, 0),
                Geometry::point(10, 10),
                Geometry::point(0, 10),
            ],
            'meta' => ['edges' => ['hem', 'side', 'waist', 'default']],
            'darts' => [],
            'notches' => [],
        ];
    }

    public function test_two_anchors_on_one_edge_land_where_they_were_asked_to(): void
    {
        $square = $this->square();

        $result = Outline::insertAnchors($square['outline'], $square['meta']['edges'], [
            'a' => ['edge' => 0, 't' => 0.25],
            'b' => ['edge' => 0, 't' => 0.75],
        ]);

        $this->assertCount(6, $result['outline']);
        $this->assertEqualsWithDelta(2.5, $result['outline'][$result['index']['a']]['x'], 1e-6);
        $this->assertEqualsWithDelta(7.5, $result['outline'][$result['index']['b']]['x'], 1e-6);
        $this->assertSame(['hem', 'hem', 'hem', 'side', 'waist', 'default'], $result['tags']);
    }

    public function test_three_anchors_on_one_edge_keep_their_own_ratios(): void
    {
        $square = $this->square();

        $result = Outline::insertAnchors($square['outline'], $square['meta']['edges'], [
            'a' => ['edge' => 1, 't' => 0.2],
            'b' => ['edge' => 1, 't' => 0.5],
            'c' => ['edge' => 1, 't' => 0.9],
        ]);

        $this->assertCount(7, $result['outline']);

        foreach (['a' => 2.0, 'b' => 5.0, 'c' => 9.0] as $key => $y) {
            $this->assertEqualsWithDelta(10.0, $result['outline'][$result['index'][$key]]['x'], 1e-6);
            $this->assertEqualsWithDelta($y, $result['outline'][$result['index'][$key]]['y'], 1e-6);
        }
    }

    public function test_two_anchors_on_the_same_spot_share_one_vertex(): void
    {
        $square = $this->square();

        $result = Outline::insertAnchors($square['outline'], $square['meta']['edges'], [
            'a' => ['edge' => 2, 't' => 0.4],
            'b' => ['edge' => 2, 't' => 0.4],
        ]);

        $this->assertCount(5, $result['outline'], 'دو لنگر روی یک نقطه نباید دو رأس چسبیده بسازند.');
        $this->assertSame($result['index']['a'], $result['index']['b']);
        $this->assertEqualsWithDelta(6.0, $result['outline'][$result['index']['a']]['x'], 1e-6);
    }

    public function test_an_anchor_on_an_end_point_does_not_add_a_vertex(): void
    {
        $square = $this->square();

        $result = Outline::insertAnchors($square['outline'], $square['meta']['edges'], [
            'a' => ['edge' => 1, 't' => 0.0],
            'b' => ['edge' => 1, 't' => 1.0],
        ]);

        $this->assertCount(4, $result['outline']);
        $this->assertSame(1, $result['index']['a']);
        $this->assertSame(2, $result['index']['b']);
    }

    public function test_splitting_a_curved_edge_keeps_its_shape(): void
    {
        $outline = [
            Geometry::point(0, 0),
            Geometry::curve(10, 0, 5, 6),
            Geometry::point(10, 10),
            Geometry::point(0, 10),
        ];

        $before = Geometry::edgeLength($outline, 0);
        $split = Geometry::splitEdgeAt($outline, 0, 0.5);

        $this->assertCount(5, $split);
        $this->assertEqualsWithDelta(5.0, $split[1]['x'], 1e-6);
        $this->assertEqualsWithDelta(3.0, $split[1]['y'], 1e-6);
        $this->assertEqualsWithDelta(
            $before,
            Geometry::edgeLength($split, 0) + Geometry::edgeLength($split, 1),
            0.02,
            'دو نیمه منحنی روی هم باید همان طول را بدهند.',
        );
        $this->assertEqualsWithDelta(
            abs(Geometry::signedArea(Geometry::flatten($outline, 800))),
            abs(Geometry::signedArea(Geometry::flatten($split, 800))),
            0.005,
        );
    }

    public function test_reversing_a_chain_moves_every_control_point_with_it(): void
    {
        $chain = [
            Geometry::point(0, 0),
            Geometry::curve(10, 0, 5, 6),
            Geometry::point(10, 10),
        ];

        $reversed = Outline::reverse($chain);

        $this->assertEqualsWithDelta(10.0, $reversed[0]['x'], 1e-6);
        $this->assertEqualsWithDelta(10.0, $reversed[0]['y'], 1e-6);
        $this->assertArrayNotHasKey('curve', $reversed[0]);
        $this->assertArrayNotHasKey('curve', $reversed[1]);
        $this->assertTrue(Geometry::isCurve($reversed[2]));
        $this->assertEqualsWithDelta(5.0, $reversed[2]['cx'], 1e-6);
        $this->assertEqualsWithDelta(6.0, $reversed[2]['cy'], 1e-6);

        $this->assertEqualsWithDelta(
            Outline::chainLength($chain),
            Outline::chainLength($reversed),
            1e-4,
        );
    }

    public function test_closing_a_path_keeps_the_curve_of_the_last_edge(): void
    {
        // زنجیره‌ای که نقطه پایانش با نقطه آغازش یکی است و لبه بسته‌شدن منحنی است
        $joined = Outline::join([[
            'points' => [
                Geometry::point(0, 0),
                Geometry::point(10, 0),
                Geometry::point(10, 10),
                Geometry::curve(0, 0, -4, 6),
            ],
            'tags' => ['hem', 'side', 'default', 'default'],
        ]], 'default');

        $this->assertCount(3, $joined['outline']);
        $this->assertTrue(
            Geometry::isCurve($joined['outline'][0]),
            'انحنای لبه بسته‌شدن نباید با حذف نقطه تکراری گم شود.',
        );
        $this->assertEqualsWithDelta(-4.0, $joined['outline'][0]['cx'], 1e-6);

        // مساحت با کمان بیرون‌زده بیشتر از مثلث ساده است
        $this->assertGreaterThan(50.0, abs(Geometry::signedArea(Geometry::flatten($joined['outline'], 800))));
    }

    public function test_dedupe_drops_repeated_points_and_keeps_the_tag_count_right(): void
    {
        $result = Outline::dedupe(
            [
                Geometry::point(0, 0),
                Geometry::point(0, 0),
                Geometry::point(10, 0),
                Geometry::point(10, 10),
                Geometry::point(0, 10),
            ],
            ['hem', 'hem', 'side', 'waist', 'default'],
        );

        $this->assertCount(4, $result['outline']);
        $this->assertCount(4, $result['tags']);
        $this->assertSame(['hem', 'side', 'waist', 'default'], $result['tags']);
    }

    public function test_an_anchor_can_be_named_by_edge_tag_or_by_point(): void
    {
        $block = $this->block();

        $byTag = Outline::anchor($block, ['edge' => 'shoulder', 't' => 0.5]);
        $this->assertSame(1, $byTag['edge']);
        $this->assertEqualsWithDelta(12.5, $byTag['point']['x'], 1e-6);
        $this->assertEqualsWithDelta(2.0, $byTag['point']['y'], 1e-6);

        $byPoint = Outline::anchor($block, ['at' => ['x' => 12.5, 'y' => 1.6]]);
        $this->assertSame(1, $byPoint['edge']);
        $this->assertEqualsWithDelta(0.5, $byPoint['t'], 0.02);
    }

    public function test_reindex_puts_notches_back_on_the_edge_they_touch(): void
    {
        $block = $this->block();
        $block['notches'][0]['edge'] = 99;

        $fixed = Outline::reindex($block);

        $this->assertSame(1, $fixed['notches'][0]['edge']);
    }
}
