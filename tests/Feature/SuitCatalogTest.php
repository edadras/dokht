<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * کاتالوگ کت‌وشلوار رسمی، و شلوارهای تازهٔ گروه پایین‌تنه.
 *
 * کت جدی‌ترین درفت این کاتالوگ است و جای خطایش سه تاست:
 *
 *   ۱. آستین دوتکه. سرآستین از دو تکه ساخته می‌شود و اگر هرکدام جداگانه درفت
 *      شود، جمعشان هیچ‌وقت به حلقه نمی‌رسد. پس جمع دو لبهٔ حلقهٔ آستین با حلقهٔ
 *      اندازه‌گرفته‌شده سنجیده می‌شود، نه با عددی اعلامی.
 *   ۲. درز پنلی. دو لبه‌ای که به هم دوخته می‌شوند از یک خط ساخته شده‌اند و باید
 *      هم‌اندازه بمانند، وگرنه کت روی سینه می‌پیچد.
 *   ۳. یقه‌پذیری. یقه در لایهٔ سبک‌ها می‌نشیند، پس لبهٔ neck تنه باید سالم و
 *      اندازه‌گرفتنی بماند؛ کتی که خط یقه‌اش گم شده باشد هیچ یقه‌ای نمی‌پذیرد.
 *
 * شلوارها (کت‌وشلوار، چینو، پالازو و جودپور) هم همان دو سنجهٔ همیشگی را دارند:
 * درز داخل پا و درز پهلوی جلو و پشت باید جور باشند و دور کمر تمام‌شده باید دقیقاً
 * برابر دور کمر هدف دربیاید.
 */
class SuitCatalogTest extends TestCase
{
    /** برچسب‌های مجاز لبه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'strap', 'default'];

    /** رواداری هم‌اندازه بودن دو درزی که به هم دوخته می‌شوند (سانتی‌متر). */
    protected const SEAM_MATCH = 0.3;

    /** پنج بدن آزمون؛ کلیدهای غیرجدولی صریح نوشته شده‌اند. */
    protected const BODIES = [
        '34' => null,
        '40' => null,
        '48' => null,
        'سینه‌درشت' => ['height' => 168, 'bust' => 118, 'waist' => 70, 'hip' => 100, 'shoulder_width' => 34, 'arm_length' => 59],
        'کوتاه و درشت' => ['height' => 148, 'bust' => 124, 'waist' => 118, 'hip' => 126, 'shoulder_width' => 40, 'arm_length' => 50],
    ];

    /** کلیدهای گروه کت‌وشلوار. */
    protected const SUIT = ['suit_jacket', 'suit_tuxedo', 'suit_waistcoat', 'suit_trousers'];

    /** شلوارهای تازهٔ گروه پایین‌تنه. */
    protected const PANTS = ['pants_chino', 'pants_palazzo', 'pants_jodhpur'];

    /** همهٔ مدل‌هایی که پاچه دارند. */
    protected const TROUSERS = ['suit_trousers', 'pants_chino', 'pants_palazzo', 'pants_jodhpur'];

    /** @return array<string, float> */
    protected function body(string $size): array
    {
        $bespoke = static::BODIES[$size] ?? null;

        return $bespoke === null
            ? Measurements::complete(Measurements::fromSize($size))
            : Measurements::complete($bespoke);
    }

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, string $size = '40', array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            $this->body($size),
            [],
            array_merge($generator->defaultParams(), $params),
        );
    }

    /** قطعه با کد داده‌شده. */
    protected function piece(array $pieces, string $code): ?array
    {
        foreach ($pieces as $piece) {
            if (($piece['code'] ?? '') === $code) {
                return $piece;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    protected function parts(array $pieces, array $parts): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => in_array((string) ($piece['meta']['part'] ?? ''), $parts, true),
        ));
    }

    /** طول همهٔ لبه‌های یک برچسب روی یک قطعه. */
    protected function tagLength(array $piece, string $tag): float
    {
        $length = 0.0;

        foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
            $length += Geometry::edgeLength($piece['outline'], $edge);
        }

        return round($length, 3);
    }

    /* ---------------------------------------------------------------------
     |  ثبت و سلامت هندسی
     * ------------------------------------------------------------------- */

    public function test_the_suit_family_and_the_new_trousers_are_registered(): void
    {
        $this->assertArrayHasKey('suit', GeneratorRegistry::GROUPS);

        $suits = GeneratorRegistry::group('suit');

        foreach (static::SUIT as $key) {
            $this->assertArrayHasKey($key, $suits, "«{$key}» در گروه کت‌وشلوار نیست.");
        }

        $pants = GeneratorRegistry::group('pants');

        foreach (static::PANTS as $key) {
            $this->assertArrayHasKey($key, $pants, "«{$key}» در گروه پایین‌تنه نیست.");
        }
    }

    public function test_every_model_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_merge(static::SUIT, static::PANTS) as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                $codes = array_column($pieces, 'code');
                $this->assertSame(count($codes), count(array_unique($codes)), "«{$key}|{$size}» کد قطعهٔ تکراری دارد.");

                foreach ($pieces as $piece) {
                    $where = "{$key}|{$size}|{$piece['code']}";
                    $outline = $piece['outline'] ?? [];

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$where} مسیر ندارد.");
                    $this->assertFalse(Geometry::selfIntersects($outline), "{$where} مسیرش خودش را قطع می‌کند.");

                    $tags = $piece['meta']['edges'] ?? null;
                    $this->assertIsArray($tags, "{$where} برچسب لبه ندارد.");
                    $this->assertCount(count($outline), $tags, "{$where} شمار برچسب و شمار لبه یکی نیست.");

                    foreach ($tags as $tag) {
                        $this->assertContains($tag, static::EDGE_TAGS, "{$where} برچسب لبهٔ ناشناخته دارد.");
                    }

                    $this->assertIsArray($piece['meta']['fold_edges'] ?? null, "{$where} meta.fold_edges ندارد.");
                    $this->assertArrayHasKey('girth_role', $piece['meta'], "{$where} نقش دور اعلام نکرده است.");
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  کت: آستین، درز پنلی و یقه‌پذیری
     * ------------------------------------------------------------------- */

    public function test_the_two_piece_sleeve_adds_up_to_the_measured_armhole(): void
    {
        foreach (['suit_jacket', 'suit_tuxedo'] as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);

                $armhole = 0.0;
                $cap = 0.0;

                foreach ($pieces as $piece) {
                    if (($piece['layer'] ?? 'outer') !== 'outer') {
                        continue;
                    }

                    if (($piece['meta']['part'] ?? '') === 'sleeve') {
                        $cap += $this->tagLength($piece, 'armhole');

                        continue;
                    }

                    $armhole += $this->tagLength($piece, 'armhole');
                }

                $this->assertGreaterThan(20.0, $armhole, "{$key}|{$size} حلقهٔ آستین ندارد.");

                $ease = $cap - $armhole;
                $expected = (float) GeneratorRegistry::make($key)->defaultParams()['cap_ease'];

                $this->assertEqualsWithDelta(
                    $expected,
                    $ease,
                    0.4,
                    sprintf(
                        '%s|%s: سرآستین %.2f و حلقه %.2f؛ آزادی %.2f در برابر %.2f خواسته‌شده.',
                        $key, $size, $cap, $armhole, $ease, $expected,
                    ),
                );
            }
        }
    }

    public function test_the_two_sleeve_pieces_are_both_present_and_sewn_at_the_same_length(): void
    {
        $pieces = $this->build('suit_jacket');
        $upper = $this->piece($pieces, 'suit-upper-sleeve');
        $under = $this->piece($pieces, 'suit-under-sleeve');

        $this->assertNotNull($upper, 'کت باید آستین رو داشته باشد.');
        $this->assertNotNull($under, 'کت باید آستین زیر داشته باشد.');

        // دو درزی که به هم دوخته می‌شوند: درز جلوی آستین و درز پشت آن
        foreach ([[6, 3, 'جلو'], [4, 1, 'پشت']] as [$upperEdge, $underEdge, $label]) {
            $walk = PieceOps::walk($upper, [$upperEdge], $under, [$underEdge], ['tolerance' => static::SEAM_MATCH]);

            $this->assertGreaterThan(20.0, $walk['a']['seam'], "درز {$label} آستین طول واقعی ندارد.");
            $this->assertTrue(
                $walk['matched'],
                sprintf('درز %s آستین: رو %.2f و زیر %.2f.', $label, $walk['a']['seam'], $walk['b']['seam']),
            );
        }
    }

    public function test_the_panel_seams_of_the_jacket_walk_to_the_same_length(): void
    {
        foreach (['suit_jacket' => 'suit-', 'suit_tuxedo' => 'tuxedo-'] as $key => $prefix) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);

                foreach (['front', 'back'] as $side) {
                    $center = $this->piece($pieces, $prefix.$side.'-center');
                    $panel = $this->piece($pieces, $prefix.$side.'-side');

                    $this->assertNotNull($center, "{$key}: پنل میانی {$side} نیست.");
                    $this->assertNotNull($panel, "{$key}: پنل پهلوی {$side} نیست.");

                    $walk = PieceOps::walk(
                        $center,
                        $center['meta']['seam_edges'],
                        $panel,
                        $panel['meta']['seam_edges'],
                        ['tolerance' => static::SEAM_MATCH],
                    );

                    $this->assertGreaterThan(20.0, $walk['a']['seam'], "{$key}|{$size}: درز پنلی طول واقعی ندارد.");
                    $this->assertTrue(
                        $walk['matched'],
                        sprintf('%s|%s درز پنلی %s: %.2f در برابر %.2f.', $key, $size, $side, $walk['a']['seam'], $walk['b']['seam']),
                    );
                }

                // درز پهلو هم درزی است که دوخته می‌شود
                $side = PieceOps::walk(
                    $this->piece($pieces, $prefix.'front-side'),
                    'side',
                    $this->piece($pieces, $prefix.'back-side'),
                    'side',
                    ['tolerance' => static::SEAM_MATCH],
                );

                $this->assertTrue($side['matched'], "{$key}|{$size} درز پهلوی جلو و پشت جور نیست.");
            }
        }
    }

    public function test_the_jacket_keeps_a_neck_edge_that_a_collar_can_sit_on(): void
    {
        foreach (['suit_jacket' => 'suit-', 'suit_tuxedo' => 'tuxedo-'] as $key => $prefix) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $neck = 0.0;

                foreach (['front', 'back'] as $side) {
                    $center = $this->piece($pieces, $prefix.$side.'-center');
                    $measured = $this->tagLength($center, 'neck');

                    $this->assertGreaterThan(4.0, $measured, "{$key}|{$size}: لبهٔ خط یقهٔ {$side} گم شده است.");
                    $this->assertEqualsWithDelta(
                        $measured,
                        (float) ($center['meta']['neck_length'] ?? -1),
                        0.2,
                        "{$key}|{$size}: طول یقهٔ اعلامی با لبهٔ واقعی نمی‌خواند.",
                    );

                    $neck += $measured;
                }

                // خط یقهٔ کامل باید از دور گردن بزرگ‌تر باشد، وگرنه یقه روی گردن می‌فشارد
                $this->assertGreaterThan(
                    $this->body($size)['neck'],
                    $neck * 2,
                    "{$key}|{$size}: دور خط یقه از دور گردن کوچک‌تر است.",
                );
            }
        }
    }

    public function test_the_jacket_carries_the_pieces_a_tailored_jacket_needs(): void
    {
        $pieces = $this->build('suit_jacket');
        $codes = array_column($pieces, 'code');

        foreach ([
            'suit-front-center', 'suit-front-side', 'suit-back-center', 'suit-back-side',
            'suit-upper-sleeve', 'suit-under-sleeve',
            'suit-under-collar', 'suit-front-facing', 'suit-back-neck-facing',
            'suit-hip-jet', 'suit-hip-flap', 'suit-hip-bag',
            'suit-chest-jet', 'suit-chest-bag',
            'suit-lining-front', 'suit-lining-back',
        ] as $code) {
            $this->assertContains($code, $codes, "کت رسمی باید قطعهٔ «{$code}» را داشته باشد.");
        }

        // خط برگردان و چاک پشت باید روی خودِ قطعه علامت خورده باشند
        $front = $this->piece($pieces, 'suit-front-center');
        $back = $this->piece($pieces, 'suit-back-center');

        $this->assertContains('roll_line', array_column($front['markers'], 'key'), 'خط برگردان یقه روی تنهٔ جلو علامت نخورده است.');
        $this->assertNotEmpty($front['drills'], 'جای دکمه‌های جلو علامت نخورده است.');
        $this->assertContains('vent', array_column($back['markers'], 'key'), 'چاک مرکز پشت علامت نخورده است.');
    }

    public function test_the_tuxedo_is_a_shawl_collared_one_button_jacket(): void
    {
        $generator = GeneratorRegistry::make('suit_tuxedo');
        $defaults = $generator->defaultParams();

        $this->assertSame('shawl', $defaults['collar_style']);
        $this->assertEqualsWithDelta(1.0, (float) $defaults['buttons'], 0.01, 'اسموکینگ یک دکمه دارد.');
        $this->assertEqualsWithDelta(0.0, (float) $defaults['back_vent'], 0.01, 'اسموکینگ چاک پشت ندارد.');

        $codes = array_column($this->build('suit_tuxedo'), 'code');

        $this->assertContains('tuxedo-shawl-facing', $codes, 'اسموکینگ باید سجاف و یقهٔ شالیِ یک‌سره داشته باشد.');
        $this->assertNotContains('tuxedo-hip-flap', $codes, 'جیب اسموکینگ درپوش ندارد.');
    }

    public function test_the_waistcoat_side_seams_match_after_the_bust_dart_is_closed(): void
    {
        foreach (array_keys(static::BODIES) as $size) {
            $pieces = $this->build('suit_waistcoat', $size);
            $front = $this->parts($pieces, ['front_bodice'])[0] ?? null;
            $back = $this->parts($pieces, ['back_bodice'])[0] ?? null;

            $this->assertNotNull($front);
            $this->assertNotNull($back);

            $walk = PieceOps::walk($front, 'side', $back, 'side', ['tolerance' => static::SEAM_MATCH]);

            $this->assertTrue(
                $walk['matched'],
                sprintf('جلیقه|%s درز پهلو: جلو %.2f و پشت %.2f.', $size, $walk['a']['seam'], $walk['b']['seam']),
            );

            // لبهٔ جلو باید نوک‌دار باشد: مرکز جلو پایین‌تر از درز پهلو تمام می‌شود
            $this->assertGreaterThan(0.0, (float) ($front['meta']['hem_point'] ?? 0), 'لبهٔ جلوی جلیقه نوک ندارد.');
        }
    }

    /* ---------------------------------------------------------------------
     |  شلوارها
     * ------------------------------------------------------------------- */

    public function test_the_two_legs_of_every_new_trouser_sew_together(): void
    {
        foreach (static::TROUSERS as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $front = $this->parts($pieces, ['front_leg'])[0] ?? null;
                $back = $this->parts($pieces, ['back_leg'])[0] ?? null;

                $this->assertNotNull($front, "«{$key}» پای جلو ندارد.");
                $this->assertNotNull($back, "«{$key}» پای پشت ندارد.");

                $inseam = PieceOps::walk(
                    $front,
                    $front['meta']['inseam_edges'],
                    $back,
                    $back['meta']['inseam_edges'],
                    ['tolerance' => static::SEAM_MATCH],
                );
                $side = PieceOps::walk(
                    $front,
                    $front['meta']['side_edges'],
                    $back,
                    $back['meta']['side_edges'],
                    ['tolerance' => static::SEAM_MATCH],
                );

                $this->assertTrue($inseam['matched'], sprintf('%s|%s درز داخل پا: %.2f در برابر %.2f.', $key, $size, $inseam['a']['seam'], $inseam['b']['seam']));
                $this->assertTrue($side['matched'], sprintf('%s|%s درز پهلو: %.2f در برابر %.2f.', $key, $size, $side['a']['seam'], $side['b']['seam']));

                // دور کمر تمام‌شده باید دقیقاً برابر دور کمر هدفِ همین درفت باشد
                $waist = (PieceOps::seamLength($front, $front['meta']['waist_edges'])
                    + PieceOps::seamLength($back, $back['meta']['waist_edges'])) * 2;

                $this->assertEqualsWithDelta(
                    (float) $front['meta']['waist_target'],
                    $waist,
                    0.5,
                    "{$key}|{$size} دور کمر تمام‌شده با دور کمر هدف نمی‌خواند.",
                );
            }
        }
    }

    public function test_the_jodhpur_really_is_wide_at_the_thigh_and_close_below_the_knee(): void
    {
        foreach (array_keys(static::BODIES) as $size) {
            foreach ($this->parts($this->build('pants_jodhpur', $size), ['front_leg', 'back_leg']) as $leg) {
                $zones = $leg['meta']['jodhpur'] ?? null;

                $this->assertIsArray($zones, 'جودپور باید دو ناحیه‌اش را اندازه بگیرد.');
                $this->assertNotNull($zones['knee_width'], 'جودپور باید خط زانو داشته باشد.');

                $this->assertGreaterThan(
                    $zones['knee_width'] * 1.4,
                    $zones['thigh_width'],
                    sprintf(
                        'جودپور|%s|%s: ران %.1f و زانو %.1f؛ ناحیهٔ گشادِ بالای زانو دیده نمی‌شود.',
                        $size, $leg['code'], $zones['thigh_width'], $zones['knee_width'],
                    ),
                );
                $this->assertLessThan(
                    $zones['knee_width'],
                    $zones['hem_width'],
                    'جودپور از زانو به پایین باید چسبان شود.',
                );
            }
        }
    }

    public function test_the_chino_has_a_flat_front_and_a_slanted_pocket(): void
    {
        foreach (array_keys(static::BODIES) as $size) {
            $pieces = $this->build('pants_chino', $size);
            $front = $this->parts($pieces, ['front_leg'])[0];

            $this->assertSame([], $front['darts'], 'چینو جلو-صاف است و ساسون جلو ندارد.');
            $this->assertSame([], $front['pleats'], 'چینو جلو-صاف است و پیلی جلو ندارد.');

            // «جلو-صاف» یعنی پارچهٔ لبهٔ کمر و اندازهٔ تمام‌شده‌اش یکی است
            $this->assertEqualsWithDelta(
                (float) $front['meta']['waist_fabric'],
                (float) $front['meta']['waist_finished'],
                0.05,
                'روی جلوی چینو نباید هیچ پارچه‌ای در کمر خورده شود.',
            );

            $pocket = $front['meta']['pocket'] ?? null;
            $this->assertIsArray($pocket, 'چینو باید جیب اریب پهلو داشته باشد.');
            $this->assertGreaterThan(6.0, (float) $pocket['opening'], 'دهانهٔ جیب اریب باید دستِ آدم را جا بدهد.');

            $this->assertContains('pocket', array_column($front['markers'], 'key'), 'خط دهانهٔ جیب علامت نخورده است.');
            $this->assertNotNull($this->piece($pieces, 'chino-pocket-bag'));
            $this->assertNotNull($this->piece($pieces, 'chino-pocket-facing'));
        }

    }

    public function test_the_palazzo_stays_as_wide_at_the_knee_as_at_the_hem(): void
    {
        foreach (array_keys(static::BODIES) as $size) {
            foreach ($this->parts($this->build('pants_palazzo', $size), ['front_leg', 'back_leg']) as $leg) {
                $knee = (float) ($leg['meta']['knee_width'] ?? 0);
                $hem = (float) ($leg['meta']['hem_width'] ?? 0);

                $this->assertGreaterThan(0.0, $knee, 'پالازو باید خط زانو داشته باشد.');
                $this->assertGreaterThan(
                    $knee,
                    $hem,
                    sprintf('پالازو|%s|%s: زانو %.1f و دم پا %.1f؛ خط پهلو باید تا پایین باز بماند.', $size, $leg['code'], $knee, $hem),
                );

                // پالازو از خط باسن به بعد پهن است: دم پا دست‌کم یک‌ونیم برابر زانوی یک شلوار راسته
                $this->assertGreaterThan(
                    $knee * 0.9,
                    $hem,
                    'پالازو نباید از زانو به پایین جمع شود.',
                );
            }
        }
    }

    public function test_the_suit_trousers_add_the_fabric_the_turn_up_eats(): void
    {
        $plain = $this->parts($this->build('suit_trousers', '40', ['cuff' => 0]), ['front_leg'])[0];
        $cuffed = $this->parts($this->build('suit_trousers', '40', ['cuff' => 4]), ['front_leg'])[0];

        $this->assertEqualsWithDelta(
            8.0,
            (float) $cuffed['meta']['leg_length'] - (float) $plain['meta']['leg_length'],
            0.2,
            'برگردان چهار سانتی‌متری باید هشت سانتی‌متر پارچه بخورد، وگرنه شلوار کوتاه درمی‌آید.',
        );

        $this->assertContains('cuff_fold', array_column($cuffed['markers'], 'key'), 'خط تای برگردان علامت نخورده است.');
        $this->assertNotNull($this->piece($this->build('suit_trousers'), 'suit-trousers-belt-loops'));
    }
}
