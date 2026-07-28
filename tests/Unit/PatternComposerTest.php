<?php

namespace Tests\Unit;

use App\Models\PatternPiece;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\PatternComposer;
use App\Services\Pattern\Style\StyleRegistry;
use App\Services\Pattern\SvgRenderer;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PatternComposerTest extends TestCase
{
    use RefreshDatabase;

    protected function composer(): PatternComposer
    {
        return app(PatternComposer::class);
    }

    protected function measurements(string $size = '40'): array
    {
        return Measurements::fromSize($size);
    }

    /** @return array<int, array<string, mixed>> */
    protected function group(array $result, string $group): array
    {
        return array_values(array_filter(
            $result['pieces'],
            fn (array $piece) => ($piece['meta']['group'] ?? null) === $group,
        ));
    }

    protected function piece(array $result, string $code): array
    {
        foreach ($result['pieces'] as $piece) {
            if ($piece['code'] === $code) {
                return $piece;
            }
        }

        $this->fail("قطعه «{$code}» در ترکیب نیست.");
    }

    /**
     * سبک‌ها با کلید ثابت انتخاب نمی‌شوند؛ اولین سبکِ هر گروه که روی این قطعه‌ها
     * می‌نشیند برداشته می‌شود، تا آزمون با بزرگ‌شدن کاتالوگ نشکند.
     */
    protected function styleFrom(string $group, array $pieces, array $context = []): ?string
    {
        $availability = $this->composer()->styleAvailability($pieces, $context);

        foreach (StyleRegistry::group($group) as $key => $style) {
            if ($availability[$key]['ok'] ?? false) {
                return $key;
            }
        }

        return null;
    }

    /** بررسی سلامت هر قطعه ترکیب: مسیر بسته، برچسب لبه‌ها، راستای پارچه و جای دوخت. */
    protected function assertPiecesAreSewable(array $result, string $message = ''): void
    {
        $codes = array_column($result['pieces'], 'code');

        $this->assertNotEmpty($result['pieces'], $message.': قطعه‌ای ساخته نشد.');
        $this->assertSame($codes, array_unique($codes), $message.': کد قطعه‌ها باید یکتا باشد.');

        foreach ($result['pieces'] as $piece) {
            $where = $message.' / '.$piece['code'];

            $this->assertGreaterThanOrEqual(3, count($piece['outline']), $where.': مسیر قطعه');
            $this->assertCount(count($piece['outline']), $piece['meta']['edges'], $where.': برچسب هر لبه');
            $this->assertNotEmpty($piece['edge_allowances'], $where.': جای دوخت');
            $this->assertGreaterThan(1, (new PatternPiece($piece))->area(), $where.': مساحت');
            $this->assertSame([0.0, 0.0], [
                round(Geometry::bounds($piece['outline'])[0], 2),
                round(Geometry::bounds($piece['outline'])[1], 2),
            ], $where.': مبدأ گوشه بالا-چپ');
        }
    }

    public function test_bodice_sleeve_and_skirt_compose_into_one_coherent_piece_list(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_a_line', 'collar' => 'shirt'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $codes = array_column($result['pieces'], 'code');

        $this->assertSame($codes, array_unique($codes), 'کد قطعه‌ها نباید تکراری باشد.');
        $this->assertSame(['bodice-front', 'bodice-back', 'skirt-front', 'skirt-back', 'sleeve', 'collar'], $codes);

        $this->assertCount(2, $this->group($result, 'bodice'));
        $this->assertCount(2, $this->group($result, 'lower'));
        $this->assertCount(1, $this->group($result, 'sleeve'));
        $this->assertCount(1, $this->group($result, 'collar'));

        $sorts = array_column($result['pieces'], 'sort');
        $sorted = $sorts;
        sort($sorted);
        $this->assertSame($sorted, $sorts);

        foreach ($result['pieces'] as $piece) {
            $this->assertGreaterThanOrEqual(3, count($piece['outline']), $piece['code']);
            $this->assertCount(count($piece['outline']), $piece['meta']['edges'], $piece['code'].': برچسب هر لبه');
            $this->assertNotEmpty($piece['grainline'], $piece['code'].': راستای پارچه');
            $this->assertNotEmpty($piece['edge_allowances'], $piece['code'].': جای دوخت');
            $this->assertSame([0.0, 0.0], [
                round(Geometry::bounds($piece['outline'])[0], 2),
                round(Geometry::bounds($piece['outline'])[1], 2),
            ], $piece['code'].': مبدأ گوشه بالا-چپ');
        }
    }

    public function test_darts_notches_and_markers_survive_the_composition(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_pencil'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $front = $this->piece($result, 'bodice-front');
        $this->assertNotEmpty($front['darts']);
        $this->assertNotEmpty($front['notches']);
        $this->assertNotEmpty($front['markers']);

        foreach ($front['darts'] as $dart) {
            if ($dart['edge'] === null) {
                continue;
            }

            $this->assertArrayHasKey((int) $dart['edge'], $front['meta']['edges'], 'شماره لبه ساسون باید معتبر باشد.');
        }

        $sleeve = $this->piece($result, 'sleeve');
        $this->assertNotEmpty($sleeve['notches']);
        $this->assertNotEmpty($this->piece($result, 'skirt-back')['darts']);
    }

    public function test_the_bodice_is_cropped_at_the_waist_when_a_lower_part_is_attached(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();

        $alone = $composer->compose(['bodice' => 'tshirt'], $measurements);
        $joined = $composer->compose(['bodice' => 'tshirt', 'skirt' => 'skirt_a_line'], $measurements);

        $long = $this->piece($alone, 'tshirt-front');
        $short = $this->piece($joined, 'tshirt-front');

        $this->assertGreaterThan(Geometry::height($short['outline']) + 10, Geometry::height($long['outline']));
        $this->assertContains('hem', $long['meta']['edges'], 'بالاتنه تنها باید لبه پایین داشته باشد.');
        $this->assertContains('waist', $short['meta']['edges'], 'بالاتنه بریده‌شده باید لبه کمر داشته باشد.');
        $this->assertNotContains('hem', $short['meta']['edges']);
        $this->assertTrue($short['meta']['cropped_at_waist']);

        // خط کمر همان جایی است که بلوک اعلام کرده بود
        $waistEdge = $composer->edgeWithTag($short, 'waist');
        $waistY = Geometry::pointOnEdge($short['outline'], $waistEdge, 0.5)['y'];
        $this->assertEqualsWithDelta(Geometry::height($short['outline']), $waistY, 0.6);
    }

    public function test_waist_difference_is_eased_into_gathers_and_the_seams_end_up_equal(): void
    {
        $composer = $this->composer();
        $selection = ['bodice' => 'bodice_block', 'skirt' => 'skirt_a_line'];
        $ease = ['bust' => 6, 'waist' => 4, 'hip' => 6];

        // چقدر باید دامن را گشادتر بگیریم تا اختلاف در حد چین‌خوردن باشد؟
        // (اندازه خودِ بلوک‌ها کار این آزمون نیست، پس از روی همین ترکیب حساب می‌شود.)
        $first = $composer->compose($selection, $this->measurements(), $ease)['metrics']['waist'];
        $extra = round(($first['bodice'] - $first['lower']) + 8, 1);

        $result = $composer->compose(
            $selection,
            $this->measurements(),
            array_merge($ease, ['lower' => ['waist' => $extra]]),
        );

        $waist = $result['metrics']['waist'];

        $this->assertSame('gather', $waist['method']);
        $this->assertGreaterThan(0, $waist['difference'], 'دامن باید گشادتر از بالاتنه باشد.');
        $this->assertLessThanOrEqual(PatternComposer::MAX_GATHER, $waist['difference']);
        $this->assertEqualsWithDelta($waist['difference'], $waist['gathered'], 0.05);

        // بعد از چین‌دادن، دو لبه کمر دقیقاً هم‌اندازه دوخته می‌شوند
        $this->assertEqualsWithDelta(
            $composer->waistGirth($this->group($result, 'bodice')),
            $composer->waistGirth($this->group($result, 'lower')),
            0.05,
        );
        $this->assertEqualsWithDelta($waist['bodice_after'], $waist['lower_after'], 0.05);

        // همه چین ثبت‌شده روی قطعه‌ها، روی هم، همان اختلاف است
        $recorded = 0.0;

        foreach ($this->group($result, 'lower') as $piece) {
            foreach ($piece['meta']['gathers'] ?? [] as $gather) {
                $recorded += $gather['amount'] * (empty($piece['on_fold']) ? max(1, (int) $piece['cut_quantity']) : 2);
            }
        }

        $this->assertNotEmpty($this->piece($result, 'skirt-front')['meta']['gathers']);
        $this->assertEqualsWithDelta($waist['difference'], $recorded, 0.2, 'چین باید بین قطعه‌های کمر پخش شود.');

        $note = collect($result['notes'])->firstWhere('type', 'tip');
        $this->assertStringContainsString('چین', $note['text']);
        $this->assertStringContainsString('سانتی‌متر', $note['text']);
    }

    public function test_waist_difference_is_trued_on_the_side_seams_and_the_seams_end_up_equal(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'tshirt', 'skirt' => 'skirt_pencil'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $waist = $result['metrics']['waist'];

        $this->assertSame('true_side_seams', $waist['method']);
        $this->assertLessThan(0, $waist['difference'], 'تی‌شرت در کمر گشادتر از دامن راسته است.');
        $this->assertGreaterThan(5, $waist['trued']);

        $bodice = $composer->waistGirth($this->group($result, 'bodice'));
        $lower = $composer->waistGirth($this->group($result, 'lower'));

        $this->assertEqualsWithDelta($bodice, $lower, 0.1, 'کمر بالاتنه و دامن باید هم‌اندازه شود.');
        $this->assertEqualsWithDelta($waist['lower'], $lower, 0.1, 'دامن نباید تغییر کند؛ فقط بالاتنه تنگ می‌شود.');

        // هندسه واقعاً عوض شده است: لبه کمر بالاتنه کوتاه‌تر شده
        $front = $this->piece($result, 'tshirt-front');
        $this->assertGreaterThan(0, $front['meta']['trued_waist']);
    }

    public function test_the_sleeve_cap_is_fitted_to_the_composed_armhole(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_a_line'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $sleeve = $result['metrics']['sleeve'];

        $this->assertSame('fitted', $sleeve['status']);
        $this->assertEqualsWithDelta(
            $composer->armholeLength($this->group($result, 'bodice')),
            $sleeve['armhole'],
            0.05,
            'طول حلقه گزارش‌شده باید همان حلقه قطعه‌های نهایی باشد.',
        );
        $this->assertEqualsWithDelta(
            $sleeve['armhole'] + 1.5,
            $composer->capLength($this->piece($result, 'sleeve')),
            PatternComposer::CAP_TOLERANCE,
            'سرآستین باید به اندازه حلقه + آزادی سرآستین باشد.',
        );
        $this->assertGreaterThanOrEqual($sleeve['armhole_before'], $sleeve['armhole']);
    }

    public function test_a_wide_arm_makes_the_armhole_deeper_and_says_so(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_a_line'],
            array_merge($this->measurements(), ['bicep' => 40]),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $sleeve = $result['metrics']['sleeve'];

        // دور بازوی بزرگ سرآستین را بلند می‌کند؛ حلقه گودتر می‌شود تا در آن بنشیند
        $this->assertGreaterThan(0, $sleeve['armhole_drop']);
        $this->assertGreaterThan($sleeve['armhole_before'], $sleeve['armhole']);
        $this->assertLessThanOrEqual(PatternComposer::MAX_ARMHOLE_DROP, $sleeve['armhole_drop']);
        $this->assertEqualsWithDelta(
            $composer->armholeLength($this->group($result, 'bodice')),
            $sleeve['armhole'],
            0.05,
        );
        $this->assertNotEmpty(collect($result['notes'])->filter(
            fn (array $note) => str_contains($note['text'], 'حلقه آستین'),
        ));
    }

    public function test_an_impossible_sleeve_cap_is_reported_instead_of_faked(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve'],
            ['bust' => 130, 'waist' => 110, 'hip' => 130, 'bicep' => 60, 'height' => 170],
            ['bust' => 10],
            ['bodice' => ['armhole_depth_extra' => -2]],
        );

        $sleeve = $result['metrics']['sleeve'];

        $this->assertSame('mismatch', $sleeve['status']);
        $this->assertGreaterThan(PatternComposer::CAP_TOLERANCE, abs($sleeve['difference']));

        $warning = collect($result['notes'])->firstWhere('type', 'warning');
        $this->assertNotNull($warning, 'اختلاف باید به کاربر گزارش شود.');
        $this->assertStringContainsString('سرآستین', $warning['text']);
    }

    public function test_the_collar_is_cut_to_the_composed_neckline(): void
    {
        $composer = $this->composer();

        foreach (['shirt', 'stand', 'flat'] as $style) {
            $result = $composer->compose(
                ['bodice' => 'bodice_block', 'collar' => $style],
                $this->measurements(),
                ['bust' => 6],
            );

            $neckline = $composer->necklineLength($this->group($result, 'bodice'));
            $collar = $this->piece($result, 'collar');
            $neckEdge = $composer->edgeWithTag($collar, 'neck');

            $this->assertNotNull($neckEdge, $style.': یقه باید لبه یقه داشته باشد.');
            $this->assertEqualsWithDelta(
                $neckline,
                Geometry::edgeLength($collar['outline'], $neckEdge),
                0.4,
                $style.': لبه یقه باید هم‌اندازه خط یقه باشد.',
            );
            $this->assertSame('fitted', $result['metrics']['collar']['status']);
        }
    }

    public function test_a_knit_neckband_is_cut_shorter_on_purpose_and_says_so(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'tshirt', 'collar' => 'band'],
            $this->measurements(),
            ['bust' => 2],
        );

        $band = $this->piece($result, 'neckband');
        $neckline = $composer->necklineLength($this->group($result, 'bodice')) * 2;

        $this->assertEqualsWithDelta($neckline * 0.85, Geometry::width($band['outline']), 0.2);
        $this->assertStringContainsString('کوتاه‌تر', collect($result['notes'])->last()['text']);
    }

    public function test_sewing_relations_join_the_waist_the_armhole_and_the_neckline(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_a_line', 'collar' => 'stand'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $labels = array_column($result['sewing_relations'], 'label');

        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'کمر')));
        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'حلقه')));
        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'یقه')));
        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'پهلو')));

        $codes = array_column($result['pieces'], 'code');

        foreach ($result['sewing_relations'] as $relation) {
            $this->assertContains($relation['from']['piece'], $codes);
            $this->assertContains($relation['to']['piece'], $codes);
        }
    }

    public function test_invalid_selections_are_refused_in_persian(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();

        $cases = [
            [[], 'بالاتنه'],
            [['sleeve' => 'sleeve'], 'بالاتنه'],
            [['collar' => 'shirt'], 'یقه بدون بالاتنه'],
            [['bodice' => 'bodice_block', 'skirt' => 'skirt_a_line', 'pants' => 'pants_straight'], 'دامن و شلوار'],
            [['bodice' => 'skirt_a_line'], 'بالاتنه نیست'],
            [['bodice' => 'bodice_block', 'collar' => 'کج'], 'شناخته نشد'],
            [['bodice' => 'bodice_block', 'sleeve' => 'dress'], 'شناخته نشد'],
        ];

        foreach ($cases as [$selection, $expected]) {
            try {
                $composer->compose($selection, $measurements);
                $this->fail('این ترکیب باید رد می‌شد: '.json_encode($selection));
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage());
            }
        }
    }

    public function test_composed_pieces_render_through_the_svg_renderer(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'shirt_classic', 'sleeve' => 'sleeve', 'pants' => 'pants_straight', 'collar' => 'shirt'],
            $this->measurements('44'),
            ['bust' => 10, 'waist' => 8, 'hip' => 8],
        );

        $svg = app(SvgRenderer::class)->renderPieces(PatternComposer::toModels($result['pieces']), ['width' => 600]);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertGreaterThanOrEqual(count($result['pieces']), substr_count($svg, '<path'));

        foreach ($result['pieces'] as $piece) {
            $this->assertStringContainsString($piece['name'], $svg, $piece['code'].': نام قطعه روی نقشه');

            $model = new PatternPiece($piece);
            $this->assertGreaterThan(1, $model->area(), $piece['code'].': مساحت قطعه باید معنادار باشد.');
            $this->assertGreaterThan(1, $model->perimeter(), $piece['code']);
        }
    }

    /* ---------------------------------------------------------------------
     |  دستور: پایه + سبک‌ها
     * ------------------------------------------------------------------- */

    public function test_a_recipe_of_a_base_and_several_styles_still_walks_the_seams_together(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();
        $base = ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'lower' => 'skirt_a_line'];

        $plain = $composer->compose($base, $measurements, ['bust' => 6, 'waist' => 4, 'hip' => 6]);

        $styles = array_values(array_filter([
            $this->styleFrom('neckline', $plain['pieces']),
            $this->styleFrom('hem', $plain['pieces']),
            $this->styleFrom('pocket', $plain['pieces']),
            $this->styleFrom('closure', $plain['pieces']),
        ]));

        $this->assertGreaterThanOrEqual(3, count($styles), 'کاتالوگ باید دست‌کم سه گروه سبک داشته باشد.');

        $result = $composer->compose(
            $base + ['styles' => $styles],
            $measurements,
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $this->assertPiecesAreSewable($result, implode('+', $styles));

        // همه سبک‌ها اجرا شدند و هر کدام گزارش خودش را دارد
        $this->assertCount(count($styles), $result['metrics']['styles']);
        $this->assertSame($styles, array_column($result['metrics']['styles'], 'key'));

        foreach ($result['metrics']['styles'] as $row) {
            $this->assertSame('applied', $row['status'], $row['key'].': باید اجرا می‌شد.');
        }

        // پس از سبک‌ها هم کمر بالاتنه و دامن هم‌اندازه دوخته می‌شوند
        $bodice = array_values(array_filter($result['pieces'], fn ($piece) => ($piece['meta']['group'] ?? null) === 'bodice'));
        $lower = array_values(array_filter($result['pieces'], fn ($piece) => ($piece['meta']['group'] ?? null) === 'lower'));

        $this->assertEqualsWithDelta(
            $composer->waistGirth($bodice),
            $composer->waistGirth($lower),
            0.5,
            'سبک‌ها نباید کمر را ناجور بگذارند.',
        );

        // و سرآستین هنوز در حلقه آستین می‌نشیند
        $this->assertGreaterThan(0, $composer->armholeLength($bodice));
        $this->assertLessThanOrEqual(
            PatternComposer::MAX_GATHER,
            abs($composer->capLength($this->piece($result, 'sleeve')) - $composer->armholeLength($bodice)),
            'اختلاف سرآستین و حلقه باید در حد چین‌خوردن بماند.',
        );

        $this->assertNotEmpty($result['notes']);
        $this->assertNotEmpty($result['sewing_relations']);
    }

    public function test_a_refused_style_is_skipped_with_its_persian_reason_without_touching_the_pieces(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();
        $base = ['bodice' => 'bodice_block', 'sleeve' => 'none', 'lower' => 'skirt_a_line'];

        $plain = $composer->compose($base, $measurements);
        $availability = $composer->styleAvailability($plain['pieces'], ['measurements' => $measurements]);
        $refused = collect($availability)->reject(fn ($row) => $row['ok'])->keys()->first();

        $this->assertNotNull($refused, 'بدون آستین باید دست‌کم یک سبک (مثلاً مچ) رد شود.');

        $reason = $availability[$refused]['reason'];
        $result = $composer->compose($base + ['styles' => [$refused]], $measurements);

        $report = $result['metrics']['styles'][0];
        $this->assertSame('skipped', $report['status']);
        $this->assertSame($reason, $report['reason']);

        $note = collect($result['notes'])->firstWhere('type', 'warning');
        $this->assertNotNull($note, 'رد شدن سبک باید به کاربر گفته شود.');
        $this->assertStringContainsString($reason, $note['text']);
        $this->assertStringContainsString(StyleRegistry::make($refused)->label(), $note['text']);

        // قطعه‌ها دقیقاً همان‌اند که بدون این سبک ساخته می‌شد
        $this->assertSame(array_column($plain['pieces'], 'code'), array_column($result['pieces'], 'code'));
        $this->assertSame(
            json_encode(array_column($plain['pieces'], 'outline')),
            json_encode(array_column($result['pieces'], 'outline')),
            'سبک رد‌شده نباید هیچ قطعه‌ای را دست بزند.',
        );
        $this->assertPiecesAreSewable($result, 'با سبک رد‌شده');
    }

    public function test_styles_run_in_the_sewing_order_whatever_order_they_were_picked(): void
    {
        $composer = $this->composer();
        $picked = [];

        foreach (array_reverse(PatternComposer::STYLE_ORDER) as $group) {
            $key = array_key_first(StyleRegistry::group($group));

            if ($key !== null) {
                $picked[] = $key;
            }
        }

        $this->assertGreaterThan(2, count($picked));

        $ordered = $composer->normalizeStyles($picked);
        $groups = array_column($ordered, 'group');
        $ranks = array_map(fn (string $group) => array_search($group, PatternComposer::STYLE_ORDER, true), $groups);
        $sorted = $ranks;
        sort($sorted);

        $this->assertSame($sorted, $ranks, 'سبک‌ها باید به ترتیب خط یقه ← … ← جزئیات اجرا شوند.');
        $this->assertSame(count($picked), count($ordered));
    }

    public function test_an_unknown_style_is_reported_instead_of_breaking_the_garment(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'styles' => ['سبک‌ناموجود']],
            $this->measurements(),
        );

        $this->assertSame('missing', $result['metrics']['styles'][0]['status']);
        $this->assertStringContainsString('سبک‌ناموجود', collect($result['notes'])->firstWhere('type', 'warning')['text']);
        $this->assertPiecesAreSewable($result, 'با سبک ناشناخته');
    }

    public function test_a_whole_garment_base_is_composed_and_styled_like_any_other(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();

        $plain = $composer->compose(['kind' => 'garment', 'garment' => 'dress'], $measurements);

        $this->assertSame('garment', $plain['recipe']['base']['kind']);
        $this->assertSame('dress', $plain['recipe']['base']['garment']);
        $this->assertPiecesAreSewable($plain, 'پیراهن یک‌تکه');
        $this->assertNotEmpty(array_filter($plain['pieces'], fn ($piece) => ($piece['meta']['group'] ?? null) === 'sleeve'));

        $neckline = $this->styleFrom('neckline', $plain['pieces']);
        $this->assertNotNull($neckline);

        $styled = $composer->compose(
            ['kind' => 'garment', 'garment' => 'dress', 'styles' => [$neckline]],
            $measurements,
        );

        $this->assertSame('applied', $styled['metrics']['styles'][0]['status']);
        $this->assertPiecesAreSewable($styled, 'پیراهن یک‌تکه + '.$neckline);
        $this->assertNotSame(
            $composer->necklineLength($plain['pieces']),
            $composer->necklineLength($styled['pieces']),
            'سبک خط یقه باید خط یقه را عوض کند.',
        );
    }

    public function test_a_neckline_style_makes_the_drafted_collar_be_cut_again(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();
        $base = ['bodice' => 'bodice_block', 'collar' => 'shirt'];

        $plain = $composer->compose($base, $measurements);
        $deep = collect(StyleRegistry::group('neckline'))
            ->keys()
            ->first(fn (string $key) => str_contains($key, 'scoop') || str_contains($key, 'u_deep') || str_contains($key, 'v'));

        $this->assertNotNull($deep);

        $result = $composer->compose($base + ['styles' => [$deep]], $measurements, [], ['collar' => ['collar_height' => 7.5]]);
        $collar = $this->piece($result, 'collar');
        $neckline = $composer->necklineLength(array_values(array_filter(
            $result['pieces'],
            fn ($piece) => ($piece['meta']['group'] ?? null) === 'bodice',
        )));

        $before = $composer->necklineLength(array_values(array_filter(
            $plain['pieces'],
            fn ($piece) => ($piece['meta']['group'] ?? null) === 'bodice',
        )));

        $this->assertGreaterThan($before, $neckline, 'خط یقه باید بازتر شده باشد.');
        $this->assertEqualsWithDelta(
            $neckline,
            Geometry::edgeLength($collar['outline'], $composer->edgeWithTag($collar, 'neck')),
            0.5,
            'یقه باید دوباره به اندازه خط یقه تازه بریده شود.',
        );
        $this->assertNotEmpty(collect($result['notes'])->filter(
            fn (array $note) => str_contains($note['text'], 'دوباره بریده شد'),
        ));
    }

    public function test_the_recipe_survives_a_round_trip_and_rebuilds_the_same_garment(): void
    {
        $this->actingAsWorkshopUser();
        $composer = $this->composer();
        $recipe = [
            'kind' => 'blocks',
            'bodice' => 'bodice_block',
            'sleeve' => 'sleeve',
            'lower' => 'skirt_a_line',
            'collar' => 'none',
            'styles' => [['key' => $this->styleFrom('neckline', $composer->compose(['bodice' => 'bodice_block'], $this->measurements())['pieces']), 'params' => ['depth' => 9]]],
        ];

        $pattern = $composer->composeIntoPattern($recipe, [
            'measurements' => $this->measurements(),
            'base_size' => '40',
        ]);

        $stored = $pattern->params['compose']['recipe'];
        $this->assertSame('blocks', $stored['base']['kind']);
        $this->assertSame('skirt_a_line', $stored['base']['lower']);
        $this->assertSame($recipe['styles'][0]['key'], $stored['styles'][0]['key']);
        $this->assertEqualsWithDelta(9.0, $stored['styles'][0]['params']['depth'], 0.001);

        // دستور ذخیره‌شده دوباره خوانده می‌شود و همان لباس درمی‌آید
        $reopened = $composer->recipeOf($pattern);
        $this->assertEquals($stored, $reopened);

        $again = $composer->compose($reopened, $pattern->measurements);

        $this->assertSame(
            $pattern->pieces->pluck('code')->all(),
            array_column($again['pieces'], 'code'),
        );
        $this->assertEqualsWithDelta(
            $pattern->pieces->sum(fn ($piece) => $piece->area()),
            collect($again['pieces'])->sum(fn (array $piece) => (new PatternPiece($piece))->area()),
            1.0,
        );
    }

    /**
     * آزمون دیده‌بان: هر مدلی که در رجیستری باشد باید در کارگاه ترکیب،
     * با سبک‌های پیش‌فرض، قطعه‌های سالم بدهد.
     */
    public function test_every_generator_in_the_registry_composes_into_valid_pieces(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements('42');
        $ease = ['bust' => 8, 'waist' => 6, 'hip' => 8];

        $probe = $composer->compose(['bodice' => 'bodice_block', 'sleeve' => 'sleeve'], $measurements, $ease);
        $styles = array_values(array_filter([
            $this->styleFrom('neckline', $probe['pieces']),
            $this->styleFrom('hem', $probe['pieces']),
        ]));

        $failures = [];

        foreach (GeneratorRegistry::keys() as $key) {
            $recipe = match (GeneratorRegistry::groupOf($key)) {
                'bodice' => ['bodice' => $key, 'sleeve' => 'sleeve'],
                'sleeve' => ['bodice' => 'bodice_block', 'sleeve' => $key],
                'skirt', 'pants' => ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'lower' => $key],
                default => ['kind' => 'garment', 'garment' => $key],
            };

            try {
                $result = $composer->compose($recipe + ['styles' => $styles], $measurements, $ease);
                $this->assertPiecesAreSewable($result, $key);
            } catch (\Throwable $exception) {
                $failures[] = $key.' → '.$exception->getMessage();
            }
        }

        $this->assertSame([], $failures, "این مدل‌ها در کارگاه ترکیب می‌شکنند:\n".implode("\n", $failures));
    }

    public function test_every_bodice_and_lower_combination_produces_a_joinable_waist(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements('42');

        foreach (PatternComposer::BODICE_BLOCKS as $bodice) {
            foreach (array_merge(PatternComposer::SKIRT_BLOCKS, PatternComposer::PANTS_BLOCKS) as $lower) {
                $result = $composer->compose(
                    ['bodice' => $bodice, 'lower' => $lower],
                    $measurements,
                    ['bust' => 8, 'waist' => 6, 'hip' => 8],
                );

                $message = $bodice.' + '.$lower;

                $this->assertGreaterThanOrEqual(4, count($result['pieces']), $message);
                $this->assertEqualsWithDelta(
                    $composer->waistGirth($this->group($result, 'bodice')),
                    $composer->waistGirth($this->group($result, 'lower')),
                    0.5,
                    $message.': کمرها باید جور شوند.',
                );
            }
        }
    }
}
