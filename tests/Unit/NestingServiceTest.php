<?php

namespace Tests\Unit;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\Pattern;
use App\Services\Cutting\NestingService;
use App\Support\FabricProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NestingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NestingService $nesting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nesting = new NestingService;
    }

    public function test_two_pieces_fit_on_a_folded_fabric_with_a_plausible_length(): void
    {
        $pattern = Pattern::factory()->withSimplePieces()->create();

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140]);

        $this->assertSame(140.0, $result['fabric_width_cm']);
        $this->assertSame(70.0, $result['usable_width_cm']);
        $this->assertCount(2, $result['placements']);
        $this->assertSame([], $result['unplaced']);

        // دو قطعه ۴۸×۶۲ و ۴۶×۶۲ در عرض مفید ۷۰ کنار هم جا نمی‌شوند، پس زیر هم می‌روند
        $this->assertGreaterThan(120, $result['required_length_cm']);
        $this->assertLessThan(300, $result['required_length_cm']);
        $this->assertSame(round($result['required_length_cm'] / 100, 2), $result['required_meters']);

        $this->assertGreaterThan(0, $result['waste_percent']);
        $this->assertLessThan(100, $result['waste_percent']);
        $this->assertEqualsWithDelta(100, $result['waste_percent'] + $result['efficiency'], 0.02);

        foreach ($result['placements'] as $placement) {
            $this->assertGreaterThanOrEqual(0, $placement['x']);
            $this->assertLessThanOrEqual(70.001, $placement['x'] + $placement['width']);
            $this->assertLessThanOrEqual($result['required_length_cm'] + 0.001, $placement['y'] + $placement['height']);
            $this->assertNotSame('', $placement['path']);
            $this->assertStringStartsWith('matrix(', $placement['transform']);
        }
    }

    public function test_placed_pieces_do_not_overlap(): void
    {
        $pattern = $this->patternWith([
            $this->piece('a', 'قطعه الف', 30, 40),
            $this->piece('b', 'قطعه ب', 30, 40),
            $this->piece('c', 'قطعه ج', 20, 25),
            $this->piece('d', 'قطعه د', 18, 22),
        ]);

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'gap_cm' => 1]);

        $this->assertCount(4, $result['placements']);

        foreach ($result['placements'] as $i => $a) {
            foreach (array_slice($result['placements'], $i + 1) as $b) {
                $overlapX = $a['x'] < $b['x'] + $b['width'] - 0.001 && $b['x'] < $a['x'] + $a['width'] - 0.001;
                $overlapY = $a['y'] < $b['y'] + $b['height'] - 0.001 && $b['y'] < $a['y'] + $a['height'] - 0.001;

                $this->assertFalse($overlapX && $overlapY, 'قطعه‌ها روی هم افتاده‌اند.');
            }
        }
    }

    public function test_the_layout_is_deterministic_for_the_same_input(): void
    {
        $pattern = $this->crowdedPattern();

        $first = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 150]);
        $second = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 150]);
        $third = (new NestingService)->nest($pattern->fresh('pieces'), null, ['fabric_width_cm' => 150]);

        $this->assertSame($first['placements'], $second['placements']);
        $this->assertSame($first['placements'], $third['placements']);
        $this->assertSame($first['required_length_cm'], $third['required_length_cm']);
        $this->assertSame($first['efficiency'], $third['efficiency']);
    }

    public function test_every_pair_of_placed_pieces_keeps_at_least_the_gap(): void
    {
        $pattern = $this->crowdedPattern();

        foreach ([0.0, 1.0, 2.5] as $gap) {
            $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 150, 'gap_cm' => $gap]);

            $this->assertSame([], $result['unplaced']);
            $this->assertNotEmpty($result['placements']);

            $this->assertSeparated($result, $gap);
        }
    }

    public function test_pieces_stay_inside_the_usable_width_and_the_required_length(): void
    {
        $pattern = $this->crowdedPattern();

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 110]);

        foreach ($result['placements'] as $placement) {
            $this->assertGreaterThanOrEqual(-0.001, $placement['x']);
            $this->assertLessThanOrEqual(
                $result['usable_width_cm'] + 0.001,
                $placement['x'] + $placement['width'],
                'قطعه از عرض مفید پارچه بیرون زده است.',
            );
            $this->assertLessThanOrEqual(
                $result['required_length_cm'] + 0.001,
                $placement['y'] + $placement['height'],
                'قطعه از طول محاسبه‌شده پارچه بیرون زده است.',
            );
        }
    }

    public function test_the_packer_beats_a_naive_one_piece_per_row_baseline(): void
    {
        $pattern = $this->crowdedPattern();

        $result = $this->nesting->nest($pattern, null, [
            'fabric_width_cm' => 150,
            'gap_cm' => 1,
            'include_allowance' => false,
        ]);

        // چیدمان ساده «هر قطعه یک ردیف»: طول برابر جمع بلندی قطعه‌ها می‌شود
        $naive = -1.0;

        foreach ($result['placements'] as $placement) {
            $naive += $placement['height'] + 1;
        }

        $this->assertSame([], $result['unplaced']);
        $this->assertGreaterThan(0, $naive);
        $this->assertLessThan(
            $naive * 0.45,
            $result['required_length_cm'],
            'چیدمان باید دست‌کم دوسوم از حالت «هر قطعه یک ردیف» کوتاه‌تر باشد.',
        );
        $this->assertGreaterThan(78, $result['efficiency']);
    }

    public function test_on_fold_pieces_hug_the_fold_even_in_a_crowded_layout(): void
    {
        $pattern = $this->crowdedPattern();

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 150]);

        $onFold = array_values(array_filter($result['placements'], fn (array $p) => $p['on_fold']));

        $this->assertGreaterThanOrEqual(2, count($onFold));

        foreach ($onFold as $placement) {
            $this->assertSame(0.0, $placement['x'], 'قطعه روی تا از لبه تا کنده شده است.');
            $this->assertSame(0, $placement['rotation']);
        }

        $this->assertSeparated($result, 1.0);
    }

    public function test_respecting_nap_never_turns_a_piece_sideways(): void
    {
        $pattern = $this->crowdedPattern();

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 150, 'respect_nap' => true]);

        $this->assertSame([], $result['unplaced']);
        $this->assertSame([0], array_values(array_unique(array_column($result['placements'], 'rotation'))));
        $this->assertSeparated($result, 1.0);
    }

    public function test_stripe_matching_keeps_the_gap_and_the_snapped_offsets(): void
    {
        $fabric = Fabric::factory()->striped()->create(['width_cm' => 150]);

        $result = $this->nesting->nest($this->crowdedPattern(), $fabric);

        $this->assertSame([], $result['unplaced']);
        $this->assertSeparated($result, (float) $result['options']['gap_cm']);

        foreach ($result['placements'] as $placement) {
            $this->assertEqualsWithDelta(0.0, fmod($placement['y'], 4.0), 0.001);
        }
    }

    public function test_a_piece_wider_than_the_fabric_produces_a_persian_warning_naming_it(): void
    {
        $pattern = $this->patternWith([
            $this->piece('cape', 'شنل گشاد', 160, 180),
        ]);

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140]);

        $warnings = implode(' | ', $result['warnings']);

        $this->assertStringContainsString('شنل گشاد', $warnings);
        $this->assertStringContainsString('جا نمی‌شود', $warnings);
        $this->assertSame(['cape'], $result['unplaced']);
        $this->assertSame([], $result['placements']);
    }

    public function test_respecting_nap_forbids_rotation(): void
    {
        $pattern = $this->patternWith([
            $this->piece('front', 'جلو', 40, 50, ['cut_quantity' => 2]),
        ]);

        $oneWay = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'respect_nap' => true]);

        $this->assertCount(2, $oneWay['placements']);
        $this->assertSame([0, 0], array_column($oneWay['placements'], 'rotation'));
        $this->assertStringContainsString(
            'یک‌جهت',
            implode(' ', $oneWay['warnings']),
        );

        $free = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'respect_nap' => false]);

        // در حالت آزاد، نمونه دوم سر‌به‌پا (۱۸۰ درجه) چیده می‌شود
        $this->assertContains(180, array_column($free['placements'], 'rotation'));
    }

    public function test_nap_is_taken_from_the_fabric_by_default(): void
    {
        $fabric = Fabric::factory()->for(FabricType::factory()->state([
            'profile' => FabricProfile::make(['has_nap' => true])->toArray(),
        ]))->create(['width_cm' => 140, 'surface_pattern' => 'plain']);

        $pattern = Pattern::factory()->withSimplePieces()->create();

        $result = $this->nesting->nest($pattern, $fabric);

        $this->assertTrue($result['options']['respect_nap']);
    }

    public function test_stripe_matching_snaps_offsets_to_the_pattern_repeat_and_warns(): void
    {
        $fabric = Fabric::factory()->striped()->create(['width_cm' => 140]);

        $pattern = $this->patternWith([
            $this->piece('front', 'جلو', 40, 50),
            $this->piece('back', 'پشت', 40, 47),
            $this->piece('sleeve', 'آستین', 30, 33),
        ]);

        $result = $this->nesting->nest($pattern, $fabric);

        $this->assertTrue($result['options']['match_stripes']);
        $this->assertSame(4.0, $result['options']['pattern_repeat_cm']);

        foreach ($result['placements'] as $placement) {
            $this->assertEqualsWithDelta(0.0, fmod($placement['y'], 4.0), 0.001, 'بالای قطعه روی تکرار طرح ننشسته است.');
        }

        $this->assertStringContainsString('راه‌راه', implode(' ', $result['warnings']));

        $plain = $this->nesting->nest($pattern, $fabric, ['match_stripes' => false]);

        $this->assertGreaterThanOrEqual($plain['required_length_cm'], $result['required_length_cm']);
    }

    public function test_a_plain_fabric_reports_no_pattern_matching_at_all(): void
    {
        $result = $this->nesting->nest($this->crowdedPattern(), null, ['fabric_width_cm' => 150]);

        $this->assertFalse($result['pattern_match']['enabled']);
        $this->assertFalse($result['pattern_match']['active']);
        $this->assertSame([], $result['pattern_match']['axes']);
        $this->assertSame([], $result['pattern_match']['seams']);
        $this->assertSame(0.0, $result['pattern_match']['extra_length_cm']);
    }

    public function test_matching_falls_back_to_the_suggested_sewing_relations(): void
    {
        // الگو هیچ درزی ذخیره نکرده است؛ سرویس باید پیشنهاد «دوخت مجازی» را بخواند
        $pattern = $this->patternWith([
            $this->bodicePiece('front', 'تنه جلو', 'front_bodice', 'front', 26.0),
            $this->bodicePiece('back', 'تنه پشت', 'back_bodice', 'back', 29.0),
        ]);

        $this->assertEmpty($pattern->sewing_relations);

        $fabric = Fabric::factory()->striped()->create(['width_cm' => 150]);
        $result = $this->nesting->nest($pattern, $fabric, ['include_allowance' => false]);

        $match = $result['pattern_match'];

        $this->assertGreaterThanOrEqual(1, $match['total']);
        $this->assertGreaterThanOrEqual(1, $match['matched']);

        $side = collect($match['seams'])->firstWhere('label', 'درز پهلو');

        $this->assertNotNull($side);
        $this->assertTrue($side['matched']);
        $this->assertSame(0.0, $side['offset_mm']['y']);

        $front = collect($result['placements'])->firstWhere('code', 'front');
        $back = collect($result['placements'])->firstWhere('code', 'back');

        $this->assertSame(0.0, fmod(($back['y'] + 29.0) - ($front['y'] + 26.0), 4.0));
    }

    public function test_efficiency_uses_the_real_polygon_area(): void
    {
        $pattern = $this->patternWith([
            [
                'code' => 'gore',
                'name' => 'ترک دامن',
                'layer' => 'outer',
                'cut_quantity' => 1,
                'outline' => [
                    ['x' => 0, 'y' => 0],
                    ['x' => 60, 'y' => 60],
                    ['x' => 0, 'y' => 60],
                ],
            ],
        ]);

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'include_allowance' => false]);

        // مساحت مثلث نصف کادر آن است؛ بهره‌وری باید همان را نشان بدهد
        $this->assertEqualsWithDelta(1800.0, $result['used_area_cm2'], 1.0);

        $boxEfficiency = 3600 / (70 * $result['required_length_cm']) * 100;

        $this->assertLessThan($boxEfficiency, $result['efficiency']);
        $this->assertGreaterThan(0, $result['efficiency']);
    }

    public function test_on_fold_pieces_are_placed_against_the_fold(): void
    {
        $pattern = $this->patternWith([
            $this->piece('back', 'پشت یک‌تکه', 34, 60, ['on_fold' => true]),
            $this->piece('front', 'جلو', 30, 55),
        ]);

        $result = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140]);

        $onFold = collect($result['placements'])->firstWhere('code', 'back');

        $this->assertNotNull($onFold);
        $this->assertTrue($onFold['on_fold']);
        $this->assertSame(0.0, $onFold['x']);
        $this->assertSame(0, $onFold['rotation']);
    }

    public function test_mirrored_pieces_are_cut_once_on_folded_fabric(): void
    {
        $pattern = $this->patternWith([
            $this->piece('sleeve', 'آستین', 30, 55, ['cut_quantity' => 2, 'mirror' => true]),
        ]);

        $folded = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'folded' => true]);
        $open = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'folded' => false]);

        $this->assertCount(1, $folded['placements']);
        $this->assertTrue($folded['placements'][0]['mirrored']);
        $this->assertCount(2, $open['placements']);
    }

    public function test_seam_allowance_grows_the_required_length(): void
    {
        $pattern = $this->patternWith([
            $this->piece('front', 'جلو', 40, 50),
        ]);

        $with = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'include_allowance' => true]);
        $without = $this->nesting->nest($pattern, null, ['fabric_width_cm' => 140, 'include_allowance' => false]);

        $this->assertGreaterThan($without['required_length_cm'], $with['required_length_cm']);
        $this->assertSame(50.0, $without['required_length_cm']);
    }

    public function test_alternatives_offer_comparable_layouts(): void
    {
        $pattern = Pattern::factory()->withSimplePieces()->create();

        $alternatives = $this->nesting->alternatives($pattern, null, ['fabric_width_cm' => 140]);

        $this->assertGreaterThanOrEqual(2, count($alternatives));
        $this->assertLessThanOrEqual(3, count($alternatives));

        foreach ($alternatives as $alternative) {
            $this->assertArrayHasKey('label', $alternative);
            $this->assertArrayHasKey('required_meters', $alternative);
            $this->assertArrayHasKey('waste_percent', $alternative);
            $this->assertIsFloat($alternative['required_meters']);
        }

        $this->assertSame('base', $alternatives[0]['key']);
    }

    public function test_estimate_for_widths_covers_common_market_widths(): void
    {
        $pattern = Pattern::factory()->withSimplePieces()->create();

        $rows = $this->nesting->estimateForWidths($pattern);

        $this->assertCount(4, $rows);
        $this->assertSame([90.0, 110.0, 140.0, 150.0], array_column($rows, 'width_cm'));

        $byWidth = collect($rows)->keyBy('width_cm');

        // عرض ۹۰ تاشده تنها ۴۵ سانتی‌متر جای مفید دارد و قطعه ۵۰ سانتی در آن نیم‌عرض
        // جا نمی‌شود؛ در این حالت چیدمان تای پارچه را باز می‌کند و تک‌لا می‌چیند، پس
        // همه قطعه‌ها بریده می‌شوند ولی مصرف پارچه از عرض پهن‌تر کمتر نمی‌شود.
        $this->assertTrue($byWidth[90.0]['fits']);
        $this->assertTrue($byWidth[140.0]['fits']);
        $this->assertGreaterThan(0, $byWidth[140.0]['required_meters']);
        $this->assertGreaterThanOrEqual($byWidth[140.0]['required_meters'], $byWidth[90.0]['required_meters']);
    }

    public function test_a_pattern_without_pieces_warns_instead_of_failing(): void
    {
        $pattern = Pattern::factory()->create();

        $result = $this->nesting->nest($pattern, null);

        $this->assertSame([], $result['placements']);
        $this->assertSame(0.0, $result['required_length_cm']);
        $this->assertSame(0.0, $result['efficiency']);
        $this->assertStringContainsString('قطعه‌ای ندارد', implode(' ', $result['warnings']));
    }

    /**
     * هیچ دو قطعه‌ای — با احتساب جای دوخت — نباید کمتر از فاصله خواسته‌شده به هم برسند.
     *
     * چون کادر هر قطعه جای دوخت را در خود دارد، جدا بودن کادرها به اندازه فاصله
     * تضمین می‌کند که خط برش دو قطعه هم روی هم نمی‌افتد.
     */
    protected function assertSeparated(array $result, float $gap): void
    {
        $placements = $result['placements'];

        foreach ($placements as $index => $a) {
            foreach (array_slice($placements, $index + 1) as $b) {
                $dx = max($a['x'] - ($b['x'] + $b['width']), $b['x'] - ($a['x'] + $a['width']));
                $dy = max($a['y'] - ($b['y'] + $b['height']), $b['y'] - ($a['y'] + $a['height']));

                $this->assertTrue(
                    $dx >= $gap - 0.001 || $dy >= $gap - 0.001,
                    sprintf(
                        'قطعه «%s» و «%s» فاصله %s سانتی‌متری را نگه نداشته‌اند (افقی %.2f، عمودی %.2f).',
                        $a['code'],
                        $b['code'],
                        $gap,
                        $dx,
                        $dy,
                    ),
                );
            }
        }
    }

    /** الگویی با قطعه‌های ریز و درشت، قرینه و روی تا؛ محک واقعی چیدمان. */
    protected function crowdedPattern(): Pattern
    {
        return $this->patternWith([
            $this->piece('front', 'تنه جلو', 34, 66, ['cut_quantity' => 2, 'mirror' => true]),
            $this->piece('back', 'تنه پشت', 32, 58, ['on_fold' => true]),
            $this->piece('sleeve', 'آستین', 38, 52, ['cut_quantity' => 2, 'mirror' => true]),
            $this->piece('yoke', 'یوک', 26, 12, ['cut_quantity' => 2, 'on_fold' => true]),
            $this->piece('collar', 'یقه', 30, 9, ['cut_quantity' => 2, 'on_fold' => true]),
            $this->piece('cuff', 'مچ', 24, 11, ['cut_quantity' => 2]),
            $this->piece('pocket', 'جیب', 14, 16, ['cut_quantity' => 2]),
            $this->piece('placket', 'پاتلت', 6, 62),
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $pieces */
    protected function patternWith(array $pieces): Pattern
    {
        $pattern = Pattern::factory()->create();

        foreach ($pieces as $piece) {
            $pattern->pieces()->create($piece);
        }

        return $pattern->load('pieces');
    }

    /** تنه‌ای با برچسب لبه‌ها و یک نشانه روی درز پهلو؛ برای آزمون تطبیق طرح. */
    protected function bodicePiece(string $code, string $name, string $part, string $side, float $notchY): array
    {
        return $this->piece($code, $name, 34.0, 60.0, [
            'notches' => [['x' => 34.0, 'y' => $notchY, 'edge' => 1, 'label' => 'نشانه کمر', 'pair' => 'side']],
            'meta' => [
                'part' => $part,
                'side' => $side,
                'edges' => ['shoulder', 'side', 'hem', 'default'],
            ],
        ]);
    }

    protected function piece(string $code, string $name, float $width, float $height, array $extra = []): array
    {
        return array_merge([
            'code' => $code,
            'name' => $name,
            'layer' => 'outer',
            'cut_quantity' => 1,
            'outline' => [
                ['x' => 0, 'y' => 0],
                ['x' => $width, 'y' => 0],
                ['x' => $width, 'y' => $height],
                ['x' => 0, 'y' => $height],
            ],
        ], $extra);
    }
}
