<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bodies;
use Tests\TestCase;

/**
 * کاتالوگ لباس خواب.
 *
 * این خانواده دو نیمه دارد و مرزشان جنس پارچه است: پیژامه و روب از پارچهٔ بافته
 * بریده می‌شوند و آزادی **مثبت** می‌گیرند؛ لباس خواب بلند و شلوارک از جرسی بریده
 * می‌شوند و **کوچک‌تر** از بدن‌اند. مهم‌ترین کار این آزمون این است که نگذارد این
 * مرز بی‌صدا جابه‌جا شود — لباس بافته‌ای که ادعا کند کشی است، روی تن تنگ درمی‌آید
 * و اصلاً پوشیده نمی‌شود.
 */
class SleepwearCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** کلیدهای این خانواده. */
    protected const KEYS = ['sleep_pajama', 'sleep_nightgown', 'sleep_robe', 'sleep_shorts'];

    /** مدل‌های بافته: آزادی مثبت، بی هیچ مهرِ کشسانی. */
    protected const WOVEN = ['sleep_pajama', 'sleep_robe'];

    /** مدل‌های کشی: کوچک‌تر از بدن، با مهرِ کشسانی. */
    protected const KNIT = ['sleep_nightgown', 'sleep_shorts'];

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

    /** @return array<int, array<string, mixed>> */
    protected function parts(array $pieces, array $parts): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => in_array((string) ($piece['meta']['part'] ?? ''), $parts, true),
        ));
    }

    protected function part(array $pieces, string $part): ?array
    {
        return $this->parts($pieces, [$part])[0] ?? null;
    }

    /* ---------------------------------------------------------------------
     |  فهرست و سلامت هندسی
     * ------------------------------------------------------------------- */

    public function test_the_family_is_registered_with_every_model(): void
    {
        $this->assertArrayHasKey('sleepwear', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('sleepwear');

        foreach (static::KEYS as $key) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در کاتالوگ لباس خواب نیست.");
            $this->assertSame('sleepwear', GeneratorRegistry::groupOf($key));
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
     |  مرز بافته و کشی
     * ------------------------------------------------------------------- */

    public function test_a_knit_model_declares_the_stretch_it_was_cut_with(): void
    {
        foreach (static::KNIT as $key) {
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

            $this->assertTrue($declared, "«{$key}» از پارچهٔ کشی است و باید ضریب کششش را اعلام کند.");
        }
    }

    public function test_a_woven_model_never_claims_to_be_cut_smaller_than_the_body(): void
    {
        foreach (static::WOVEN as $key) {
            foreach ($this->build($key) as $piece) {
                $this->assertArrayNotHasKey(
                    'stretch_ratio',
                    $piece['meta'],
                    "«{$key}» از پارچهٔ بافته است؛ نباید ادعا کند کوچک‌تر از بدن بریده شده.",
                );
            }
        }
    }

    public function test_a_woven_model_is_bigger_than_the_body_and_a_knit_one_is_smaller(): void
    {
        foreach (Bodies::bodies() as $size) {
            $body = Bodies::body($size);

            foreach (static::WOVEN as $key) {
                $this->assertGreaterThan(
                    (float) $body['bust'],
                    $this->bustGirth($this->build($key, $size)),
                    "«{$key}|{$size}» بافته است و باید بزرگ‌تر از دور سینهٔ بدن بریده شود.",
                );
            }

            $this->assertLessThan(
                (float) $body['bust'],
                $this->bustGirth($this->build('sleep_nightgown', $size)),
                "لباس خواب بلند|{$size} کشی است و باید کوچک‌تر از دور سینهٔ بدن بریده شود.",
            );
        }
    }

    public function test_a_stretchier_fabric_gives_a_smaller_nightgown(): void
    {
        $wide = $this->bustGirth($this->build('sleep_nightgown', '40', ['stretch' => 0.99]));
        $tight = $this->bustGirth($this->build('sleep_nightgown', '40', ['stretch' => 0.75]));

        $this->assertGreaterThan(0, $wide);
        $this->assertLessThan($wide, $tight, 'پارچهٔ پرکشش‌تر یعنی الگوی کوچک‌تر.');
    }

    /** دور سینهٔ تمام‌شده از روی خط نشانهٔ سینهٔ پنل‌های تنه. */
    protected function bustGirth(array $pieces): float
    {
        $total = 0.0;

        foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
            foreach ($piece['markers'] ?? [] as $marker) {
                if (($marker['key'] ?? '') !== 'bust') {
                    continue;
                }

                $width = abs(((float) $marker['to']['x']) - ((float) $marker['from']['x']));
                $total += $width * (int) ($piece['cut_quantity'] ?? 1) * (empty($piece['on_fold']) ? 1 : 2);
            }
        }

        return round($total, 2);
    }

    /* ---------------------------------------------------------------------
     |  کش
     * ------------------------------------------------------------------- */

    public function test_every_open_edge_asks_for_a_measured_elastic(): void
    {
        // روب عمداً این‌جا نیست: نه کش دارد نه بست؛ فقط هم‌پوشانی و بند کمر
        // نگهش می‌دارند و خودش هم همین را در یادداشت می‌گوید.
        foreach (['sleep_pajama', 'sleep_nightgown', 'sleep_shorts'] as $key) {
            $elastics = $this->notions($this->build($key), 'elastic');

            $this->assertNotEmpty($elastics, "«{$key}» باید کش بخواهد.");

            foreach ($elastics as $elastic) {
                $length = (float) ($elastic['length'] ?? 0);
                $edge = (float) ($elastic['edge_length'] ?? 0);

                $this->assertGreaterThan(0, $length, "{$key}: بلندی کش باید حساب شده باشد.");

                if ($edge <= 0) {
                    continue; // نوار کش شلوار طول لبه‌اش را جدا ثبت نمی‌کند
                }

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

        $loose = $total($this->build('sleep_nightgown', '40', ['elastic_ratio' => 0.98]));
        $tight = $total($this->build('sleep_nightgown', '40', ['elastic_ratio' => 0.75]));

        $this->assertGreaterThan(0, $loose);
        $this->assertLessThan($loose, $tight, 'نسبت کمتر یعنی کش کوتاه‌تر.');
    }

    /* ---------------------------------------------------------------------
     |  دوختنی بودن
     * ------------------------------------------------------------------- */

    public function test_side_seams_of_front_and_back_walk_to_the_same_length(): void
    {
        foreach (['sleep_pajama', 'sleep_nightgown', 'sleep_robe'] as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);

                $walk = PieceOps::walk(
                    $this->part($pieces, 'front_bodice'),
                    'side',
                    $this->part($pieces, 'back_bodice'),
                    'side',
                    ['tolerance' => 0.15],
                );

                $this->assertTrue($walk['matched'], "{$key}|{$size}: درز پهلوی جلو و پشت هم‌اندازه نیستند.");
            }
        }
    }

    public function test_a_sleeve_is_walked_onto_the_armhole_of_this_very_draft(): void
    {
        foreach (['sleep_pajama', 'sleep_robe'] as $key) {
            foreach (Bodies::bodies() as $size) {
                $pieces = $this->build($key, $size);
                $sleeves = $this->parts($pieces, ['sleeve']);

                $this->assertNotEmpty($sleeves, "{$key}|{$size} باید آستین داشته باشد.");

                $armhole = 0.0;

                foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $panel) {
                    $this->assertNotEmpty(
                        Geometry::edgesWithTag($panel, 'armhole'),
                        "{$key}|{$size}|{$panel['code']} بالاتنه است ولی حلقه آستین ندارد.",
                    );
                    $armhole += PieceOps::edgeLength($panel, 'armhole');
                }

                $cap = 0.0;

                foreach ($sleeves as $sleeve) {
                    $cap += PieceOps::edgeLength($sleeve, Geometry::edgesWithTag($sleeve, 'armhole'));
                }

                $ease = $cap - $armhole;

                $this->assertGreaterThan(-0.5, $ease, "{$key}|{$size}: سرآستین از حلقه کوتاه‌تر است و دوخته نمی‌شود.");
                $this->assertLessThan(
                    max(6.0, $armhole * 0.25),
                    $ease,
                    "{$key}|{$size}: آزادی سرآستین بیش از اندازه است.",
                );
            }
        }
    }

    public function test_the_pyjama_top_is_a_separate_garment_from_its_trousers(): void
    {
        $pieces = $this->build('sleep_pajama');

        $top = $this->parts($pieces, ['front_bodice', 'back_bodice']);
        $legs = $this->parts($pieces, ['front_leg', 'back_leg']);

        $this->assertCount(2, $top, 'پیژامه بالاتنهٔ جلو و پشت دارد.');
        $this->assertCount(2, $legs, 'پایین پیژامه یک شلوار واقعی است، با پاچهٔ جلو و پشت.');

        // لبهٔ پایین بالاتنه دم لباس است نه خط کمر؛ به شلوار دوخته نمی‌شود
        foreach ($top as $panel) {
            $this->assertEmpty(
                Geometry::edgesWithTag($panel, 'waist'),
                "«{$panel['code']}» لبهٔ کمر دارد، ولی بالاتنهٔ پیژامه به شلوار دوخته نمی‌شود.",
            );
            $this->assertNotEmpty(Geometry::edgesWithTag($panel, 'hem'));
        }

        $this->assertNotEmpty($this->notions($pieces, 'button'), 'جلوی پیژامه با دکمه باز می‌شود.');
        $this->assertNotEmpty($this->notions($pieces, 'eyelet'), 'بند کمر شلوار از جادکمه‌ای بیرون می‌آید.');
        $this->assertNotNull($this->part($pieces, 'collar'), 'پیژامه یقهٔ ایستاده دارد.');

        $front = $this->part($pieces, 'front_bodice');
        $this->assertGreaterThanOrEqual(5, count($front['drills']), 'جای پنج دکمه باید علامت خورده باشد.');
        $this->assertGreaterThan(0.0, (float) $front['meta']['button_stand']);
        $this->assertSame(2, (int) $front['cut_quantity'], 'جلوی دکمه‌خور روی تای پارچه نمی‌رود.');
    }

    /* ---------------------------------------------------------------------
     |  شخصیت هر مدل
     * ------------------------------------------------------------------- */

    public function test_the_nightgown_flares_enough_to_walk_in(): void
    {
        foreach (Bodies::bodies() as $size) {
            $body = Bodies::body($size);
            $hem = 0.0;

            foreach ($this->parts($this->build('sleep_nightgown', $size), ['front_bodice', 'back_bodice']) as $panel) {
                $hem += PieceOps::edgeLength($panel, 'hem') * (empty($panel['on_fold']) ? 1 : 2);
            }

            $this->assertGreaterThan(
                (float) $body['hip'],
                $hem,
                "لباس خواب|{$size}: دم لباس باید دست‌کم از باسن رد شود، وگرنه پا را می‌بندد.",
            );
        }
    }

    public function test_a_wider_flare_gives_a_wider_hem(): void
    {
        $hemOf = function (float $flare): float {
            $total = 0.0;

            foreach ($this->parts($this->build('sleep_nightgown', '40', ['hem_flare' => $flare]), ['front_bodice', 'back_bodice']) as $panel) {
                $total += PieceOps::edgeLength($panel, 'hem') * (empty($panel['on_fold']) ? 1 : 2);
            }

            return $total;
        };

        $this->assertGreaterThan($hemOf(14), $hemOf(38), 'باز شدن بیشتر یعنی دم لباس گشادتر.');
    }

    public function test_the_robe_wraps_over_itself_and_ties_with_a_belt(): void
    {
        $pieces = $this->build('sleep_robe');
        $front = $this->part($pieces, 'front_bodice');

        $this->assertGreaterThanOrEqual(5.0, (float) $front['meta']['wrap_overlap'], 'روب باید هم‌پوشانی جلو داشته باشد.');
        $this->assertSame(2, (int) $front['cut_quantity'], 'جلوی روب روی تای پارچه نمی‌رود.');

        $this->assertNotNull($this->part($pieces, 'belt'), 'روب بند کمر دارد؛ هیچ بست دیگری ندارد.');
        $this->assertNotNull($this->part($pieces, 'loop'), 'بند باید از حلقهٔ روی درز پهلو رد شود.');
        $this->assertNotNull($this->part($pieces, 'placket'), 'نوار یک‌سرهٔ لبهٔ جلو و یقه لازم است.');
        $this->assertNotNull($this->part($pieces, 'pocket'), 'روب جیب دارد.');

        $this->assertEmpty($this->notions($pieces, 'button'), 'روب دکمه ندارد.');
        $this->assertEmpty($this->notions($pieces, 'zip'), 'روب زیپ ندارد.');

        // هم‌پوشانی رویهم آمدن پارچه است، نه گشادی لباس؛ در دور سینه حساب نمی‌شود
        $this->assertGreaterThan(
            (float) $front['meta']['wrap_overlap'],
            Geometry::width($front['outline']) - (float) $front['meta']['wrap_overlap'],
            'پهنای پنل باید بیشتر از هم‌پوشانی باشد.',
        );

        $belt = $this->part($pieces, 'belt');
        $this->assertGreaterThan(
            (float) Bodies::body('40')['waist'] * 1.5,
            Geometry::width($belt['outline']),
            'بند روب با گره بسته می‌شود و باید بیش از یک و نیم دور کمر باشد.',
        );
    }

    public function test_a_wider_overlap_makes_a_wider_front_panel(): void
    {
        $widthOf = function (float $overlap): float {
            foreach ($this->build('sleep_robe', '40', ['overlap' => $overlap]) as $piece) {
                if (($piece['meta']['part'] ?? '') === 'front_bodice') {
                    return Geometry::width($piece['outline']);
                }
            }

            return 0.0;
        };

        $this->assertEqualsWithDelta($widthOf(20) - $widthOf(8), 12.0, 0.2, 'هر سانتی‌متر هم‌پوشانی یک سانتی‌متر به پهنای پنل جلو اضافه می‌کند.');
    }

    public function test_sleep_shorts_are_real_shorts_with_an_elastic_waist_and_a_cord(): void
    {
        $pieces = $this->build('sleep_shorts');

        $this->assertCount(2, $this->parts($pieces, ['front_leg', 'back_leg']), 'شلوارک خواب پاچهٔ جلو و پشت دارد.');
        $this->assertNotNull($this->part($pieces, 'waistband'), 'کمر شلوارک خواب نوار کش دارد.');
        $this->assertNotEmpty($this->notions($pieces, 'elastic'), 'کمر کش می‌خواهد.');
        $this->assertNotEmpty($this->notions($pieces, 'eyelet'), 'بند از جادکمه‌ای بیرون می‌آید.');

        $cord = array_filter($pieces, fn (array $p) => str_contains((string) ($p['code'] ?? ''), 'drawcord'));
        $this->assertNotEmpty($cord, 'کشِ تنها در خواب می‌چرخد؛ بند کمر هم لازم است.');

        foreach ($this->parts($pieces, ['front_leg', 'back_leg']) as $leg) {
            $this->assertSame('shorts_cycling', $leg['meta']['borrowed_from'] ?? null);
            $this->assertArrayHasKey('stretch_ratio', $leg['meta'], 'پاچهٔ جرسی باید ضریب کششش را اعلام کند.');
        }
    }

    public function test_a_longer_leg_gives_a_taller_short(): void
    {
        $heightOf = function (float $length): float {
            foreach ($this->build('sleep_shorts', '40', ['leg_length' => $length]) as $piece) {
                if (($piece['meta']['part'] ?? '') === 'front_leg') {
                    return Geometry::height($piece['outline']);
                }
            }

            return 0.0;
        };

        $this->assertGreaterThan($heightOf(8) + 8.0, $heightOf(26), 'قد پای بیشتر یعنی پاچهٔ بلندتر.');
    }
}
