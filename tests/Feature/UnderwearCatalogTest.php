<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bodies;
use Tests\TestCase;

/**
 * کاتالوگ لباس زیر.
 *
 * لباس زیر چهار چیز دارد که جای خطاست و هیچ‌کدام از بیرون دیده نمی‌شوند:
 * الگو باید کوچک‌تر از بدن باشد، هر لبهٔ باز باید کشِ حساب‌شده بخواهد، هر شورتی
 * باید نوار فاق جدا داشته باشد، و در سوتین دو لبه باید میلی‌متری بر هم بنشینند
 * (درزِ کاپ، و لبهٔ کاپ روی سینه‌بند). این آزمون‌ها همان چهار را می‌پایند.
 */
class UnderwearCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** کلیدهای این خانواده. */
    protected const KEYS = [
        'bra_soft', 'bra_push_up', 'bralette',
        'panty_brief', 'panty_hipster', 'boxer_brief', 'slip_full',
    ];

    /** شورت‌ها؛ همگی باید نوار فاق داشته باشند. */
    protected const BOTTOMS = ['panty_brief', 'panty_hipster', 'boxer_brief'];

    /** سوتین‌های کاپ‌دار؛ برالت عمداً این‌جا نیست چون کاپ ندارد. */
    protected const CUPPED = ['bra_soft', 'bra_push_up'];

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, string $size = '40', array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Bodies::body($size),
            [],
            array_merge($generator->defaultParams(), $params),
        );
    }

    /** @return array<int, array<string, mixed>> */
    protected function notions(array $pieces, string $type): array
    {
        $out = [];

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                if (($notion['type'] ?? '') === $type) {
                    $out[] = $notion;
                }
            }
        }

        return $out;
    }

    /** نخستین قطعه با این نقش. */
    protected function part(array $pieces, string $part): ?array
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? '') === $part) {
                return $piece;
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     |  فهرست و سلامت هندسی
     * ------------------------------------------------------------------- */

    public function test_the_family_is_registered_with_every_model(): void
    {
        $this->assertArrayHasKey('underwear', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('underwear');

        foreach (static::KEYS as $key) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در کاتالوگ لباس زیر نیست.");
            $this->assertSame('underwear', GeneratorRegistry::groupOf($key));
            $this->assertNotSame('', GeneratorRegistry::make($key)->label());
        }
    }

    public function test_every_model_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (static::KEYS as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);

                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $outline = $piece['outline'] ?? [];
                    $where = "{$key}|{$size}|{$piece['code']}";

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$where} مسیر ندارد.");
                    $this->assertFalse(
                        Geometry::selfIntersects($outline),
                        "{$where} مسیرش خودش را قطع می‌کند.",
                    );
                    $this->assertGreaterThan(3.0, Geometry::area($outline), "{$where} مساحتش باورکردنی نیست.");
                    $this->assertCount(
                        count($outline),
                        $piece['meta']['edges'] ?? [],
                        "{$where} برچسب لبه‌هایش با تعداد لبه‌ها جور نیست.",
                    );
                }

                $codes = array_column($pieces, 'code');
                $this->assertSame($codes, array_unique($codes), "{$key}|{$size} کد قطعهٔ تکراری دارد.");
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  آزادی منفی
     * ------------------------------------------------------------------- */

    public function test_every_stretch_shell_declares_the_ratio_it_was_cut_with(): void
    {
        foreach (static::KEYS as $key) {
            $declared = false;

            foreach ($this->build($key) as $piece) {
                if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                    continue;
                }

                if (isset($piece['meta']['stretch_ratio'])) {
                    $declared = true;
                    $this->assertLessThan(1.0, (float) $piece['meta']['stretch_ratio']);
                }
            }

            $this->assertTrue($declared, "«{$key}» باید بگوید با چه ضریب کششی بریده شده.");
        }
    }

    public function test_a_panty_is_cut_smaller_than_the_body(): void
    {
        $body = Bodies::body('40');

        foreach (static::BOTTOMS as $key) {
            $pieces = $this->build($key, '40', ['stretch' => 0.8, 'rise_drop' => 0]);
            $waist = 0.0;

            foreach ($pieces as $piece) {
                $girth = $piece['meta']['girth']['waist'] ?? null;

                if ($girth === null) {
                    continue;
                }

                $waist += (float) $girth;
            }

            $this->assertGreaterThan(0, $waist, "«{$key}» دور کمر تمام‌شده‌اش را اعلام نکرده.");
            $this->assertLessThan(
                (float) $body['waist'],
                $waist,
                "«{$key}» باید کوچک‌تر از دور بدن بریده شود؛ شورتِ اندازهٔ بدن روی تن می‌چرخد.",
            );
        }
    }

    public function test_a_stretchier_fabric_gives_a_smaller_pattern(): void
    {
        $widthOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'panty_front') {
                    return Geometry::width($piece['outline']);
                }
            }

            return 0.0;
        };

        $wide = $widthOf($this->build('panty_brief', '40', ['stretch' => 0.98]));
        $tight = $widthOf($this->build('panty_brief', '40', ['stretch' => 0.7]));

        $this->assertGreaterThan(0, $wide);
        $this->assertLessThan($wide, $tight, 'پارچهٔ پرکشش‌تر یعنی الگوی کوچک‌تر.');
    }

    /* ---------------------------------------------------------------------
     |  کش
     * ------------------------------------------------------------------- */

    public function test_every_open_edge_asks_for_a_measured_elastic(): void
    {
        // کمبینزون عمداً این‌جا نیست: روی اریب بریده می‌شود، کش ندارد و لبه‌هایش
        // با نوار اریب تمام می‌شوند. خودش هم همین را در یادداشت می‌گوید.
        foreach (['bra_soft', 'bra_push_up', 'bralette', 'panty_brief', 'panty_hipster', 'boxer_brief'] as $key) {
            $elastics = $this->notions($this->build($key), 'elastic');

            $this->assertNotEmpty($elastics, "«{$key}» باید کش بخواهد؛ بدون کش هیچ درزی لبه را نگه نمی‌دارد.");

            foreach ($elastics as $elastic) {
                $length = (float) ($elastic['length'] ?? 0);
                $edge = (float) ($elastic['edge_length'] ?? 0);

                $this->assertGreaterThan(0, $length, "{$key}: بلندی کش باید حساب شده باشد.");
                $this->assertGreaterThan(0, $edge, "{$key}: طول لبه‌ای که کش می‌خورد باید ثبت شده باشد.");
                $this->assertLessThan(
                    $edge,
                    $length,
                    "{$key}: کش «{$elastic['label']}» باید کوتاه‌تر از لبه‌ای باشد که نگهش می‌دارد.",
                );
            }
        }
    }

    public function test_a_shorter_elastic_ratio_gives_a_shorter_elastic(): void
    {
        $total = fn (array $pieces) => array_sum(array_map(
            fn (array $notion) => (float) $notion['length'],
            $this->notions($pieces, 'elastic'),
        ));

        $loose = $total($this->build('panty_brief', '40', ['elastic_ratio' => 0.98]));
        $tight = $total($this->build('panty_brief', '40', ['elastic_ratio' => 0.75]));

        $this->assertGreaterThan(0, $loose);
        $this->assertLessThan($loose, $tight, 'نسبت کمتر یعنی کش کوتاه‌تر.');
    }

    /* ---------------------------------------------------------------------
     |  نوار فاق
     * ------------------------------------------------------------------- */

    public function test_every_bottom_carries_a_separate_gusset(): void
    {
        foreach (static::BOTTOMS as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);
                $gusset = $this->part($pieces, 'gusset');

                $this->assertNotNull($gusset, "«{$key}|{$size}» باید نوار فاق داشته باشد؛ اختیاری نیست.");
                $this->assertSame(2, (int) $gusset['cut_quantity'], 'نوار فاق دو لایه است: رو و پنبه.');

                // پهنای نوار فاق باید دقیقاً به اندازهٔ لبهٔ فاقِ باز‌شدهٔ پنل باشد
                foreach ($pieces as $piece) {
                    $edge = $piece['meta']['gusset_edge'] ?? null;

                    if ($edge === null) {
                        continue;
                    }

                    $seam = Geometry::edgeLength($piece['outline'], (int) $edge)
                        * (empty($piece['on_fold']) ? 1 : 2);

                    $this->assertEqualsWithDelta(
                        (float) $gusset['meta']['gusset_width'],
                        $seam,
                        0.15,
                        "{$key}|{$size}|{$piece['code']}: لبهٔ فاق و پهنای نوار فاق بر هم نمی‌نشینند.",
                    );
                }
            }
        }
    }

    public function test_a_panty_is_not_mistaken_for_a_trouser_leg(): void
    {
        $parts = array_map(fn (array $p) => $p['meta']['part'] ?? '', $this->build('panty_brief'));

        $this->assertContains('panty_front', $parts);
        $this->assertNotContains('front_leg', $parts, 'شورت زیر پاچهٔ شلوار نیست؛ نه درز داخل پا دارد نه فاق شلوار.');

        $boxer = array_map(fn (array $p) => $p['meta']['part'] ?? '', $this->build('boxer_brief'));

        $this->assertContains('boxer_front', $boxer);
        $this->assertNotContains('front_leg', $boxer, 'باکسر پاچهٔ کوتاه دارد ولی پاچهٔ شلوار نیست.');
    }

    public function test_the_side_seams_of_a_bottom_walk_to_the_same_length(): void
    {
        foreach (static::BOTTOMS as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);
                $lengths = [];

                foreach ($pieces as $piece) {
                    $edge = $piece['meta']['side_edge'] ?? null;

                    if ($edge === null) {
                        continue;
                    }

                    $lengths[$piece['meta']['side']] = Geometry::edgeLength($piece['outline'], (int) $edge);
                }

                $this->assertCount(2, $lengths, "{$key}|{$size} جلو و پشت درز پهلو ندارند.");
                $this->assertEqualsWithDelta(
                    $lengths['front'],
                    $lengths['back'],
                    0.15,
                    "{$key}|{$size}: درز پهلوی جلو و پشت هم‌اندازه نیستند؛ بلندی بیشترِ پشت باید روی مرکز پشت باشد.",
                );
            }
        }
    }

    public function test_less_coverage_makes_a_smaller_back(): void
    {
        $areaOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'panty_back') {
                    return Geometry::area($piece['outline']);
                }
            }

            return 0.0;
        };

        $full = $areaOf($this->build('panty_brief', '40', ['coverage' => 'full']));
        $medium = $areaOf($this->build('panty_brief', '40', ['coverage' => 'medium']));
        $cheeky = $areaOf($this->build('panty_brief', '40', ['coverage' => 'cheeky']));

        $this->assertGreaterThan($medium, $full, 'پوشش کامل باید پارچهٔ بیشتری از پوشش معمولی داشته باشد.');
        $this->assertGreaterThan($cheeky, $medium, 'پوشش معمولی باید پارچهٔ بیشتری از پوشش کم داشته باشد.');
    }

    public function test_a_hipster_sits_lower_and_wider_than_a_brief(): void
    {
        $front = function (string $key): array {
            foreach ($this->build($key) as $piece) {
                if (($piece['meta']['part'] ?? '') === 'panty_front') {
                    return $piece;
                }
            }

            return [];
        };

        $brief = $front('panty_brief');
        $hipster = $front('panty_hipster');

        $this->assertGreaterThan(
            Geometry::height($hipster['outline']),
            Geometry::height($brief['outline']),
            'شورت کلاسیک باید بلندتر از هیپستر باشد؛ کمرش بالاتر می‌نشیند.',
        );
        $this->assertGreaterThan(
            (float) $brief['meta']['girth']['waist'],
            (float) $hipster['meta']['girth']['waist'],
            'کمرِ هیپستر روی باسن می‌نشیند و آن‌جا دور بدن بزرگ‌تر است.',
        );
    }

    /* ---------------------------------------------------------------------
     |  سوتین
     * ------------------------------------------------------------------- */

    public function test_the_two_halves_of_a_bra_cup_share_the_same_seam(): void
    {
        foreach (static::CUPPED as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);
                $upper = $this->part($pieces, 'bra_cup_upper');
                $lower = $this->part($pieces, 'bra_cup_lower');

                $this->assertNotNull($upper, "{$key}|{$size} کاپ بالا ندارد.");
                $this->assertNotNull($lower, "{$key}|{$size} کاپ پایین ندارد.");

                $a = Geometry::edgeLength($upper['outline'], (int) $upper['meta']['cup_seam_edge']);
                $b = Geometry::edgeLength($lower['outline'], (int) $lower['meta']['cup_seam_edge']);

                $this->assertGreaterThan(5.0, $a, "{$key}|{$size} درز کاپ باورکردنی نیست.");
                $this->assertEqualsWithDelta(
                    $a,
                    $b,
                    0.1,
                    "{$key}|{$size}: درز کاپ بالا و پایین هم‌اندازه نیستند و به هم دوخته نمی‌شوند.",
                );
            }
        }
    }

    public function test_the_cup_sits_exactly_on_the_cradle_it_is_sewn_to(): void
    {
        foreach (static::CUPPED as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);
                $lower = $this->part($pieces, 'bra_cup_lower');
                $cradle = $this->part($pieces, 'bra_cradle');

                $this->assertNotNull($cradle, "{$key}|{$size} سینه‌بند جلو ندارد.");

                $cup = Geometry::edgesLength($lower['outline'], $lower['meta']['cup_seat_edges'])
                    - (float) $lower['meta']['cup_dart_intake'];
                $seat = Geometry::edgesLength($cradle['outline'], $cradle['meta']['cup_seat_edges']);

                $this->assertGreaterThan(10.0, $seat, "{$key}|{$size} قوس کاپ روی سینه‌بند باورکردنی نیست.");
                $this->assertEqualsWithDelta(
                    $cup,
                    $seat,
                    0.35,
                    "{$key}|{$size}: لبهٔ پایین کاپ و قوس کاپ روی سینه‌بند بر هم نمی‌نشینند.",
                );
            }
        }
    }

    public function test_the_bra_band_side_seam_matches_between_cradle_and_wing(): void
    {
        foreach (static::CUPPED as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);
                $cradle = $this->part($pieces, 'bra_cradle');
                $wing = $this->part($pieces, 'bra_wing');

                $this->assertNotNull($wing, "{$key}|{$size} بال پشت ندارد.");

                $this->assertEqualsWithDelta(
                    Geometry::edgeLength($cradle['outline'], (int) $cradle['meta']['side_edge']),
                    Geometry::edgeLength($wing['outline'], (int) $wing['meta']['side_edge']),
                    0.1,
                    "{$key}|{$size}: درز پهلوی قاب کاپ و بال پشت هم‌اندازه نیستند.",
                );

                // دورِ کل سینه‌بند: دو قاب جلو و دو بال پشت
                $band = (2 * (float) $cradle['meta']['cradle_width']) + (2 * (float) $wing['meta']['wing_length']);

                $this->assertEqualsWithDelta(
                    (float) $cradle['meta']['band_girth'],
                    $band,
                    0.5,
                    "{$key}|{$size}: جمع قاب جلو و بال پشت به دور سینه‌بند نمی‌رسد.",
                );
            }
        }
    }

    public function test_a_bra_strap_actually_reaches_from_the_back_to_the_front(): void
    {
        foreach (static::CUPPED as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);
                $strap = $this->part($pieces, 'strap');

                $this->assertNotNull($strap, "{$key}|{$size} بند شانه ندارد.");

                $path = (float) $strap['meta']['strap_path'];
                $front = (float) $strap['meta']['strap_front'];
                $back = (float) $strap['meta']['strap_back'];

                $this->assertGreaterThan(4.0, $front, "{$key}|{$size}: بند از جلو به کاپ نمی‌رسد.");
                $this->assertGreaterThan(7.0, $back, "{$key}|{$size}: بند از پشت به سینه‌بند نمی‌رسد.");
                $this->assertEqualsWithDelta($front + $back, $path, 0.15);

                $this->assertGreaterThan(
                    $path,
                    Geometry::width($strap['outline']),
                    "{$key}|{$size}: بند باید بلندتر از مسیرش روی بدن بریده شود تا حلقه و سگک جا بگیرند.",
                );
            }
        }
    }

    public function test_the_back_closure_asks_for_hooks_in_rows(): void
    {
        foreach (static::CUPPED as $key) {
            $pieces = $this->build($key, '40', ['hook_rows' => 3, 'hook_columns' => 2]);
            $wing = $this->part($pieces, 'bra_wing');
            $hooks = $this->notions($pieces, 'hook');

            $this->assertNotEmpty($hooks, "«{$key}» باید بست قزنی بخواهد.");
            $this->assertSame(3, (int) $wing['meta']['hook_rows']);
            $this->assertSame(2, (int) $wing['meta']['hook_columns']);
            $this->assertSame(6, (int) $hooks[0]['count'], 'تعداد قزن = تعداد قزن روی هر ستون × تعداد ردیف تنظیم.');
        }
    }

    public function test_a_push_up_cup_has_a_dart_at_the_bottom_and_a_layer_under_it(): void
    {
        $pieces = $this->build('bra_push_up');
        $lower = $this->part($pieces, 'bra_cup_lower');

        $this->assertNotEmpty($lower['darts'], 'کاپ پوش‌آپ باید ساسون پایین کاپ داشته باشد.');

        $dart = $lower['darts'][0];
        $this->assertSame('cup', $dart['type']);
        $this->assertGreaterThan(0.5, (float) $dart['intake']);
        $this->assertContains(
            (int) $dart['edge'],
            $lower['meta']['cup_seat_edges'],
            'ساسون پوش‌آپ باید روی لبهٔ نشستن کاپ باشد، نه روی درز کاپ.',
        );

        $this->assertNotNull($this->part($pieces, 'cup_pad'), 'کاپ پوش‌آپ لایه‌دار است.');
        $this->assertTrue((bool) $this->part($pieces, 'bra_cradle')['meta']['underwire'], 'پوش‌آپ فنر دارد.');

        // ساسون بزرگ‌تر یعنی لبهٔ کاپ کوتاه‌تر، پس قاب کاپ هم باید کوتاه‌تر شود
        $seat = function (float $dart): float {
            foreach ($this->build('bra_push_up', '40', ['cup_dart' => $dart]) as $piece) {
                if (($piece['meta']['part'] ?? '') === 'bra_cradle') {
                    return Geometry::edgesLength($piece['outline'], $piece['meta']['cup_seat_edges']);
                }
            }

            return 0.0;
        };

        $this->assertGreaterThan($seat(3.5), $seat(0.8), 'ساسون بزرگ‌تر باید قاب کاپ کوتاه‌تری بدهد.');
    }

    public function test_a_soft_bra_and_a_bralette_have_no_wire_and_no_moulded_cup(): void
    {
        $soft = $this->build('bra_soft');

        $this->assertFalse(
            (bool) $this->part($soft, 'bra_cradle')['meta']['underwire'],
            'سوتین بدون فنر نباید فنر بخواهد.',
        );
        $this->assertEmpty(array_filter(
            $this->notions($soft, 'other'),
            fn (array $notion) => str_contains((string) $notion['label'], 'فنر'),
        ), 'سوتین بدون فنر نباید فنر در فهرست یراقش داشته باشد.');

        $bralette = $this->build('bralette');

        $this->assertNull($this->part($bralette, 'bra_cup_upper'), 'برالت کاپِ قالبی ندارد.');
        $this->assertNull($this->part($bralette, 'bra_cup_lower'), 'برالت کاپِ قالبی ندارد.');

        foreach (['front_bodice', 'back_bodice'] as $part) {
            $panel = $this->part($bralette, $part);
            $this->assertFalse((bool) $panel['meta']['underwire'], 'برالت فنر ندارد.');
            $this->assertFalse((bool) $panel['meta']['molded_cup'], 'برالت کاپِ قالبی ندارد.');
        }

        $band = $this->part($bralette, 'binding');
        $this->assertNotNull($band, 'برالت باید نوار زیر سینه داشته باشد؛ همهٔ وزن روی همان است.');

        $elastic = $this->notions([$band], 'elastic');
        $this->assertNotEmpty($elastic);
        $this->assertLessThan(
            (float) $band['meta']['band_girth'],
            (float) $elastic[0]['length'],
            'کش زیر سینه باید کوتاه‌تر از دور زیر سینه باشد.',
        );
    }

    public function test_the_cup_grows_with_the_difference_between_bust_and_under_bust(): void
    {
        $depthOf = function (array $body): float {
            $generator = GeneratorRegistry::make('bra_soft');

            foreach ($generator->generate($body, [], $generator->defaultParams()) as $piece) {
                if (($piece['meta']['part'] ?? '') === 'bra_cup_lower') {
                    return Geometry::height($piece['outline']);
                }
            }

            return 0.0;
        };

        $body = Bodies::body('40');

        $small = $depthOf(array_merge($body, ['under_bust' => $body['bust'] - 6]));
        $large = $depthOf(array_merge($body, ['under_bust' => $body['bust'] - 22]));

        $this->assertGreaterThan(0, $small);
        $this->assertGreaterThan($small, $large, 'اختلاف بیشترِ سینه و زیر سینه یعنی کاپ عمیق‌تر.');
    }

    public function test_an_estimated_under_bust_is_admitted_in_the_notes(): void
    {
        $generator = GeneratorRegistry::make('bra_soft');
        $body = Bodies::body('40');

        $guessed = $generator->generate(
            array_diff_key($body, ['under_bust' => null]),
            [],
            $generator->defaultParams(),
        );

        $cradle = $this->part($guessed, 'bra_cradle');

        $this->assertTrue(
            (bool) ($cradle['meta']['under_bust_estimated'] ?? false),
            'وقتی زیر سینه گرفته نشده، الگو باید بگوید عدد تخمینی است.',
        );
        $this->assertNotEmpty(array_filter(
            $cradle['meta']['notes'] ?? [],
            fn (string $note) => str_contains($note, 'تخمین'),
        ), 'تخمینی بودن زیر سینه باید در یادداشت قطعه نوشته شود.');

        $measured = $generator->generate(
            array_merge($body, ['under_bust' => 79.5]),
            [],
            $generator->defaultParams(),
        );

        $this->assertArrayNotHasKey(
            'under_bust_estimated',
            $this->part($measured, 'bra_cradle')['meta'],
            'وقتی زیر سینه گرفته شده، نباید ادعای تخمین بشود.',
        );
    }

    /* ---------------------------------------------------------------------
     |  کمبینزون
     * ------------------------------------------------------------------- */

    public function test_the_slip_is_cut_on_the_bias_and_hangs_on_narrow_straps(): void
    {
        $pieces = $this->build('slip_full');

        foreach (['front_bodice', 'back_bodice'] as $part) {
            $piece = $this->part($pieces, $part);

            $this->assertNotNull($piece);
            $this->assertTrue((bool) ($piece['meta']['bias'] ?? false), 'کمبینزون روی اریب بریده می‌شود.');
            $this->assertStringContainsString('اریب', (string) $piece['grainline']['label']);
        }

        $strap = $this->part($pieces, 'strap');

        $this->assertNotNull($strap, 'کمبینزون بند دارد.');
        $this->assertLessThan(2.0, (float) $strap['meta']['finished_width'], 'بند کمبینزون باریک است.');
        $this->assertSame(2, (int) $strap['cut_quantity']);
    }

    public function test_the_slip_front_and_back_side_seams_walk_together(): void
    {
        foreach (Bodies::bodies() as $size) {
            $pieces = $this->build('slip_full', $size);

            $walk = PieceOps::walk(
                $this->part($pieces, 'front_bodice'),
                'side',
                $this->part($pieces, 'back_bodice'),
                'side',
                ['tolerance' => 0.15],
            );

            $this->assertTrue($walk['matched'], "کمبینزون|{$size}: درز پهلوی جلو و پشت هم‌اندازه نیستند.");
        }
    }
}
