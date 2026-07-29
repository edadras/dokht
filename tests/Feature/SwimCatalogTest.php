<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاتالوگ مایو.
 *
 * مایو سه چیز دارد که هیچ لباس دیگری در این کاتالوگ ندارد، و هر سه جای خطاست:
 * الگو باید کوچک‌تر از بدن باشد، هر لبهٔ باز باید کش داشته باشد، و فاق باید
 * آستر جدا داشته باشد. این آزمون‌ها همان سه را می‌پایند.
 */
class SwimCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, string $size = '40', array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Measurements::complete(Measurements::fromSize($size)),
            [],
            array_merge($generator->defaultParams(), $params),
        );
    }

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

    public function test_the_family_is_registered_with_both_women_and_men_models(): void
    {
        $this->assertArrayHasKey('swim', GeneratorRegistry::GROUPS);

        $swim = GeneratorRegistry::group('swim');

        $this->assertGreaterThanOrEqual(12, count($swim), 'کاتالوگ مایو باید دست‌کم دوازده مدل داشته باشد.');

        foreach ([
            // زنانه
            'swim_onepiece', 'swim_racerback', 'swim_skirted', 'swim_tankini',
            'swim_bikini_triangle', 'swim_bikini_bandeau', 'swim_sport_bra',
            'swim_briefs', 'swim_burkini',
            // مردانه
            'swim_trunks', 'swim_boardshorts', 'swim_jammer',
        ] as $key) {
            $this->assertArrayHasKey($key, $swim, "«{$key}» در کاتالوگ نیست.");
        }
    }

    public function test_every_swimsuit_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_keys(GeneratorRegistry::group('swim')) as $key) {
            foreach (['34', '40', '48', 'کودک', 'بلندقد'] as $size) {
                $pieces = $this->build($key, $size);

                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $outline = $piece['outline'] ?? [];

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$key}|{$size}|{$piece['name']} مسیر ندارد.");
                    $this->assertFalse(
                        Geometry::selfIntersects($outline),
                        "{$key}|{$size}|{$piece['name']} مسیرش خودش را قطع می‌کند.",
                    );
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  آزادی منفی
     * ------------------------------------------------------------------- */

    public function test_a_swimsuit_is_cut_smaller_than_the_body(): void
    {
        $body = Measurements::complete(Measurements::fromSize('40'));

        foreach (['swim_onepiece', 'swim_racerback', 'swim_tankini'] as $key) {
            $pieces = $this->build($key, '40', ['stretch' => 0.8]);
            $width = 0.0;

            foreach ($pieces as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                    continue;
                }

                $width += Geometry::width($piece['outline']) * (empty($piece['on_fold']) ? 1 : 2);
            }

            $this->assertGreaterThan(0, $width);
            $this->assertLessThan(
                (float) $body['bust'],
                $width,
                "«{$key}» باید کوچک‌تر از دور سینهٔ بدن بریده شود؛ مایوِ اندازهٔ بدن در آب می‌افتد.",
            );
        }
    }

    public function test_a_stretchier_fabric_gives_a_smaller_pattern(): void
    {
        $wide = $this->build('swim_onepiece', '40', ['stretch' => 0.95]);
        $tight = $this->build('swim_onepiece', '40', ['stretch' => 0.7]);

        $frontOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'front_bodice') {
                    return Geometry::width($piece['outline']);
                }
            }

            return 0.0;
        };

        $this->assertGreaterThan(0, $frontOf($wide));
        $this->assertLessThan($frontOf($wide), $frontOf($tight), 'پارچهٔ پرکشش‌تر یعنی الگوی کوچک‌تر.');
    }

    public function test_every_swim_shell_declares_the_stretch_it_was_cut_with(): void
    {
        // ترانکس و بردشورت عمداً در این فهرست نیستند: هر دو با آزادی مثبت
        // بریده می‌شوند و خودشان هم همین را اعلام می‌کنند
        foreach (['swim_onepiece', 'swim_briefs', 'swim_tankini', 'swim_jammer'] as $key) {
            $declared = false;

            foreach ($this->build($key) as $piece) {
                if (($piece['meta']['girth_role'] ?? '') === 'shell' && isset($piece['meta']['stretch_ratio'])) {
                    $declared = true;
                    $this->assertLessThan(1.0, (float) $piece['meta']['stretch_ratio']);
                }
            }

            $this->assertTrue($declared, "«{$key}» باید بگوید با چه ضریب کشسانی بریده شده.");
        }
    }

    public function test_the_burkini_is_the_one_that_is_not_cut_smaller(): void
    {
        foreach ($this->build('swim_burkini') as $piece) {
            $this->assertArrayNotHasKey(
                'stretch_ratio',
                $piece['meta'],
                'بورکینی گشاد است؛ نباید ادعا کند کوچک‌تر از بدن بریده شده.',
            );
        }
    }

    /* ---------------------------------------------------------------------
     |  کش و آستر
     * ------------------------------------------------------------------- */

    public function test_every_open_edge_asks_for_elastic(): void
    {
        foreach (['swim_onepiece', 'swim_briefs', 'swim_bikini_triangle', 'swim_tankini'] as $key) {
            $elastics = $this->notions($this->build($key), 'elastic');

            $this->assertNotEmpty($elastics, "«{$key}» باید کش بخواهد؛ بدون کش هیچ درزی لبه را نگه نمی‌دارد.");

            foreach ($elastics as $elastic) {
                $this->assertGreaterThan(0, (float) ($elastic['length'] ?? 0), 'بلندی کش باید حساب شده باشد.');
            }
        }
    }

    public function test_elastic_is_always_cut_shorter_than_the_edge_it_holds(): void
    {
        $loose = $this->build('swim_briefs', '40', ['elastic_ratio' => 0.98]);
        $tight = $this->build('swim_briefs', '40', ['elastic_ratio' => 0.75]);

        $lengthOf = fn (array $pieces) => array_sum(array_map(
            fn (array $n) => (float) $n['length'],
            $this->notions($pieces, 'elastic'),
        ));

        $this->assertGreaterThan(0, $lengthOf($loose));
        $this->assertLessThan($lengthOf($loose), $lengthOf($tight), 'نسبت کمتر یعنی کش کوتاه‌تر.');
    }

    public function test_every_bottom_carries_a_gusset(): void
    {
        foreach (['swim_briefs', 'swim_bikini_triangle', 'swim_bikini_bandeau', 'swim_tankini', 'swim_onepiece'] as $key) {
            $gusset = array_filter(
                $this->build($key),
                fn (array $piece) => ($piece['meta']['part'] ?? '') === 'gusset',
            );

            $this->assertNotEmpty($gusset, "«{$key}» باید نوار فاق داشته باشد؛ اختیاری نیست.");
        }
    }

    public function test_a_swim_bottom_is_not_mistaken_for_a_trouser_leg(): void
    {
        $parts = array_map(fn (array $p) => $p['meta']['part'] ?? '', $this->build('swim_briefs'));

        $this->assertContains('swim_bottom_front', $parts);
        $this->assertNotContains('front_leg', $parts, 'شورت مایو پاچهٔ شلوار نیست؛ نه درز داخل پا دارد نه فاق شلوار.');
    }

    /* ---------------------------------------------------------------------
     |  تفاوت‌های مدل‌ها
     * ------------------------------------------------------------------- */

    public function test_a_lower_rise_makes_a_shorter_bottom(): void
    {
        $high = $this->build('swim_briefs', '40', ['rise_drop' => 0]);
        $low = $this->build('swim_briefs', '40', ['rise_drop' => 14]);

        $heightOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'swim_bottom_front') {
                    return Geometry::height($piece['outline']);
                }
            }

            return 0.0;
        };

        $this->assertGreaterThan($heightOf($low), $heightOf($high), 'کمر پایین‌تر یعنی شورت کوتاه‌تر.');
    }

    public function test_less_coverage_makes_a_narrower_back(): void
    {
        $backOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'swim_bottom_back') {
                    return abs(Geometry::area($piece['outline']));
                }
            }

            return 0.0;
        };

        $full = $backOf($this->build('swim_briefs', '40', ['coverage' => 'full']));
        $cheeky = $backOf($this->build('swim_briefs', '40', ['coverage' => 'cheeky']));

        $this->assertGreaterThan($cheeky, $full, 'پوشش کامل باید پارچهٔ بیشتری از پوشش کم داشته باشد.');
    }

    public function test_mens_trunks_ask_for_both_elastic_and_a_drawcord(): void
    {
        $pieces = $this->build('swim_trunks');

        $this->assertNotEmpty($this->notions($pieces, 'elastic'), 'کمر ترانکس کش می‌خواهد.');
        $this->assertNotEmpty($this->notions($pieces, 'eyelet'), 'بند از جادکمه‌ای بیرون می‌آید.');

        $cord = array_filter($pieces, fn (array $p) => str_contains((string) ($p['code'] ?? ''), 'drawcord'));
        $this->assertNotEmpty($cord, 'ترانکس باید بند کمر داشته باشد؛ کش به‌تنهایی در آب کافی نیست.');
    }

    public function test_a_jammer_is_tighter_than_boardshorts(): void
    {
        $widthOf = function (array $pieces): float {
            $width = 0.0;

            foreach ($pieces as $piece) {
                if (str_contains((string) ($piece['meta']['part'] ?? ''), 'leg')) {
                    $width += Geometry::width($piece['outline']);
                }
            }

            return $width;
        };

        $this->assertLessThan(
            $widthOf($this->build('swim_boardshorts')),
            $widthOf($this->build('swim_jammer')),
            'جامر باید به تن بچسبد و بردشورت گشاد است.',
        );
    }

    public function test_a_triangle_cup_is_cut_on_the_bias(): void
    {
        $cup = null;

        foreach ($this->build('swim_bikini_triangle') as $piece) {
            if (($piece['meta']['part'] ?? '') === 'cup') {
                $cup = $piece;
            }
        }

        $this->assertNotNull($cup, 'بیکینی مثلثی باید فنجان داشته باشد.');
        $this->assertTrue((bool) ($cup['meta']['bias'] ?? false), 'مثلث باید روی اریب بریده شود.');
        $this->assertSame(4, (int) $cup['cut_quantity'], 'دو رو و دو آستر.');
    }
}
