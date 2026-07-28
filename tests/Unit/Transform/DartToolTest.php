<?php

namespace Tests\Unit\Transform;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\DartTool;
use App\Services\Pattern\Transform\Outline;
use App\Services\Pattern\Transform\PieceOps;

/**
 * چرخاندن، تقسیم و درزکردن ساسون.
 *
 * قانون طلایی این است که چرخاندن ساسون پارچه نمی‌سازد و از بین نمی‌برد؛ پس در
 * همه آزمون‌های زیر مساحت دوخته‌شده، طول همه درزها، جای نوک و زاویه ساسون باید
 * دست‌نخورده بمانند.
 */
class DartToolTest extends TransformTestCase
{
    public function test_the_sample_block_has_the_area_and_dart_value_we_expect(): void
    {
        $block = $this->block();

        $this->assertEqualsWithDelta(940.0, $this->preciseArea($block['outline']), 0.001);
        $this->assertEqualsWithDelta(24.0, DartTool::wedgeArea($block['darts'][0]), 0.001);
        $this->assertEqualsWithDelta(916.0, $this->preciseNetArea($block), 0.001);
        $this->assertEqualsWithDelta(0.330297, DartTool::angleOf($block['darts'][0]), 1e-5);
    }

    public function test_rotating_the_bust_dart_to_the_shoulder_keeps_area_girth_and_apex(): void
    {
        $block = $this->block();
        $apex = ['x' => 12.0, 'y' => 26.0];

        $rotated = DartTool::rotate($block, 'ساسون سینه', ['edge' => 'shoulder', 't' => 0.5]);
        $moved = end($rotated['darts']);

        // مساحت دوخته‌شده: مثلثی که در پهلو بسته شد، سرشانه باز شده است
        $this->assertEqualsWithDelta(
            $this->preciseNetArea($block),
            $this->preciseNetArea($rotated),
            0.05,
            'چرخاندن ساسون مساحت دوخته‌شده را عوض کرد.',
        );

        // دور سینه تمام‌شده
        $this->assertEqualsWithDelta(
            $this->bustGirthAround($block, $apex),
            $this->bustGirthAround($rotated, ['x' => (float) $moved['apex']['x'], 'y' => (float) $moved['apex']['y']]),
            0.05,
            'دور سینه با چرخاندن ساسون عوض شد.',
        );

        // نوک ساسون سرِ جایش می‌ماند
        $this->assertEqualsWithDelta(12.0, (float) $moved['apex']['x'], 0.005);
        $this->assertEqualsWithDelta(26.0, (float) $moved['apex']['y'], 0.005);

        // ارزش ساسون همان زاویه است، نه پهنای دهانه
        $this->assertEqualsWithDelta(
            DartTool::angleOf($block['darts'][0]),
            DartTool::angleOf($moved),
            1e-4,
            'زاویه ساسون (ارزش آن) در چرخاندن حفظ نشد.',
        );

        // دهانه تازه پهن‌تر است چون از نوک دورتر است
        $this->assertEqualsWithDelta(7.89, (float) $moved['intake'], 0.02);
        $this->assertGreaterThan((float) $block['darts'][0]['intake'], (float) $moved['intake']);

        $this->assertSame([], Geometry::validatePiece($rotated));
        $this->assertFalse(Geometry::selfIntersects($rotated['outline']));
    }

    public function test_rotating_a_dart_leaves_every_sewn_seam_length_untouched(): void
    {
        $block = $this->block();

        foreach (['neck' => 10.63, 'shoulder' => 11.70, 'armhole' => 15.23, 'side' => 22.00, 'waist' => 24.00, 'default' => 36.00] as $tag => $expected) {
            $this->assertEqualsWithDelta($expected, PieceOps::seamLength($block, $tag), self::TOLERANCE);
        }

        foreach (['shoulder', 'armhole', 'waist', 'neck'] as $target) {
            $rotated = DartTool::rotate($block, 'ساسون سینه', ['edge' => $target, 't' => 0.5]);

            foreach (['neck' => 10.63, 'shoulder' => 11.70, 'armhole' => 15.23, 'side' => 22.00, 'waist' => 24.00, 'default' => 36.00] as $tag => $expected) {
                $this->assertEqualsWithDelta(
                    $expected,
                    PieceOps::seamLength($rotated, $tag),
                    self::TOLERANCE,
                    "چرخاندن ساسون به «{$target}» طول درز «{$tag}» را عوض کرد.",
                );
            }
        }
    }

    public function test_the_rotated_dart_knows_which_edge_it_sits_on(): void
    {
        $rotated = DartTool::rotate($this->block(), 'ساسون سینه', ['edge' => 'shoulder', 't' => 0.5]);
        $moved = end($rotated['darts']);

        $this->assertIsInt($moved['edge'], 'ساسون تازه شماره لبه نگرفت.');
        $this->assertSame('shoulder', Geometry::edgeTags($rotated)[$moved['edge']]);

        // هر دو پا باید روی همان لبه بنشینند
        foreach ($moved['legs'] as $leg) {
            $near = Geometry::nearestEdge($rotated['outline'], ['x' => (float) $leg['x'], 'y' => (float) $leg['y']]);
            $this->assertLessThan(0.01, $near['distance']);
        }
    }

    public function test_a_belly_dart_that_floats_inside_the_piece_keeps_no_edge(): void
    {
        $block = $this->block();
        $block['darts'][] = [
            'type' => 'waist',
            'label' => 'ساسون بادامی',
            'edge' => null,
            'axis' => 'x',
            'intake' => 2.0,
            'center' => Geometry::point(10, 34),
            'apex' => Geometry::point(10, 26),
            'legs' => [Geometry::point(9, 34), Geometry::point(11, 34)],
        ];

        $reindexed = Outline::reindex($block);

        $this->assertNull($reindexed['darts'][1]['edge'], 'ساسون بادامی نباید به لبه‌ای چسبانده شود.');
        $this->assertSame(4, $reindexed['darts'][0]['edge']);
    }

    public function test_splitting_a_dart_keeps_the_total_intake(): void
    {
        $block = $this->block();

        $two = DartTool::split($block, 'ساسون سینه', 2, ['gap' => 1.0]);
        $this->assertCount(2, $two['darts']);
        $this->assertEqualsWithDelta(4.0, array_sum(array_column($two['darts'], 'intake')), 0.005);

        $three = DartTool::split($block, 'ساسون سینه', 3, ['gap' => 0.8]);
        $this->assertCount(3, $three['darts']);
        // هر سهم به دو رقم اعشار گرد می‌شود، پس خطای جمع حداکثر ۳×۰٫۰۰۵ است
        $this->assertEqualsWithDelta(4.0, array_sum(array_column($three['darts'], 'intake')), 0.015);

        $weighted = DartTool::split($block, 'ساسون سینه', 2, ['weights' => [3, 1], 'gap' => 1.0]);
        $this->assertEqualsWithDelta(3.0, (float) $weighted['darts'][0]['intake'], 0.005);
        $this->assertEqualsWithDelta(1.0, (float) $weighted['darts'][1]['intake'], 0.005);
        $this->assertEqualsWithDelta(4.0, array_sum(array_column($weighted['darts'], 'intake')), 0.005);

        foreach ($three['darts'] as $dart) {
            $this->assertSame(4, $dart['edge'], 'ساسون‌های تقسیم‌شده باید روی همان لبه بمانند.');
        }
    }

    public function test_turning_a_dart_into_a_seam_gives_two_panels_with_equal_new_seams(): void
    {
        $block = $this->block();

        $panels = DartTool::toSeam($block, 'ساسون سینه', [
            'to' => ['edge' => 'shoulder', 't' => 0.5],
            'absorb' => false,
        ]);

        $this->assertCount(2, $panels);

        $lengths = array_map(
            fn (array $panel) => PieceOps::edgeLength($panel, $panel['meta']['cut_edges']),
            $panels,
        );

        $this->assertGreaterThan(10.0, $lengths[0]);
        $this->assertEqualsWithDelta(
            $lengths[0],
            $lengths[1],
            0.02,
            'درز تازه دو پانل هم‌اندازه نیست و دوخته نمی‌شود.',
        );

        $sum = array_sum(array_map(fn (array $panel) => $this->preciseArea($panel['outline']), $panels));

        $this->assertEqualsWithDelta(
            $this->preciseNetArea($block),
            $sum,
            0.05,
            'مجموع مساحت دو پانل با مساحت دوخته‌شده قطعه یکی نیست.',
        );

        foreach ($panels as $panel) {
            $this->assertSame([], Geometry::validatePiece($panel));
            $this->assertFalse(Geometry::selfIntersects($panel['outline']));
            $this->assertSame([], $panel['darts'], 'ساسون باید در درز حل شده باشد.');
            $this->assertCount(2, $panel['meta']['cut_edges']);
        }

        // فقط پانلی که خط مرکز جلو را دارد روی تای پارچه می‌ماند
        $onFold = array_values(array_filter($panels, fn (array $panel) => $panel['meta']['fold_edges'] !== []));
        $this->assertCount(1, $onFold);
    }

    public function test_the_cut_seam_is_not_confused_with_the_side_seam_that_was_already_there(): void
    {
        $panels = DartTool::toSeam($this->block(), 'ساسون سینه', [
            'to' => ['edge' => 'shoulder', 't' => 0.5],
            'absorb' => false,
            'tag' => 'side',
        ]);

        foreach ($panels as $panel) {
            $this->assertCount(
                2,
                $panel['meta']['cut_edges'],
                'درز برش باید از روی هندسه پیدا شود، نه از روی برچسب side که پیش‌تر هم روی قطعه بود.',
            );
        }
    }

    public function test_a_dart_becomes_gathers_without_touching_the_outline(): void
    {
        $block = $this->block();
        $before = $this->preciseArea($block['outline']);

        $gathered = DartTool::toGathers($block, 'ساسون سینه', ['span' => 2.0]);

        $this->assertSame([], $gathered['darts']);
        $this->assertEqualsWithDelta($before, $this->preciseArea($gathered['outline']), 0.001);
        $this->assertCount(1, $gathered['meta']['gathers']);
        $this->assertEqualsWithDelta(4.0, (float) $gathered['meta']['gathers'][0]['amount'], 0.001);
        $this->assertSame(4, $gathered['meta']['gathers'][0]['edge']);

        // پارچه‌ای که با چین جمع می‌شود، از طول دوخته‌شده درز کم می‌کند
        $this->assertEqualsWithDelta(
            PieceOps::seamLength($block, 'side'),
            PieceOps::seamLength($gathered, 'side'),
            self::TOLERANCE,
        );
    }
}
