<?php

namespace Tests\Unit\Transform;

use App\Services\Pattern\Transform\DartTool;
use App\Services\Pattern\Transform\FullnessRecorder;
use App\Services\Pattern\Transform\PieceOps;
use InvalidArgumentException;

/**
 * ثبت چین، پیلی و آزادی دوخت.
 *
 * شکل ثبت در توضیح خودِ کلاس آمده و اینجا مو به مو سنجیده می‌شود، چون برگه فنی،
 * برش‌کار و نقشه‌کش هر سه از همین کلیدها می‌خوانند.
 */
class FullnessRecorderTest extends TransformTestCase
{
    public function test_gathers_are_written_in_the_documented_shape(): void
    {
        $block = $this->block();

        $piece = FullnessRecorder::gathers($block, 6, 8.0, ['start' => 0.25, 'end' => 0.75, 'label' => 'چین کمر']);

        $this->assertArrayHasKey('gathers', $piece['meta']);
        $this->assertCount(1, $piece['meta']['gathers']);

        $entry = $piece['meta']['gathers'][0];

        $this->assertSame(
            ['kind', 'edge', 'tag', 'start', 'end', 'amount', 'label', 'ratio'],
            array_keys($entry),
        );
        $this->assertSame('gathers', $entry['kind']);
        $this->assertSame(6, $entry['edge']);
        $this->assertSame('waist', $entry['tag']);
        $this->assertEqualsWithDelta(0.25, $entry['start'], 1e-6);
        $this->assertEqualsWithDelta(0.75, $entry['end'], 1e-6);
        $this->assertEqualsWithDelta(8.0, $entry['amount'], 1e-6);
        $this->assertSame('چین کمر', $entry['label']);

        // لبه کمر ۲۴ سانتی‌متر است، نیمه چین‌خورده ۱۲ و پس از جمع شدن ۴ می‌ماند
        $this->assertEqualsWithDelta(12.0 / 4.0, $entry['ratio'], 0.005);
    }

    public function test_pleat_depth_follows_the_kind_of_fold(): void
    {
        $block = $this->block();

        $knife = FullnessRecorder::pleats($block, 6, 12.0, ['count' => 3, 'type' => 'knife']);
        $this->assertEqualsWithDelta(2.0, (float) $knife['meta']['pleats'][0]['depth'], 1e-6);
        $this->assertSame(3, $knife['meta']['pleats'][0]['count']);
        $this->assertSame('knife', $knife['meta']['pleats'][0]['type']);

        // پیلی جعبه‌ای از دو تای روبه‌رو ساخته می‌شود، پس ژرفای هر تا نصف است
        $box = FullnessRecorder::pleats($block, 6, 12.0, ['count' => 3, 'type' => 'box']);
        $this->assertEqualsWithDelta(1.0, (float) $box['meta']['pleats'][0]['depth'], 1e-6);

        $single = FullnessRecorder::pleats($block, 6, 6.0, ['type' => 'inverted']);
        $this->assertEqualsWithDelta(1.5, (float) $single['meta']['pleats'][0]['depth'], 1e-6);
    }

    public function test_an_unknown_pleat_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FullnessRecorder::pleats($this->block(), 6, 4.0, ['type' => 'zigzag']);
    }

    public function test_ease_is_recorded_but_does_not_shorten_the_seam(): void
    {
        $block = $this->block();
        $before = PieceOps::seamLength($block, 'armhole');

        $piece = FullnessRecorder::ease($block, 2, 1.5);

        $this->assertEqualsWithDelta(1.5, FullnessRecorder::amountOn($piece, 2, 'ease'), 1e-6);
        $this->assertEqualsWithDelta(0.0, FullnessRecorder::consumedOn($piece, 2), 1e-6);
        $this->assertEqualsWithDelta($before, PieceOps::seamLength($piece, 'armhole'), self::TOLERANCE);
    }

    public function test_what_a_seam_eats_is_dart_plus_pleat_plus_gathers(): void
    {
        $block = $this->block();

        // لبه ۴ همان دهانه ساسون سینه است: ۴ سانتی‌متر
        $this->assertEqualsWithDelta(4.0, FullnessRecorder::consumedOn($block, 4), 1e-6);

        $piece = FullnessRecorder::gathers($block, 4, 2.5);
        $this->assertEqualsWithDelta(6.5, FullnessRecorder::consumedOn($piece, 4), 1e-6);

        $piece = FullnessRecorder::pleats($piece, 4, 1.5, ['count' => 1, 'type' => 'knife']);
        $this->assertEqualsWithDelta(8.0, FullnessRecorder::consumedOn($piece, 4), 1e-6);

        $this->assertEqualsWithDelta(4.0, FullnessRecorder::amountOn($piece, 4), 1e-6);
        $this->assertEqualsWithDelta(2.5, FullnessRecorder::amountOn($piece, 4, 'gathers'), 1e-6);
    }

    public function test_zero_or_negative_fullness_is_not_recorded(): void
    {
        $block = $this->block();

        $this->assertSame($block, FullnessRecorder::gathers($block, 6, 0.0));
        $this->assertSame($block, FullnessRecorder::gathers($block, 6, -3.0));
    }

    public function test_clearing_removes_only_what_was_asked_for(): void
    {
        $piece = FullnessRecorder::gathers($this->block(), 6, 4.0);
        $piece = FullnessRecorder::gathers($piece, 2, 1.0);
        $piece = FullnessRecorder::ease($piece, 6, 0.8);

        $this->assertCount(2, $piece['meta']['gathers']);

        $only = FullnessRecorder::clear($piece, 6, 'gathers');
        $this->assertCount(1, $only['meta']['gathers']);
        $this->assertSame(2, $only['meta']['gathers'][0]['edge']);
        $this->assertCount(1, $only['meta']['ease']);

        $everything = FullnessRecorder::clear($piece);
        $this->assertArrayNotHasKey('gathers', $everything['meta']);
        $this->assertArrayNotHasKey('ease', $everything['meta']);
    }

    public function test_remap_moves_the_records_to_their_new_edge_numbers(): void
    {
        $piece = FullnessRecorder::gathers($this->block(), 6, 4.0);

        $moved = FullnessRecorder::remap($piece, [6 => 2]);

        $this->assertSame(2, $moved['meta']['gathers'][0]['edge']);
        $this->assertEqualsWithDelta(4.0, FullnessRecorder::amountOn($moved, 2, 'gathers'), 1e-6);
    }

    public function test_a_dart_turned_into_pleats_keeps_the_same_amount_of_cloth(): void
    {
        $block = $this->block();
        $intake = (float) $block['darts'][0]['intake'];

        $pleated = DartTool::toPleats($block, 'ساسون سینه', ['count' => 2, 'type' => 'knife']);

        $this->assertSame([], $pleated['darts']);
        $this->assertEqualsWithDelta($intake, FullnessRecorder::amountOn($pleated, 4, 'pleats'), 1e-6);
        $this->assertEqualsWithDelta(
            PieceOps::seamLength($block, 'side'),
            PieceOps::seamLength($pleated, 'side'),
            self::TOLERANCE,
            'تبدیل ساسون به پیلی نباید طول دوخته‌شده درز را عوض کند.',
        );
    }

    public function test_all_records_are_listed_together(): void
    {
        $piece = FullnessRecorder::gathers($this->block(), 6, 4.0);
        $piece = FullnessRecorder::pleats($piece, 4, 2.0, ['type' => 'box']);
        $piece = FullnessRecorder::ease($piece, 2, 1.0);

        $all = FullnessRecorder::all($piece);

        $this->assertCount(3, $all);
        $this->assertSame(['gathers', 'pleats', 'ease'], array_column($all, 'kind'));
        $this->assertEqualsWithDelta(7.0, array_sum(array_column($all, 'amount')), 1e-6);
    }
}
