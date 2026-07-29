<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاتالوگ لباس ساحلی.
 *
 * این خانواده دورگه است و همین، جای خطاست: سه مدلش با پارچه بافته و آزادی مثبت
 * بریده می‌شوند و یکی (راش‌گارد) با پارچه کشی و آزادی منفی. آزمون‌های زیر همین
 * مرز را می‌پایند — که هیچ‌کدام ادعای آن یکی را نکند — و کنارش دو چیز دیگر:
 *
 *   ساروَنگ باید صادق باشد: پهنای پارچه، هم‌پوشانی، اندازه گره و دور تمام‌شده
 *   باید نوشته شوند و با هم جور دربیایند؛ یک مستطیل بدون این چهار عدد، الگو
 *   نیست.
 *
 *   پیراهن ساحلی باید کمرش جور باشد: لبه کمر بالاتنه و لبه بالای دامنِ چین‌دار
 *   به هم دوخته می‌شوند، پس اندازه‌گیری‌شده باید برابر باشند و چین در
 *   meta.gathers ثبت شده باشد.
 */
class BeachCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * بدن‌های آزمون.
     *
     * Measurements::fromSize() فقط سایزهای جدولی را می‌شناسد و هر کلید ناشناخته
     * را بی‌صدا به سایز ۴۰ برمی‌گرداند؛ پس بدن‌های سفارشی صریح نوشته شده‌اند.
     *
     * @var array<string, array<string, float|int>>
     */
    protected const BESPOKE = [
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'سینه‌درشت' => ['height' => 168, 'bust' => 118, 'waist' => 70, 'hip' => 100, 'shoulder_width' => 34, 'arm_length' => 59],
        'بلندقد' => ['height' => 195, 'bust' => 84, 'waist' => 66, 'hip' => 90, 'shoulder_width' => 44, 'arm_length' => 72],
        'کوتاه و درشت' => ['height' => 148, 'bust' => 124, 'waist' => 118, 'hip' => 126, 'shoulder_width' => 40, 'arm_length' => 50],
    ];

    /** پنج بدنی که هر مدل این خانواده باید رویشان بایستد. */
    protected const BODIES = ['34', '40', '48', 'کودک', 'سینه‌درشت'];

    /** کلید ⇒ نام فارسی. */
    protected const MODELS = [
        'beach_cover_kaftan' => 'کافتان ساحلی',
        'beach_sarong' => 'ساروَنگ (لُنگ ساحلی)',
        'beach_dress' => 'پیراهن ساحلی',
        'beach_rash_guard' => 'راش‌گارد',
    ];

    /** @return array<string, float> */
    protected function body(string $name): array
    {
        if (isset(static::BESPOKE[$name])) {
            return Measurements::complete(static::BESPOKE[$name]);
        }

        $this->assertArrayHasKey($name, Measurements::SIZE_TABLE, "بدن «{$name}» نه سایز جدولی است نه بدن سفارشی آزمون.");

        return Measurements::complete(Measurements::SIZE_TABLE[$name]);
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

    /** @return array<int, array<string, mixed>> */
    protected function parts(array $pieces, array $wanted): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => in_array((string) ($piece['meta']['part'] ?? ''), $wanted, true),
        ));
    }

    protected function piece(array $pieces, string $code): ?array
    {
        foreach ($pieces as $piece) {
            if (($piece['code'] ?? '') === $code) {
                return $piece;
            }
        }

        return null;
    }

    /** اندازه دور تمام‌شده روی یک خط نشانه. */
    protected function girth(array $pieces, string $line): float
    {
        $total = 0.0;

        foreach ($this->parts($pieces, ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back']) as $piece) {
            foreach ($piece['markers'] ?? [] as $marker) {
                if (($marker['key'] ?? '') !== $line) {
                    continue;
                }

                $width = abs(((float) $marker['to']['x']) - ((float) $marker['from']['x']));
                $total += $width * (int) $piece['cut_quantity'] * (empty($piece['on_fold']) ? 1 : 2);
            }
        }

        return round($total, 2);
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

    /* ---------------------------------------------------------------------
     |  فهرست و درستی پایه
     * ------------------------------------------------------------------- */

    public function test_the_family_is_registered_with_all_four_models(): void
    {
        $this->assertArrayHasKey('beach', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('beach');

        foreach (static::MODELS as $key => $label) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در گروه لباس ساحلی نیست.");
            $this->assertSame($label, $group[$key], "نام فارسی «{$key}» عوض شده است.");
        }
    }

    public function test_every_model_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_keys(static::MODELS) as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);

                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $where = "{$key}|{$size}|{$piece['code']}";
                    $outline = array_values($piece['outline'] ?? []);

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$where} مسیر ندارد.");
                    $this->assertFalse(
                        Geometry::selfIntersects($outline),
                        "{$where} مسیرش خودش را قطع می‌کند.",
                    );
                    $this->assertCount(
                        count($outline),
                        $piece['meta']['edges'] ?? [],
                        "{$where} برای هر لبه یک برچسب ندارد.",
                    );
                    $this->assertGreaterThan(1.0, Geometry::area($outline), "{$where} مساحت ندارد.");
                }
            }
        }
    }

    public function test_front_and_back_side_seams_are_the_same_length(): void
    {
        foreach (['beach_cover_kaftan', 'beach_dress', 'beach_rash_guard'] as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);
                $front = $this->parts($pieces, ['front_bodice']);
                $back = $this->parts($pieces, ['back_bodice']);

                $this->assertCount(1, $front, "{$key}|{$size} یک تنه جلو ندارد.");
                $this->assertCount(1, $back, "{$key}|{$size} یک تنه پشت ندارد.");

                $walk = PieceOps::walk($front[0], 'side', $back[0], 'side', ['tolerance' => 0.15]);

                $this->assertTrue(
                    $walk['matched'],
                    sprintf(
                        '%s|%s درز پهلوی جلو %.2f و پشت %.2f است.',
                        $key, $size, $walk['a']['seam'], $walk['b']['seam'],
                    ),
                );
            }
        }
    }

    public function test_the_rash_guard_sleeve_matches_its_armhole(): void
    {
        foreach (static::BODIES as $size) {
            $pieces = $this->build('beach_rash_guard', $size);
            $sleeves = $this->parts($pieces, ['sleeve']);

            $this->assertCount(1, $sleeves, "راش‌گارد|{$size} آستین ندارد.");

            $armhole = 0.0;

            foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $panel) {
                $armhole += PieceOps::edgeLength($panel, 'armhole');
            }

            $cap = PieceOps::edgeLength($sleeves[0], Geometry::edgesWithTag($sleeves[0], 'armhole'));

            $this->assertGreaterThan($armhole - 0.5, $cap, "راش‌گارد|{$size} سرآستین از حلقه کوتاه‌تر است.");
            $this->assertLessThan(
                $armhole + max(6.0, $armhole * 0.25),
                $cap,
                "راش‌گارد|{$size} سرآستین بیش از اندازه بلند است.",
            );
        }
    }

    /* ---------------------------------------------------------------------
     |  مرزِ آزادی مثبت و منفی
     * ------------------------------------------------------------------- */

    public function test_only_the_rash_guard_claims_negative_ease(): void
    {
        foreach (['beach_cover_kaftan', 'beach_sarong', 'beach_dress'] as $key) {
            foreach ($this->build($key) as $piece) {
                $this->assertArrayNotHasKey(
                    'stretch_ratio',
                    $piece['meta'],
                    "«{$key}» با پارچه بافته بریده می‌شود؛ نباید ادعا کند کوچک‌تر از بدن است.",
                );
            }
        }

        $declared = false;

        foreach ($this->build('beach_rash_guard') as $piece) {
            if (($piece['meta']['girth_role'] ?? '') === 'shell' && isset($piece['meta']['stretch_ratio'])) {
                $declared = true;
                $this->assertLessThan(1.0, (float) $piece['meta']['stretch_ratio']);
            }
        }

        $this->assertTrue($declared, 'راش‌گارد باید بگوید با چه ضریب کشسانی بریده شده.');
    }

    public function test_the_rash_guard_is_cut_smaller_than_the_body(): void
    {
        foreach (static::BODIES as $size) {
            $body = $this->body($size);
            $pieces = $this->build('beach_rash_guard', $size, ['stretch' => 0.85]);

            $bust = $this->girth($pieces, 'bust');

            $this->assertLessThan(
                (float) $body['bust'],
                $bust,
                "راش‌گارد|{$size} باید کوچک‌تر از دور سینه بدن بریده شود.",
            );
            $this->assertEqualsWithDelta(
                ((float) $body['bust']) * 0.85,
                $bust,
                0.5,
                "راش‌گارد|{$size} باید دقیقاً به همان نسبتی که اعلام کرده کوچک شود.",
            );
        }
    }

    public function test_a_stretchier_fabric_gives_a_smaller_rash_guard(): void
    {
        $frontOf = function (array $pieces): float {
            return Geometry::width($this->parts($pieces, ['front_bodice'])[0]['outline']);
        };

        $loose = $this->build('beach_rash_guard', '40', ['stretch' => 0.98]);
        $tight = $this->build('beach_rash_guard', '40', ['stretch' => 0.75]);

        $this->assertGreaterThan(0, $frontOf($loose));
        $this->assertLessThan($frontOf($loose), $frontOf($tight), 'پارچه پرکشش‌تر یعنی الگوی کوچک‌تر.');
    }

    public function test_the_rash_guard_open_edges_ask_for_elastic_and_a_short_zip(): void
    {
        $pieces = $this->build('beach_rash_guard');
        $elastics = $this->notions($pieces, 'elastic');

        $this->assertNotEmpty($elastics, 'دم لباس و مچ راش‌گارد کش می‌خواهند؛ بدون کش در آب بالا می‌رود.');

        foreach ($elastics as $elastic) {
            $this->assertGreaterThan(0.0, (float) $elastic['length'], 'بلندی کش باید حساب شده باشد.');
        }

        $zips = $this->notions($pieces, 'zip');
        $this->assertCount(1, $zips, 'راش‌گارد یک زیپ کوتاه دارد.');
        $this->assertLessThanOrEqual(30.0, (float) $zips[0]['length'], 'زیپ راش‌گارد کوتاه است، نه سرتاسری.');

        $this->assertNotNull($this->piece($pieces, 'rash-guard-collar-stand'), 'یقه ایستاده گردن را از آفتاب نگه می‌دارد.');
        $this->assertNotNull($this->piece($pieces, 'rash-guard-zip-stay'), 'زیر زیپ روی پارچه کشی نوار محکم‌کننده می‌خواهد.');
    }

    public function test_a_tighter_elastic_ratio_asks_for_shorter_elastic(): void
    {
        $lengthOf = fn (array $pieces) => array_sum(array_map(
            fn (array $notion) => (float) $notion['length'],
            $this->notions($pieces, 'elastic'),
        ));

        $loose = $this->build('beach_rash_guard', '40', ['elastic_ratio' => 0.99]);
        $tight = $this->build('beach_rash_guard', '40', ['elastic_ratio' => 0.78]);

        $this->assertGreaterThan(0, $lengthOf($loose));
        $this->assertLessThan($lengthOf($loose), $lengthOf($tight), 'نسبت کمتر یعنی کش کوتاه‌تر.');
    }

    public function test_the_woven_beach_models_stay_a_sane_distance_from_the_body(): void
    {
        foreach (['beach_cover_kaftan', 'beach_dress'] as $key) {
            foreach (static::BODIES as $size) {
                $body = $this->body($size);
                $bust = $this->girth($this->build($key, $size), 'bust');

                $this->assertGreaterThan((float) $body['bust'], $bust, "{$key}|{$size} از بدن تنگ‌تر است.");
                $this->assertLessThanOrEqual(
                    (float) $body['bust'] + 30.0,
                    $bust,
                    "{$key}|{$size} بیش از سی سانتی‌متر از بدن بازتر است.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  ساروَنگ
     * ------------------------------------------------------------------- */

    public function test_the_sarong_declares_its_wrap_overlap_and_knot(): void
    {
        foreach (static::BODIES as $size) {
            $sarong = $this->piece($this->build('beach_sarong', $size), 'sarong');

            $this->assertNotNull($sarong, "ساروَنگ|{$size} ساخته نشد.");

            $meta = $sarong['meta'];

            foreach (['finished_waist', 'overlap', 'knot', 'fabric_width', 'hem_width'] as $key) {
                $this->assertArrayHasKey($key, $meta, "ساروَنگ باید «{$key}» را اعلام کند.");
            }

            $body = $this->body($size);

            $this->assertEqualsWithDelta(
                ((float) $body['hip']) + 4,
                (float) $meta['finished_waist'],
                0.05,
                "ساروَنگ|{$size} دور تمام‌شده باید دور باسن به‌علاوه آزادی باشد.",
            );

            // پهنای پارچه = دور پیچش + هم‌پوشانی + دو سرِ گره؛ نه یک عدد گرد شده
            $this->assertEqualsWithDelta(
                ((float) $meta['finished_waist']) + ((float) $meta['overlap']) + (2 * ((float) $meta['knot'])),
                (float) $meta['fabric_width'],
                0.05,
                "ساروَنگ|{$size} پهنای اعلام‌شده با اجزایش جور نیست.",
            );

            $this->assertEqualsWithDelta(
                (float) $meta['fabric_width'],
                Geometry::width($sarong['outline']) - ((float) $meta['hem_width'] - (float) $meta['fabric_width']),
                0.05,
                "ساروَنگ|{$size} لبه بالای الگو با پهنای اعلام‌شده جور نیست.",
            );

            $this->assertGreaterThan(
                (float) $meta['fabric_width'],
                (float) $meta['hem_width'],
                'لبه پایین ساروَنگ باید از لبه بالا بازتر باشد.',
            );
        }
    }

    public function test_sewn_ties_replace_the_fabric_knot(): void
    {
        $knotted = $this->piece($this->build('beach_sarong'), 'sarong');
        $tied = $this->build('beach_sarong', '40', ['sewn_ties' => true]);
        $body = $this->piece($tied, 'sarong');

        $this->assertGreaterThan(0.0, (float) $knotted['meta']['knot'], 'گره پارچه‌ای باید پارچه بخورد.');
        $this->assertSame(0.0, (float) $body['meta']['knot'], 'با بند دوخته‌شده، پارچه‌ای در گره خورده نمی‌شود.');
        $this->assertLessThan(
            (float) $knotted['meta']['fabric_width'],
            (float) $body['meta']['fabric_width'],
            'بندِ دوخته‌شده باید پارچه کمتری بخواهد.',
        );
        $this->assertNotNull($this->piece($tied, 'sarong-tie'), 'بندها ساخته نشده‌اند.');
    }

    public function test_the_sarong_is_a_single_seamless_piece(): void
    {
        $pieces = $this->build('beach_sarong');
        $sarong = $this->piece($pieces, 'sarong');

        $this->assertCount(1, $pieces, 'ساروَنگ بدون بند، فقط یک قطعه است.');
        $this->assertCount(4, $sarong['outline'], 'ساروَنگ یک ذوزنقه چهارخطی است.');
        $this->assertSame(1, $sarong['cut_quantity']);
        $this->assertFalse((bool) $sarong['on_fold']);
    }

    /* ---------------------------------------------------------------------
     |  پیراهن ساحلی و کافتان
     * ------------------------------------------------------------------- */

    public function test_the_beach_dress_waist_seams_meet(): void
    {
        foreach (static::BODIES as $size) {
            $pieces = $this->build('beach_dress', $size);

            $upper = 0.0;

            foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
                $edges = Geometry::edgesWithTag($piece, 'waist');
                $this->assertNotEmpty($edges, 'بالاتنه پیراهن ساحلی روی خط کمر تمام می‌شود.');
                $upper += PieceOps::seamLength($piece, $edges)
                    * (int) $piece['cut_quantity'] * (empty($piece['on_fold']) ? 1 : 2);
            }

            $lower = 0.0;

            foreach ($this->parts($pieces, ['skirt_front', 'skirt_back']) as $piece) {
                $edges = Geometry::edgesWithTag($piece, 'waist');
                $lower += PieceOps::seamLength($piece, $edges)
                    * (int) $piece['cut_quantity'] * (empty($piece['on_fold']) ? 1 : 2);
            }

            $this->assertGreaterThan(0.0, $upper);
            $this->assertEqualsWithDelta(
                $upper,
                $lower,
                1.0,
                "پیراهن ساحلی|{$size} کمر بالاتنه و کمر دامن به هم دوخته می‌شوند و باید هم‌اندازه باشند.",
            );
        }
    }

    public function test_the_beach_dress_skirt_records_its_gathers(): void
    {
        foreach ([1.4, 2.4] as $ratio) {
            $skirt = $this->parts($this->build('beach_dress', '40', ['gather_ratio' => $ratio]), ['skirt_front'])[0];
            $gathers = $skirt['meta']['gathers'] ?? [];

            $this->assertNotEmpty($gathers, 'چین دامن در meta.gathers ثبت نشده است.');
            $this->assertEqualsWithDelta($ratio, (float) $gathers[0]['ratio'], 0.05, 'نسبت چین ثبت‌شده با پارامتر جور نیست.');
        }
    }

    public function test_the_beach_dress_closes_at_the_centre_back(): void
    {
        $pieces = $this->build('beach_dress');
        $back = $this->parts($pieces, ['back_bodice'])[0];

        $this->assertFalse((bool) $back['on_fold'], 'پشتِ پیراهن کمرگرفته باید درز داشته باشد تا زیپ بنشیند.');
        $this->assertSame(2, (int) $back['cut_quantity']);

        $zips = $this->notions($pieces, 'zip');
        $this->assertCount(1, $zips, 'یک زیپ مرکز پشت لازم است.');
        $this->assertGreaterThan(30.0, (float) $zips[0]['length'], 'زیپ باید از باسن رد شود، وگرنه پیراهن پوشیده نمی‌شود.');
    }

    public function test_the_beach_kaftan_is_short_wide_and_slit(): void
    {
        $pieces = $this->build('beach_cover_kaftan');
        $front = $this->parts($pieces, ['front_bodice'])[0];

        $this->assertTrue((bool) ($front['meta']['one_piece_sleeve'] ?? false), 'کافتان ساحلی تنه و آستین یک‌سره دارد.');
        $this->assertGreaterThan(0.0, (float) ($front['meta']['neck_slit'] ?? 0), 'چاک یقه علامت نخورده است.');
        $this->assertGreaterThan(0.0, (float) ($front['meta']['vent'] ?? 0), 'چاک پهلو علامت نخورده است.');

        // کوتاه‌تر از کافتانِ بلندِ کاتالوگ: تا میان ران، نه تا میان ساق
        $kaftan = GeneratorRegistry::make('kaftan');
        $long = $kaftan->generate($this->body('40'), [], $kaftan->defaultParams());
        $longFront = $this->parts($long, ['front_bodice'])[0];

        $this->assertLessThan(
            Geometry::height($longFront['outline']) - 20,
            Geometry::height($front['outline']),
            'کافتان ساحلی باید آشکارا کوتاه‌تر از کافتان بلند کاتالوگ باشد.',
        );
    }

    public function test_a_wider_hem_flare_really_opens_the_kaftan(): void
    {
        // پهنای کادر قطعه را آستین یک‌سره تعیین می‌کند، نه لبه پایین؛ پس خودِ
        // لبه پایین اندازه گرفته می‌شود.
        $hemOf = fn (array $pieces) => PieceOps::edgeLength($this->parts($pieces, ['front_bodice'])[0], 'hem');

        $narrow = $this->build('beach_cover_kaftan', '40', ['hem_flare' => 4]);
        $wide = $this->build('beach_cover_kaftan', '40', ['hem_flare' => 30]);

        $this->assertGreaterThan($hemOf($narrow) + 20, $hemOf($wide), 'بازتر شدن لبه پایین باید پارچه بیشتری بخواهد.');
    }
}
