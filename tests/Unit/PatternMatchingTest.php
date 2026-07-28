<?php

namespace Tests\Unit;

use App\Models\CuttingLayout;
use App\Models\Fabric;
use App\Models\Pattern;
use App\Services\Cutting\LayoutRenderer;
use App\Services\Cutting\NestingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تطبیق راه‌راه و چهارخانه در درزها.
 *
 * الگوهای این آزمون عمداً ساده‌اند تا بشود عددها را با دست حساب کرد: هر قطعه یک
 * مستطیل با چهار لبه است (بالا، پهلو، دم، مرکز) و نشانه‌ها روی لبه پهلو نشسته‌اند.
 */
class PatternMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected NestingService $nesting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nesting = new NestingService;
    }

    public function test_two_side_seam_notches_land_on_the_same_stripe(): void
    {
        // نشانه جلو روی ۲۵ و نشانه پشت روی ۲۷ است؛ پس پشت باید ۲ سانتی‌متر جابه‌جا
        // شود تا هر دو نشانه روی یک راه از طرح چهارسانتی بیفتند
        $pattern = $this->twoPiecePattern(25.0, 27.0);
        $fabric = Fabric::factory()->striped()->create(['width_cm' => 140]);

        $result = $this->nesting->nest($pattern, $fabric, ['include_allowance' => false, 'gap_cm' => 1]);

        $match = $result['pattern_match'];

        $this->assertTrue($match['active']);
        $this->assertSame(4.0, $match['repeat_cm']);
        $this->assertSame(0.0, $match['repeat_x_cm'], 'راه‌راه فقط در طول پارچه تکرار می‌شود.');
        $this->assertSame(['y'], $match['axes']);
        $this->assertSame(1, $match['total']);
        $this->assertSame(1, $match['matched']);
        $this->assertSame(0, $match['unmatched']);
        $this->assertSame(0.0, $match['seams'][0]['offset_mm']['y']);
        $this->assertSame('notch', $match['seams'][0]['basis']);

        $front = $this->placement($result, 'front');
        $back = $this->placement($result, 'back');

        // جلو ریشه است و روی خط طرح می‌نشیند؛ پشت با فاز ۲ پایین‌تر می‌رود
        $this->assertSame(0.0, $front['y']);
        $this->assertSame(62.0, $back['y']);
        $this->assertSame(2.0, fmod($back['y'], 4.0));
        $this->assertSame(122.0, $result['required_length_cm']);

        // جای نشانه‌ها روی خود پارچه: اختلافشان باید مضرب دقیق تکرار طرح باشد
        $frontNotch = $front['y'] + 25.0;
        $backNotch = $back['y'] + 27.0;

        $this->assertSame(25.0, $frontNotch);
        $this->assertSame(89.0, $backNotch);
        $this->assertSame(0.0, fmod($backNotch - $frontNotch, 4.0));
    }

    public function test_a_plaid_matches_the_seam_in_both_axes(): void
    {
        // لبه پهلوی پشت دو سانتی‌متر تورفته است، پس تطبیق عرضی هم باید قطعه را
        // در عرض پارچه جابه‌جا کند، نه فقط در طول
        $pattern = $this->twoPiecePattern(25.0, 27.0, 38.0);
        $fabric = Fabric::factory()->plaid()->create(['width_cm' => 140]);

        $result = $this->nesting->nest($pattern, $fabric, ['include_allowance' => false, 'gap_cm' => 1]);

        $match = $result['pattern_match'];

        $this->assertSame(8.0, $match['repeat_cm']);
        $this->assertSame(8.0, $match['repeat_x_cm']);
        $this->assertSame(['y', 'x'], $match['axes']);
        $this->assertSame(1, $match['matched']);
        $this->assertSame(0.0, $match['seams'][0]['offset_mm']['y']);
        $this->assertSame(0.0, $match['seams'][0]['offset_mm']['x']);

        $front = $this->placement($result, 'front');
        $back = $this->placement($result, 'back');

        $this->assertSame(0.0, $front['x']);
        $this->assertSame(2.0, $back['x'], 'قطعه پشت باید در عرض هم روی فاز طرح بنشیند.');
        $this->assertSame(0.0, fmod(($front['x'] + 40.0) - ($back['x'] + 38.0), 8.0));
        $this->assertSame(0.0, fmod(($back['y'] + 27.0) - ($front['y'] + 25.0), 8.0));

        $this->assertLessThanOrEqual(70.0, $back['x'] + $back['width']);
    }

    public function test_an_impossible_ring_of_seams_warns_instead_of_lying(): void
    {
        // سه قطعه به هم دوخته می‌شوند و نشانه‌هایشان حلقه‌ای می‌سازد که جمع
        // اختلاف‌هایش مضرب تکرار طرح نیست؛ یک درز از سه درز شدنی نیست
        $pattern = $this->ringPattern();
        $fabric = Fabric::factory()->striped()->create(['width_cm' => 150]);

        $result = $this->nesting->nest($pattern, $fabric, ['include_allowance' => false, 'gap_cm' => 1]);

        $match = $result['pattern_match'];

        $this->assertSame(3, $match['total']);
        $this->assertSame(2, $match['matched']);
        $this->assertSame(1, $match['unmatched']);

        $failed = collect($match['seams'])->firstWhere('matched', false);

        $this->assertNotNull($failed);
        $this->assertSame('notch', $failed['basis']);
        $this->assertSame(10.0, $failed['offset_mm']['y'], 'اختلاف واقعی باید ۱۰ میلی‌متر گزارش شود.');
        $this->assertNotSame('', $failed['note']);

        $warnings = implode(' | ', $result['warnings']);

        $this->assertStringContainsString('جور نشد', $warnings);
        $this->assertStringContainsString($failed['label'], $warnings);
        $this->assertStringContainsString('میلی‌متر', $warnings);
        $this->assertStringContainsString('۲ درز روی طرح جور شد', $warnings);

        // درزهای جورشده هم باید واقعاً جور باشند، نه فقط ادعا
        foreach ($match['seams'] as $seam) {
            if ($seam['matched']) {
                $this->assertLessThanOrEqual(0.5, $seam['offset_mm']['y']);
            }
        }
    }

    public function test_a_mirrored_pair_keeps_the_centre_front_on_the_same_check(): void
    {
        $pattern = $this->mirroredFrontPattern();
        $fabric = Fabric::factory()->plaid()->create(['width_cm' => 140]);

        $result = $this->nesting->nest($pattern, $fabric, [
            'folded' => false,
            'include_allowance' => false,
            'gap_cm' => 1,
        ]);

        $this->assertCount(2, $result['placements']);

        $centres = [];

        foreach ($result['placements'] as $placement) {
            // خط مرکز جلو در مختصات خود قطعه x = ۰ است؛ در نمونه قرینه، سر دیگر کادر
            $centres[] = $placement['mirrored']
                ? $placement['x'] + $placement['width']
                : $placement['x'];
        }

        $this->assertCount(2, $centres);

        foreach ($centres as $centre) {
            $this->assertEqualsWithDelta(
                0.0,
                fmod($centre, 8.0),
                0.001,
                'خط مرکز جلو روی خط طرح ننشسته است؛ دو لنگه لباس قرینه در نمی‌آید.',
            );
        }

        $this->assertSame(0.0, fmod($centres[0] - $centres[1], 8.0));
    }

    public function test_matching_never_shrinks_the_required_length(): void
    {
        $patterns = [
            'دو تکه' => $this->twoPiecePattern(25.0, 27.0),
            'حلقه سه‌تکه' => $this->ringPattern(),
            'جلوی قرینه' => $this->mirroredFrontPattern(),
        ];

        foreach ($patterns as $label => $pattern) {
            foreach ([['striped', 4.0], ['plaid', 8.0]] as [$state, $repeat]) {
                $fabric = Fabric::factory()->{$state}()->create(['width_cm' => 140]);

                $on = $this->nesting->nest($pattern, $fabric, ['match_stripes' => true]);
                $off = $this->nesting->nest($pattern, $fabric, ['match_stripes' => false]);

                $this->assertGreaterThanOrEqual(
                    $off['required_length_cm'],
                    $on['required_length_cm'],
                    sprintf('تطبیق طرح در «%s» مصرف را کم نشان داده است.', $label),
                );

                $this->assertSame($repeat, $on['pattern_match']['repeat_cm']);
                $this->assertGreaterThanOrEqual(0.0, $on['pattern_match']['extra_length_cm']);
                $this->assertEqualsWithDelta(
                    $on['required_length_cm'] - $on['pattern_match']['baseline_length_cm'],
                    $on['pattern_match']['extra_length_cm'],
                    0.11,
                );
                $this->assertFalse($off['pattern_match']['active']);
            }
        }
    }

    public function test_matching_keeps_the_layout_deterministic_and_inside_the_fabric(): void
    {
        $pattern = $this->ringPattern();
        $fabric = Fabric::factory()->plaid()->create(['width_cm' => 150]);

        $first = $this->nesting->nest($pattern, $fabric);
        $second = $this->nesting->nest($pattern, $fabric);
        $third = (new NestingService)->nest($pattern->fresh('pieces'), $fabric->fresh());

        $this->assertSame($first['placements'], $second['placements']);
        $this->assertSame($first['placements'], $third['placements']);
        $this->assertSame($first['pattern_match']['seams'], $third['pattern_match']['seams']);

        foreach ($first['placements'] as $index => $a) {
            $this->assertSame(0, $a['rotation'], 'روی پارچه طرح‌دار هیچ قطعه‌ای نباید بچرخد.');
            $this->assertGreaterThanOrEqual(-0.001, $a['x']);
            $this->assertLessThanOrEqual($first['usable_width_cm'] + 0.001, $a['x'] + $a['width']);
            $this->assertLessThanOrEqual($first['required_length_cm'] + 0.001, $a['y'] + $a['height']);

            foreach (array_slice($first['placements'], $index + 1) as $b) {
                $overlapX = $a['x'] < $b['x'] + $b['width'] - 0.001 && $b['x'] < $a['x'] + $a['width'] - 0.001;
                $overlapY = $a['y'] < $b['y'] + $b['height'] - 0.001 && $b['y'] < $a['y'] + $a['height'] - 0.001;

                $this->assertFalse($overlapX && $overlapY, 'قطعه‌ها روی هم افتاده‌اند.');
            }
        }
    }

    public function test_on_fold_pieces_stay_pinned_to_the_fold_while_matching(): void
    {
        $pattern = $this->twoPiecePattern(25.0, 27.0);
        $pattern->pieces()->where('code', 'back')->update(['on_fold' => true]);
        $pattern = $pattern->fresh('pieces');

        $fabric = Fabric::factory()->plaid()->create(['width_cm' => 140]);

        $result = $this->nesting->nest($pattern, $fabric, ['include_allowance' => false]);

        $back = $this->placement($result, 'back');

        $this->assertTrue($back['on_fold']);
        $this->assertSame(0.0, $back['x']);
        $this->assertSame(0, $back['rotation']);
    }

    public function test_a_fabric_without_a_recorded_repeat_says_so(): void
    {
        $pattern = $this->twoPiecePattern(25.0, 27.0);
        $fabric = Fabric::factory()->create(['width_cm' => 140, 'surface_pattern' => 'stripe', 'pattern_repeat_cm' => 0]);

        $result = $this->nesting->nest($pattern, $fabric);

        $this->assertFalse($result['pattern_match']['active']);
        $this->assertSame(0, $result['pattern_match']['total']);
        $this->assertStringContainsString('تکرار طرح', implode(' ', $result['warnings']));
    }

    public function test_the_plan_draws_the_fabric_grid_behind_the_pieces(): void
    {
        $pattern = $this->twoPiecePattern(25.0, 27.0);
        $fabric = Fabric::factory()->plaid()->create(['width_cm' => 140]);

        $layout = CuttingLayout::factory()->nested($pattern, $fabric)->create([
            'fabric_id' => $fabric->id,
            'pattern_id' => $pattern->id,
            'match_stripes' => true,
        ]);

        $svg = (new LayoutRenderer)->render($layout->fresh());

        $this->assertStringContainsString('شبکه چهارخانه', $svg);
        $this->assertStringContainsString('تکرار ۸ سانتی‌متر', $svg);
        $this->assertStringContainsString('x1="8" y1="0"', $svg);
        $this->assertStringContainsString('y1="8" x2="70"', $svg);

        // پارچه ساده شبکه نمی‌گیرد
        $plain = CuttingLayout::factory()->nested($pattern, null)->create(['fabric_id' => null]);

        $this->assertStringNotContainsString('شبکه چهارخانه', (new LayoutRenderer)->render($plain->fresh()));
    }

    /** @return array<string, mixed> */
    protected function placement(array $result, string $code): array
    {
        $placement = collect($result['placements'])->firstWhere('code', $code);

        $this->assertNotNull($placement, sprintf('قطعه «%s» در چیدمان نیست.', $code));

        return $placement;
    }

    /**
     * دو قطعه که از لبه پهلو به هم دوخته می‌شوند، هر کدام با یک نشانه روی آن لبه.
     */
    protected function twoPiecePattern(float $frontNotchY, float $backNotchY, float $backWidth = 40.0): Pattern
    {
        $pattern = Pattern::factory()->create(['seam_allowances' => ['default' => 1.0]]);

        $pattern->pieces()->create($this->rectPiece('front', 'تنه جلو', 40.0, 60.0, [
            'notches' => [['x' => 40.0, 'y' => $frontNotchY, 'edge' => 1, 'label' => 'نشانه کمر', 'pair' => 'side']],
        ]));

        $pattern->pieces()->create($this->rectPiece('back', 'تنه پشت', $backWidth, 60.0, [
            'notches' => [['x' => $backWidth, 'y' => $backNotchY, 'edge' => 1, 'label' => 'نشانه کمر', 'pair' => 'side']],
        ]));

        $pattern->update(['sewing_relations' => [[
            'from' => ['piece' => 'front', 'edge' => 1],
            'to' => ['piece' => 'back', 'edge' => 1],
            'label' => 'درز پهلو',
        ]]]);

        return $pattern->load('pieces');
    }

    /**
     * سه قطعه در یک حلقه: الف→ب، ب→ج، ج→الف. جمع اختلاف نشانه‌ها ۳ سانتی‌متر است
     * و مضرب تکرار ۴ سانتی‌متری نمی‌شود، پس یکی از سه درز شدنی نیست.
     */
    protected function ringPattern(): Pattern
    {
        $pattern = Pattern::factory()->create(['seam_allowances' => ['default' => 1.0]]);

        $notches = ['a' => [20.0, 21.0], 'b' => [21.0, 22.0], 'c' => [22.0, 23.0]];

        foreach (['a' => 'قطعه الف', 'b' => 'قطعه ب', 'c' => 'قطعه ج'] as $code => $name) {
            $pattern->pieces()->create($this->rectPiece($code, $name, 30.0, 50.0, [
                'notches' => [
                    ['x' => 30.0, 'y' => $notches[$code][0], 'edge' => 1, 'label' => 'نشانه راست', 'pair' => 'side'],
                    ['x' => 0.0, 'y' => $notches[$code][1], 'edge' => 3, 'label' => 'نشانه چپ', 'pair' => 'side'],
                ],
            ]));
        }

        $pattern->update(['sewing_relations' => [
            ['from_piece' => 'a', 'from_edge' => 1, 'to_piece' => 'b', 'to_edge' => 3, 'label' => 'درز پهلو الف‌ب'],
            ['from_piece' => 'b', 'from_edge' => 1, 'to_piece' => 'c', 'to_edge' => 3, 'label' => 'درز پهلو ب‌ج'],
            ['from_piece' => 'c', 'from_edge' => 1, 'to_piece' => 'a', 'to_edge' => 3, 'label' => 'درز پهلو ج‌الف'],
        ]]);

        return $pattern->load('pieces');
    }

    /** یک تنه جلوی قرینه با خط مرکز جلو، که دو بار بریده می‌شود. */
    protected function mirroredFrontPattern(): Pattern
    {
        $pattern = Pattern::factory()->create(['seam_allowances' => ['default' => 1.0]]);

        $pattern->pieces()->create($this->rectPiece('front', 'تنه جلو', 37.0, 60.0, [
            'cut_quantity' => 2,
            'mirror' => true,
            'markers' => [[
                'key' => 'cf',
                'label' => 'خط مرکز جلو',
                'from' => ['x' => 0.0, 'y' => 0.0],
                'to' => ['x' => 0.0, 'y' => 60.0],
            ]],
        ]));

        return $pattern->load('pieces');
    }

    /** @return array<string, mixed> */
    protected function rectPiece(string $code, string $name, float $width, float $height, array $extra = []): array
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
            'meta' => ['edges' => ['shoulder', 'side', 'hem', 'default']],
        ], $extra);
    }
}
