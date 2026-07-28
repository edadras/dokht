<?php

namespace Tests\Unit\Style;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleModifier;
use App\Services\Pattern\Style\StyleRegistry;
use App\Services\Pattern\Transform\FullnessRecorder;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * آزمون سبک‌های «چین و گشادی».
 *
 * هر سبک باید سه چیز را نشان بدهد: قطعه را واقعاً عوض کرده باشد، اندازه تمام‌شده
 * را نلغزانده باشد، و جایی که معنا ندارد با یک پیام فارسی رد کند.
 */
class FullnessTest extends TestCase
{
    /** کلید همه سبک‌های این گروه. */
    protected const KEYS = [
        'fullness_waist_gathers',
        'fullness_knife_pleats',
        'fullness_box_pleats',
        'fullness_accordion_pleats',
        'fullness_godet',
        'fullness_tiers',
        'fullness_flare',
        'fullness_taper',
        'fullness_high_low',
        'fullness_cuff',
    ];

    protected function style(string $key): StyleModifier
    {
        return StyleRegistry::make($key);
    }

    /**
     * قطعه‌های یک لباس پایین‌تنه برای اجرای سبک روی آن.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function garment(string $generator = 'skirt_straight', string $size = '40', array $params = []): array
    {
        $gen = GeneratorRegistry::make($generator);

        return $gen->generate(
            Measurements::fromSize($size),
            [],
            array_merge($gen->defaultParams(), $params),
        );
    }

    /** بستر اجرای سبک. */
    protected function context(string $key, array $params = [], string $size = '40'): array
    {
        return [
            'measurements' => Measurements::fromSize($size),
            'ease' => [],
            'params' => array_merge($this->style($key)->defaultParams(), $params),
        ];
    }

    /**
     * اجرای یک سبک روی یک لباس، با اطمینان از اینکه پذیرفته شده است.
     *
     * @return array{pieces: array<int, array<string, mixed>>, notes: array, meta: array}
     */
    protected function applyStyle(string $key, array $pieces, array $params = [], string $size = '40'): array
    {
        $style = $this->style($key);
        $context = $this->context($key, $params, $size);

        $this->assertTrue($style->supports($pieces, $context), "سبک {$key} این لباس را نپذیرفت.");

        return $style->apply($pieces, $context);
    }

    /** پنل‌های پایین‌تنه یک لباس. */
    protected function panels(array $pieces): array
    {
        return array_values(array_filter($pieces, fn ($piece) => in_array(
            $piece['meta']['part'] ?? '',
            ['skirt_front', 'skirt_back', 'skirt_panel', 'skirt_tier', 'front_leg', 'back_leg'],
            true,
        )));
    }

    protected function repeats(array $piece): int
    {
        return ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
    }

    /** دور تمام‌شده یک برچسب لبه روی پنل‌های پایین‌تنه. */
    protected function girth(array $pieces, string $tag): float
    {
        $total = 0.0;

        foreach ($this->panels($pieces) as $piece) {
            foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
                $length = Geometry::edgeLength($piece['outline'], $edge);
                $total += $this->repeats($piece) * max(0.0, $length - FullnessRecorder::consumedOn($piece, $edge));
            }
        }

        return round($total, 2);
    }

    /** دور خام (پارچه) یک برچسب لبه روی پنل‌های پایین‌تنه. */
    protected function fabricGirth(array $pieces, string $tag): float
    {
        $total = 0.0;

        foreach ($this->panels($pieces) as $piece) {
            foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
                $total += $this->repeats($piece) * Geometry::edgeLength($piece['outline'], $edge);
            }
        }

        return round($total, 2);
    }

    /** فاصله سرِ مرکزیِ دم از بالای قطعه. */
    protected function centreHemY(array $piece): float
    {
        $outline = array_values($piece['outline']);
        $best = null;

        foreach (Geometry::edgesWithTag($piece, 'hem') as $edge) {
            foreach ([$edge, ($edge + 1) % count($outline)] as $index) {
                if ($best === null || (float) $outline[$index]['x'] < (float) $outline[$best]['x']) {
                    $best = $index;
                }
            }
        }

        return $best === null ? 0.0 : (float) $outline[$best]['y'];
    }

    /* ---------------------------------------------------------------------
     |  کاتالوگ
     * ------------------------------------------------------------------- */

    public function test_every_style_is_discovered_in_the_fullness_group(): void
    {
        StyleRegistry::flush();
        $group = StyleRegistry::group('fullness');

        foreach (static::KEYS as $key) {
            $this->assertArrayHasKey($key, $group, "سبک «{$key}» در گروه چین و گشادی پیدا نشد.");
            $this->assertSame('fullness', $group[$key]::group());
            $this->assertNotSame('', $group[$key]->label());
            $this->assertNotSame('', $group[$key]->description());
        }

        $labels = array_map(fn (StyleModifier $style) => $style->label(), $group);
        $this->assertSame(count($labels), count(array_unique($labels)), 'دو سبک این گروه نام یکسان دارند.');
    }

    public function test_every_style_declares_a_usable_params_schema(): void
    {
        foreach (static::KEYS as $key) {
            $style = $this->style($key);
            $schema = $style->paramsSchema();
            $defaults = $style->defaultParams();

            $this->assertNotEmpty($schema, "سبک {$key} پارامتری ندارد.");

            foreach ($schema as $name => $field) {
                $this->assertArrayHasKey('label', $field, "پارامتر {$name} سبک {$key} برچسب ندارد.");
                $this->assertArrayHasKey('default', $field);
                $this->assertArrayHasKey($name, $defaults);

                if (isset($field['min'], $field['max'])) {
                    $this->assertGreaterThanOrEqual($field['min'], $field['default']);
                    $this->assertLessThanOrEqual($field['max'], $field['default']);
                }
            }
        }
    }

    public function test_every_style_changes_the_piece_and_reports_its_fabric(): void
    {
        foreach (['skirt_straight', 'pants_tapered'] as $host) {
            $base = $this->garment($host);

            foreach (static::KEYS as $key) {
                $result = $this->applyStyle($key, $base, [], '40');

                $this->assertArrayHasKey('added_fabric', $result['meta'], "سبک {$key} مصرف پارچه را گزارش نکرد.");
                $this->assertNotEmpty($result['notes'], "سبک {$key} هیچ یادداشتی نداد.");
                $this->assertNotEquals(
                    $base,
                    array_slice($result['pieces'], 0, count($base)),
                    "سبک {$key} روی {$host} هیچ چیزی را عوض نکرد.",
                );
                $this->assertGreaterThanOrEqual(count($base), count($result['pieces']));

                foreach ($result['pieces'] as $piece) {
                    $this->assertSame(
                        [],
                        Geometry::validatePiece($piece),
                        "قطعه «{$piece['code']}» بعد از سبک {$key} روی {$host} سالم نیست.",
                    );
                    $this->assertCount(count($piece['outline']), $piece['meta']['edges']);
                }
            }
        }
    }

    public function test_no_fullness_style_accepts_a_bodice(): void
    {
        $bodice = $this->garment('bodice_block');

        foreach (static::KEYS as $key) {
            $reason = $this->style($key)->supports($bodice, $this->context($key));

            $this->assertIsString($reason, "سبک {$key} روی بالاتنه پذیرفته شد، در حالی که باید رد کند.");
            $this->assertNotSame('', $reason, "سبک {$key} بدون دلیل رد کرد.");
        }
    }

    /* ---------------------------------------------------------------------
     |  چین کمر
     * ------------------------------------------------------------------- */

    public function test_waist_gathers_add_fabric_without_moving_the_finished_waist(): void
    {
        $base = $this->garment('skirt_straight');
        $before = $this->girth($base, 'waist');

        $result = $this->applyStyle('fullness_waist_gathers', $base, ['fullness' => 1.6, 'slashes' => 3]);

        $this->assertEqualsWithDelta($before, $this->girth($result['pieces'], 'waist'), 0.3);
        $this->assertEqualsWithDelta(
            $before * 1.6,
            $this->fabricGirth($result['pieces'], 'waist'),
            0.6,
            'پارچه خط کمر به نسبت پُری خواسته‌شده نرسید.',
        );

        // ساسون‌ها باز شدند و پارچه‌شان به چین رفت
        foreach ($this->panels($result['pieces']) as $piece) {
            $this->assertSame([], $piece['darts'], 'ساسون کمر باید به چین تبدیل شده باشد.');
            $this->assertNotEmpty($piece['meta']['gathers'] ?? [], 'چین کمر در meta ثبت نشده.');
        }

        $this->assertGreaterThan(0, $result['meta']['added_fabric']);
        $this->assertEqualsWithDelta($before, $result['meta']['waist_after'], 0.3);
    }

    public function test_a_bigger_gather_ratio_means_more_fabric(): void
    {
        $base = $this->garment('skirt_straight');

        $small = $this->applyStyle('fullness_waist_gathers', $base, ['fullness' => 1.2]);
        $large = $this->applyStyle('fullness_waist_gathers', $base, ['fullness' => 2.2]);

        $this->assertGreaterThan(
            $this->fabricGirth($small['pieces'], 'waist') + 10,
            $this->fabricGirth($large['pieces'], 'waist'),
        );
        $this->assertGreaterThan($small['meta']['added_fabric'], $large['meta']['added_fabric']);
    }

    /* ---------------------------------------------------------------------
     |  پیلی
     * ------------------------------------------------------------------- */

    public function test_pleats_widen_the_fabric_by_exactly_their_allowance(): void
    {
        $cases = [
            'fullness_knife_pleats' => 2,   // جای هر پیلی = ۲ × ژرفای تا
            'fullness_accordion_pleats' => 2,
            'fullness_box_pleats' => 4,     // جعبه‌ای دو تا دارد
        ];

        foreach ($cases as $key => $factor) {
            $base = $this->garment('skirt_straight');
            $before = $this->girth($base, 'waist');
            $fabricBefore = $this->fabricGirth($base, 'waist');

            $result = $this->applyStyle($key, $base, ['count' => 3, 'depth' => 4]);
            $panels = count($this->panels($base));
            $repeats = array_sum(array_map(fn ($piece) => $this->repeats($piece), $this->panels($base)));

            $this->assertSame($factor * 4.0, (float) $result['meta']['pleat_allowance'], "جای هر {$key} درست حساب نشده.");
            $this->assertSame(3, $result['meta']['pleat_count']);
            $this->assertGreaterThan(0, $panels);

            // پارچه دقیقاً به اندازه «تعداد پیلی × جای هر پیلی × تعداد تکرار پنل» بیشتر شد
            $this->assertEqualsWithDelta(
                $fabricBefore + ($repeats * 3 * $factor * 4.0),
                $this->fabricGirth($result['pieces'], 'waist'),
                0.5,
                "پارچه خط کمر بعد از {$key} با حساب پیلی نمی‌خواند.",
            );

            // ولی اندازه تمام‌شده کمر تکان نخورد
            $this->assertEqualsWithDelta($before, $this->girth($result['pieces'], 'waist'), 0.3);

            foreach ($this->panels($result['pieces']) as $piece) {
                $this->assertCount(3, $piece['pleats'], "{$key}: سه پیلی روی قطعه رسم نشد.");
                $this->assertNotEmpty($piece['meta']['pleats'] ?? [], "{$key}: پیلی در meta ثبت نشد.");

                foreach ($piece['meta']['pleats'] as $entry) {
                    $this->assertSame(3, $entry['count']);
                    $this->assertSame(4.0, (float) $entry['depth']);
                }
            }
        }
    }

    public function test_deeper_pleats_cost_more_fabric(): void
    {
        $base = $this->garment('skirt_straight');

        $shallow = $this->applyStyle('fullness_knife_pleats', $base, ['count' => 4, 'depth' => 2]);
        $deep = $this->applyStyle('fullness_knife_pleats', $base, ['count' => 4, 'depth' => 6]);

        $this->assertEqualsWithDelta($shallow['meta']['added_fabric'] * 3, $deep['meta']['added_fabric'], 0.5);
    }

    /* ---------------------------------------------------------------------
     |  گودت
     * ------------------------------------------------------------------- */

    public function test_a_godet_adds_its_own_width_to_the_hem_and_nothing_to_the_waist(): void
    {
        $base = $this->garment('skirt_straight');
        $waist = $this->girth($base, 'waist');
        $hem = $this->fabricGirth($base, 'hem');

        $result = $this->applyStyle('fullness_godet', $base, ['count' => 4, 'height' => 30, 'width' => 18]);

        $this->assertSame(72.0, (float) $result['meta']['added_fabric'], 'چهار گودت ۱۸ سانتی باید ۷۲ سانت به دم اضافه کند.');
        $this->assertEqualsWithDelta($hem + 72, $result['meta']['hem_after'], 0.1);
        $this->assertEqualsWithDelta($waist, $this->girth($result['pieces'], 'waist'), 0.1, 'گودت نباید خط کمر را دست بزند.');

        $godet = null;

        foreach ($result['pieces'] as $piece) {
            if (($piece['meta']['part'] ?? '') === 'godet') {
                $godet = $piece;
            }
        }

        $this->assertNotNull($godet, 'قطعه گودت ساخته نشد.');
        $this->assertSame(4, $godet['cut_quantity']);

        // دو لبه راست گودت دقیقاً به بلندی چاک درمی‌آیند و کمان دمش به پهنای خواسته‌شده
        $sides = $godet['meta']['side_edges'];
        $this->assertEqualsWithDelta(30.0, Geometry::edgeLength($godet['outline'], $sides[0]), 0.1);
        $this->assertEqualsWithDelta(30.0, Geometry::edgeLength($godet['outline'], $sides[1]), 0.1);

        $arc = 0.0;

        foreach ($godet['meta']['hem_edges'] as $edge) {
            $arc += Geometry::edgeLength($godet['outline'], $edge);
        }

        $this->assertEqualsWithDelta(18.0, $arc, 0.1, 'کمان دم گودت به پهنای خواسته‌شده درنیامد.');

        // سر چاک روی پنل‌ها علامت خورده است
        foreach ($this->panels($result['pieces']) as $piece) {
            $this->assertContains('godet', array_column($piece['notches'], 'pair'));
            $this->assertSame(30.0, (float) $piece['meta']['godet_slit']);
        }
    }

    public function test_a_godet_taller_than_the_side_seam_is_refused(): void
    {
        $base = $this->garment('skirt_straight', '40', ['length' => 45]);
        $style = $this->style('fullness_godet');

        $reason = $style->supports($base, $this->context('fullness_godet', ['height' => 60]));

        $this->assertIsString($reason);
        $this->assertStringContainsString('گودت', $reason);
    }

    /* ---------------------------------------------------------------------
     |  طبقه‌ای
     * ------------------------------------------------------------------- */

    public function test_tiering_cuts_the_panel_and_adds_gathered_tiers(): void
    {
        $base = $this->garment('skirt_straight');
        $waist = $this->girth($base, 'waist');
        $panels = count($this->panels($base));

        $result = $this->applyStyle('fullness_tiers', $base, ['tiers' => 2, 'start' => 0.4, 'ratio' => 1.6]);

        $tiers = array_values(array_filter(
            $result['pieces'],
            fn ($piece) => ($piece['meta']['part'] ?? '') === 'skirt_tier',
        ));

        $this->assertCount($panels * 2, $tiers, 'برای هر پنل باید دو طبقه ساخته شود.');
        $this->assertEqualsWithDelta($waist, $this->girth($result['pieces'], 'waist'), 0.3, 'طبقه‌بندی نباید کمر را عوض کند.');

        foreach ($tiers as $tier) {
            $this->assertNotEmpty($tier['meta']['gathers'] ?? [], 'چین درز طبقه ثبت نشده.');
            $this->assertGreaterThan(0, FullnessRecorder::amountOn($tier, 0, 'gathers'));
        }

        // هر طبقه ۱٫۶ برابر طبقه بالای خودش است
        $first = $tiers[0];
        $second = $tiers[1];
        $this->assertEqualsWithDelta(
            (float) $first['meta']['finished_width'] * 1.6,
            (float) $second['meta']['finished_width'],
            0.1,
        );

        // و لبه دم فقط روی آخرین طبقه است
        $this->assertTrue($second['meta']['tier_last']);
        $this->assertFalse($first['meta']['tier_last']);
        $this->assertSame([], Geometry::edgesWithTag($first, 'hem'));

        $this->assertGreaterThan($result['meta']['hem_before'], $result['meta']['hem_after']);
    }

    public function test_a_panel_too_short_to_tier_is_refused(): void
    {
        $base = $this->garment('skirt_straight', '40', ['length' => 25]);

        $reason = $this->style('fullness_tiers')->supports($base, $this->context('fullness_tiers'));

        $this->assertIsString($reason);
        $this->assertStringContainsString('طبقه', $reason);
    }

    /* ---------------------------------------------------------------------
     |  کلوش و باریک کردن
     * ------------------------------------------------------------------- */

    public function test_flare_opens_the_hem_and_leaves_the_waist_alone(): void
    {
        $base = $this->garment('skirt_straight');
        $waist = $this->girth($base, 'waist');
        $hem = $this->fabricGirth($base, 'hem');

        $result = $this->applyStyle('fullness_flare', $base, ['sweep' => 60, 'slashes' => 3]);

        $this->assertEqualsWithDelta($hem + 60, $this->fabricGirth($result['pieces'], 'hem'), 0.5, 'دور دم به اندازه خواسته‌شده باز نشد.');
        $this->assertEqualsWithDelta($waist, $this->girth($result['pieces'], 'waist'), 0.3, 'کلوش نباید خط کمر را پهن کند.');
        $this->assertEqualsWithDelta(60.0, $result['meta']['added_fabric'], 0.5);

        // درز پهلو با کلوش بلندتر می‌شود، ولی روی همه پنل‌ها یک‌اندازه
        $lengths = [];

        foreach ($this->panels($result['pieces']) as $piece) {
            $lengths[] = round(Geometry::edgesLength($piece['outline'], $piece['meta']['side_edges'] ?? []), 2);
        }

        $this->assertEqualsWithDelta($lengths[0], $lengths[1], 0.2, 'درز پهلوی جلو و پشت بعد از کلوش هم‌اندازه نماند.');
    }

    public function test_taper_narrows_the_hem_and_saves_fabric(): void
    {
        $base = $this->garment('skirt_straight');
        $hem = $this->fabricGirth($base, 'hem');
        $waist = $this->girth($base, 'waist');

        $result = $this->applyStyle('fullness_taper', $base, ['take_in' => 4]);
        $panels = array_sum(array_map(fn ($piece) => $this->repeats($piece), $this->panels($base)));

        $this->assertEqualsWithDelta($hem - ($panels * 4), $this->fabricGirth($result['pieces'], 'hem'), 0.6);
        $this->assertLessThan(0, $result['meta']['added_fabric'], 'باریک کردن باید پارچه کمتری بخواهد.');
        $this->assertEqualsWithDelta($waist, $this->girth($result['pieces'], 'waist'), 0.1, 'باریک کردن نباید کمر را دست بزند.');
    }

    public function test_taper_wider_than_the_hem_is_refused(): void
    {
        $base = $this->garment('skirt_straight');

        $reason = $this->style('fullness_taper')->supports($base, $this->context('fullness_taper', ['take_in' => 60]));

        $this->assertIsString($reason);
        $this->assertStringContainsString('باریک', $reason);
    }

    /* ---------------------------------------------------------------------
     |  های‌لو و برگردان
     * ------------------------------------------------------------------- */

    public function test_high_low_moves_the_centres_but_not_the_side_seam(): void
    {
        $base = $this->garment('skirt_straight');
        $sides = [];

        foreach ($this->panels($base) as $piece) {
            $sides[$piece['meta']['side']] = round(Geometry::edgesLength($piece['outline'], $piece['meta']['side_edges']), 2);
        }

        $centresBefore = [];

        foreach ($this->panels($base) as $piece) {
            $centresBefore[$piece['meta']['side']] = $this->centreHemY($piece);
        }

        $result = $this->applyStyle('fullness_high_low', $base, ['front_rise' => 10, 'back_drop' => 16, 'curve' => 0]);

        $centres = [];
        $after = [];

        foreach ($this->panels($result['pieces']) as $piece) {
            $centres[$piece['meta']['side']] = $this->centreHemY($piece);
            $after[$piece['meta']['side']] = round(Geometry::edgesLength($piece['outline'], $piece['meta']['side_edges']), 2);
        }

        $this->assertEqualsWithDelta($sides['front'], $after['front'], 0.05, 'درز پهلوی جلو نباید عوض شود.');
        $this->assertEqualsWithDelta($sides['back'], $after['back'], 0.05, 'درز پهلوی پشت نباید عوض شود.');

        // مرکز جلو ۱۰ سانت بالا آمد و مرکز پشت ۱۶ سانت پایین رفت
        $this->assertEqualsWithDelta($centresBefore['front'] - 10, $centres['front'], 0.2);
        $this->assertEqualsWithDelta($centresBefore['back'] + 16, $centres['back'], 0.2);
        $this->assertGreaterThan(0, $result['meta']['added_fabric']);
    }

    public function test_high_low_needs_both_a_front_and_a_back(): void
    {
        $base = $this->garment('skirt_straight');
        $onlyFront = array_values(array_filter(
            $base,
            fn ($piece) => ($piece['meta']['side'] ?? '') !== 'back',
        ));

        $reason = $this->style('fullness_high_low')->supports($onlyFront, $this->context('fullness_high_low'));

        $this->assertIsString($reason);
        $this->assertStringContainsString('پشت', $reason);
    }

    public function test_a_cuff_makes_every_panel_longer_by_twice_its_depth(): void
    {
        foreach (['skirt_straight', 'pants_tapered'] as $host) {
            $base = $this->garment($host);
            $heights = [];

            foreach ($this->panels($base) as $piece) {
                $heights[$piece['code']] = Geometry::height($piece['outline']);
            }

            $result = $this->applyStyle('fullness_cuff', $base, ['depth' => 5, 'turn_under' => 1]);

            $this->assertSame(11.0, (float) $result['meta']['extension'], 'بلندی اضافه = ۲×۵ + ۱');

            foreach ($this->panels($result['pieces']) as $piece) {
                $this->assertEqualsWithDelta(
                    $heights[$piece['code']] + 11,
                    Geometry::height($piece['outline']),
                    0.1,
                    "{$host}: قطعه «{$piece['code']}» به اندازه برگردان بلندتر نشد.",
                );

                $this->assertSame(5.0, (float) $piece['meta']['hem_turnup']);
                $this->assertSame(0.0, (float) $piece['meta']['hem_allowance']);
                $this->assertSame(0.0, (float) $piece['meta']['allowance_overrides']['hem']);

                $folds = array_filter($piece['markers'], fn ($marker) => ($marker['key'] ?? '') === 'fold');
                $this->assertCount(2, $folds, 'برگردان باید دو خط تا داشته باشد.');
            }

            $this->assertGreaterThan(0, $result['meta']['added_fabric']);
        }
    }

    public function test_a_cuff_on_a_hem_narrower_than_itself_is_refused(): void
    {
        $base = $this->garment('leggings');

        $reason = $this->style('fullness_cuff')->supports($base, $this->context('fullness_cuff', ['depth' => 12]));

        $this->assertIsString($reason);
        $this->assertStringContainsString('برگردان', $reason);
    }

    /* ---------------------------------------------------------------------
     |  ترکیب
     * ------------------------------------------------------------------- */

    public function test_styles_stack_on_top_of_each_other(): void
    {
        $pieces = $this->garment('skirt_straight');
        $waist = $this->girth($pieces, 'waist');

        foreach (['fullness_flare', 'fullness_waist_gathers', 'fullness_cuff', 'fullness_taper'] as $key) {
            $result = $this->applyStyle($key, $pieces);
            $pieces = $result['pieces'];
        }

        $this->assertEqualsWithDelta($waist, $this->girth($pieces, 'waist'), 0.4, 'بعد از سه سبک پشت سر هم، کمر لغزید.');

        foreach ($pieces as $piece) {
            $this->assertSame([], Geometry::validatePiece($piece), "قطعه «{$piece['code']}» بعد از سه سبک سالم نیست.");
        }
    }

    public function test_styles_are_deterministic(): void
    {
        $base = $this->garment('pants_tapered');

        foreach (static::KEYS as $key) {
            $first = $this->applyStyle($key, $base);
            $second = $this->applyStyle($key, $base);

            $this->assertSame($first['pieces'], $second['pieces'], "سبک {$key} دو بار اجرا شد و دو خروجی داد.");
        }
    }
}
