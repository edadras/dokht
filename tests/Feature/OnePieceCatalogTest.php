<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاتالوگ لباس یک‌تکه و لباس کار.
 *
 * لباس یک‌تکه دو چیز دارد که هیچ لباس دیگری در کاتالوگ ندارد، و هر دو جای
 * خطاست:
 *
 *   خط کمر — بالاتنه و پاچه جدا درفت می‌شوند و باید روی همان خط دقیقاً
 *   هم‌اندازه باشند. یک سانتی‌متر اختلاف یعنی یکی از دو لبه کشیده دوخته
 *   می‌شود و خط کمر روی تن می‌پیچد.
 *
 *   رایز کل — فاصله گردن تا فاق. لباس دوتکه این اندازه را ندارد چون پیراهن روی
 *   شلوار می‌لغزد؛ سرهمی نمی‌لغزد و اگر رایزش کوتاه باشد پوشنده که می‌نشیند
 *   لباس از سرشانه بالا می‌کشد.
 *
 * این آزمون‌ها همان دو را می‌پایند، به‌علاوه چیزهایی که هر لباسی باید داشته
 * باشد: مسیر بسته، درز پهلوی جفت، و آستینی که در حلقه بنشیند.
 */
class OnePieceCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** کلیدهای این خانواده و اینکه کدامشان پاچه دارد. */
    protected const MODELS = [
        'one_playsuit' => true,
        'one_catsuit' => true,
        'one_boilersuit' => true,
        'one_coverall' => true,
        'one_smock' => false,
    ];

    /**
     * بدن‌های آزمون.
     *
     * عمداً آرایه صریح است: Measurements::fromSize() فقط سایز ۳۴ تا ۴۸ را
     * می‌شناسد و برای هر کلید ناشناخته بی‌صدا سایز ۴۰ را برمی‌گرداند. اگر
     * این‌جا نامِ بدن را به آن می‌دادیم، آزمون خیال می‌کرد بدن سفارشی را سنجیده
     * ولی دوباره همان سایز ۴۰ را سنجیده بود.
     *
     * @var array<string, array<string, float|int>>
     */
    protected const BESPOKE = [
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'سینه‌درشت' => ['height' => 168, 'bust' => 118, 'waist' => 70, 'hip' => 100, 'shoulder_width' => 34, 'arm_length' => 59],
        'بلندقد' => ['height' => 195, 'bust' => 84, 'waist' => 66, 'hip' => 90, 'shoulder_width' => 44, 'arm_length' => 72],
    ];

    /** پنج بدنی که هر مدل باید رویشان بایستد. */
    protected const BODIES = ['34', '40', '48', 'کودک', 'بلندقد'];

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

    /**
     * قطعه‌هایی با این نقش‌ها.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parts(array $pieces, array $parts): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => in_array((string) ($piece['meta']['part'] ?? ''), $parts, true),
        ));
    }

    /** دور کمر تمام‌شده‌ای که این دسته قطعه‌ها می‌سازند. */
    protected function waistGirth(array $pieces, array $parts): float
    {
        $total = 0.0;

        foreach ($this->parts($pieces, $parts) as $piece) {
            $edges = Geometry::edgesWithTag($piece, 'waist');

            if ($edges === []) {
                continue;
            }

            $total += PieceOps::seamLength($piece, $edges)
                * (int) ($piece['cut_quantity'] ?? 1)
                * (empty($piece['on_fold']) ? 1 : 2);
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
     |  فهرست و سلامت هندسی
     * ------------------------------------------------------------------- */

    public function test_the_family_is_registered_with_all_five_models(): void
    {
        $this->assertArrayHasKey('onepiece', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('onepiece');

        foreach (array_keys(static::MODELS) as $key) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در گروه لباس یک‌تکه نیست.");
            $this->assertNotSame('', GeneratorRegistry::make($key)->label());
        }
    }

    public function test_every_model_builds_a_clean_closed_pattern_on_five_bodies(): void
    {
        $allowed = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'strap', 'default'];

        foreach (array_keys(static::MODELS) as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);

                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $where = "{$key}|{$size}|{$piece['code']}";
                    $outline = array_values($piece['outline'] ?? []);

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$where} مسیر ندارد.");
                    $this->assertFalse(Geometry::selfIntersects($outline), "{$where} مسیرش خودش را قطع می‌کند.");

                    $tags = $piece['meta']['edges'] ?? null;
                    $this->assertIsArray($tags, "{$where} برچسب لبه ندارد.");
                    $this->assertCount(count($outline), $tags, "{$where} تعداد برچسب لبه با تعداد لبه نمی‌خواند.");

                    foreach ($tags as $tag) {
                        $this->assertContains($tag, $allowed, "{$where} برچسب لبه ناشناخته دارد.");
                    }

                    $this->assertArrayHasKey('fold_edges', $piece['meta'], "{$where} meta.fold_edges ندارد.");
                    $this->assertArrayHasKey('girth_role', $piece['meta'], "{$where} meta.girth_role ندارد.");
                    $this->assertNotEmpty($piece['grainline'] ?? null, "{$where} راستای پارچه ندارد.");
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  خط کمر
     * ------------------------------------------------------------------- */

    public function test_the_bodice_waist_and_the_leg_waist_are_the_same_length(): void
    {
        foreach (array_keys(array_filter(static::MODELS)) as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);

                $upper = $this->waistGirth($pieces, ['front_bodice', 'back_bodice']);
                $lower = $this->waistGirth($pieces, ['front_leg', 'back_leg']);

                $this->assertGreaterThan(0, $upper, "«{$key}» روی «{$size}» لبه کمر بالاتنه ندارد.");
                $this->assertGreaterThan(0, $lower, "«{$key}» روی «{$size}» لبه کمر پاچه ندارد.");

                $this->assertLessThanOrEqual(
                    1.0,
                    abs($upper - $lower),
                    sprintf(
                        '%s|%s کمر بالاتنه %.2f و کمر پاچه %.2f است؛ این دو به هم دوخته می‌شوند.',
                        $key, $size, $upper, $lower,
                    ),
                );
            }
        }
    }

    public function test_no_one_piece_hangs_the_button_stand_on_the_waist_seam(): void
    {
        // اضافه جای دکمه روی خودِ تنه، لبه کمر بالاتنه را از لبه کمر پاچه بلندتر
        // می‌کند؛ پس این خانواده بست جلو را یا روی درز مرکزی می‌گذارد یا روی
        // پاتلت جدا. این آزمون همان قاعده را روی خروجی می‌سنجد.
        foreach (['one_playsuit', 'one_boilersuit', 'one_coverall'] as $key) {
            $pieces = $this->build($key);
            $front = $this->parts($pieces, ['front_bodice'])[0] ?? null;

            $this->assertNotNull($front, "«{$key}» تنه جلو ندارد.");
            $this->assertLessThanOrEqual(
                0.01,
                (float) ($front['meta']['button_stand'] ?? 0),
                "«{$key}» اضافه جای دکمه را روی تنه گذاشته و خط کمرش دیگر جفت نیست.",
            );
            $this->assertFalse((bool) $front['on_fold'], "«{$key}» تنه جلو باید درز مرکزی داشته باشد تا بست رویش بنشیند.");
        }
    }

    public function test_front_and_back_side_seams_walk_to_the_same_length(): void
    {
        foreach (array_keys(static::MODELS) as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);
                $front = $this->parts($pieces, ['front_bodice'])[0] ?? null;
                $back = $this->parts($pieces, ['back_bodice'])[0] ?? null;

                $this->assertNotNull($front, "«{$key}» تنه جلو ندارد.");
                $this->assertNotNull($back, "«{$key}» تنه پشت ندارد.");

                $walk = PieceOps::walk($front, 'side', $back, 'side', ['tolerance' => 0.15]);

                $this->assertTrue($walk['matched'], sprintf(
                    '%s|%s درز پهلوی جلو %.2f و پشت %.2f است؛ اختلاف %.2f سانتی‌متر.',
                    $key, $size, $walk['a']['seam'], $walk['b']['seam'], $walk['difference'],
                ));
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  رایز کل
     * ------------------------------------------------------------------- */

    public function test_every_one_piece_declares_a_sittable_rise(): void
    {
        foreach (array_keys(array_filter(static::MODELS)) as $key) {
            foreach (static::BODIES as $size) {
                $rise = null;

                foreach ($this->build($key, $size) as $piece) {
                    $rise = $piece['meta']['rise'] ?? $rise;
                }

                $this->assertIsArray($rise, "«{$key}» روی «{$size}» رایز کل را اعلام نکرده است.");

                $this->assertGreaterThan(
                    $rise['bodice'],
                    $rise['total'],
                    "«{$key}» رایز کلش از قد بالاتنه‌اش بیشتر نیست؛ فاقی در کار نیست.",
                );

                $this->assertGreaterThanOrEqual(
                    2.0,
                    (float) $rise['ease'],
                    sprintf('%s|%s آزادی رایز %.1f سانتی‌متر است؛ با این عدد نمی‌شود نشست.', $key, $size, $rise['ease']),
                );

                $this->assertEqualsWithDelta(
                    (float) $rise['total'] - (float) $rise['ease'],
                    (float) $rise['body'],
                    0.05,
                    "«{$key}» عددهای رایزش با هم نمی‌خوانند.",
                );
            }
        }
    }

    public function test_more_rise_ease_really_makes_a_longer_body(): void
    {
        $riseOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (isset($piece['meta']['rise'])) {
                    return (float) $piece['meta']['rise']['total'];
                }
            }

            return 0.0;
        };

        foreach (['one_boilersuit', 'one_coverall', 'one_catsuit'] as $key) {
            $tight = $riseOf($this->build($key, '40', ['rise_ease' => 0, 'rise_extra' => 0]));
            $roomy = $riseOf($this->build($key, '40', ['rise_ease' => 6, 'rise_extra' => 5]));

            $this->assertGreaterThan(0, $tight);
            $this->assertGreaterThan(
                $tight + 9.0,
                $roomy,
                "«{$key}» آزادی رایز را اعلام می‌کند ولی روی الگو پیاده‌اش نمی‌کند.",
            );
        }
    }

    public function test_every_leg_carries_a_real_crotch_curve(): void
    {
        foreach (array_keys(array_filter(static::MODELS)) as $key) {
            foreach (static::BODIES as $size) {
                $body = $this->body($size);
                $pieces = $this->build($key, $size);
                $width = 0.0;

                foreach ($this->parts($pieces, ['front_leg', 'back_leg']) as $leg) {
                    // پیش‌آمدگی فاق همان پارچه‌ای است که از خط مرکز بیرون می‌زند
                    // و لای پا را می‌پوشاند؛ بدون آن، پاچه یک لوله است نه شلوار
                    $width += Geometry::width($leg['outline']) - (float) ($leg['meta']['panel_width'] ?? 0);
                    $this->assertGreaterThan(0, (float) ($leg['meta']['crotch_depth'] ?? 0));
                }

                $this->assertGreaterThan(
                    $body['hip'] / 8,
                    $width,
                    "«{$key}» روی «{$size}» پیش‌آمدگی فاق کافی ندارد؛ لای پا باز می‌ماند.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  آستین
     * ------------------------------------------------------------------- */

    public function test_the_sleeve_cap_matches_the_measured_armhole(): void
    {
        foreach (['one_catsuit', 'one_boilersuit', 'one_coverall', 'one_smock'] as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);
                $sleeves = $this->parts($pieces, ['sleeve']);

                $this->assertNotEmpty($sleeves, "«{$key}» باید آستین داشته باشد.");

                $armhole = 0.0;

                foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $panel) {
                    $armhole += PieceOps::edgeLength($panel, 'armhole');
                }

                $cap = 0.0;

                foreach ($sleeves as $sleeve) {
                    $cap += PieceOps::edgeLength($sleeve, Geometry::edgesWithTag($sleeve, 'armhole'));
                }

                $ease = $cap - $armhole;

                $this->assertGreaterThan(-0.5, $ease, "«{$key}»|{$size} سرآستین از حلقه کوتاه‌تر است.");
                $this->assertLessThan(
                    max(6.0, $armhole * 0.25),
                    $ease,
                    "«{$key}»|{$size} سرآستین بیش از اندازه از حلقه بلندتر است.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  تفاوت مدل‌ها
     * ------------------------------------------------------------------- */

    public function test_the_catsuit_is_cut_smaller_than_the_body(): void
    {
        $body = $this->body('40');
        $pieces = $this->build('one_catsuit', '40', ['stretch' => 0.85]);
        $declared = false;
        $width = 0.0;

        foreach ($pieces as $piece) {
            if (($piece['meta']['girth_role'] ?? '') === 'shell' && isset($piece['meta']['stretch_ratio'])) {
                $declared = true;
                $this->assertLessThan(1.0, (float) $piece['meta']['stretch_ratio']);
            }

            if (in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                $width += Geometry::width($piece['outline'])
                    * (int) $piece['cut_quantity']
                    * (empty($piece['on_fold']) ? 1 : 2);
            }
        }

        $this->assertTrue($declared, 'کت‌سوت باید بگوید با چه ضریب کشسانی بریده شده.');
        $this->assertLessThan(
            (float) $body['bust'],
            $width,
            'کت‌سوت باید کوچک‌تر از دور سینه بریده شود؛ کت‌سوت اندازه بدن روی تن می‌افتد.',
        );
    }

    public function test_a_stretchier_fabric_gives_a_smaller_catsuit(): void
    {
        $frontOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'front_bodice') {
                    return Geometry::width($piece['outline']);
                }
            }

            return 0.0;
        };

        $loose = $frontOf($this->build('one_catsuit', '40', ['stretch' => 0.98]));
        $tight = $frontOf($this->build('one_catsuit', '40', ['stretch' => 0.75]));

        $this->assertGreaterThan(0, $loose);
        $this->assertLessThan($loose, $tight, 'پارچه پرکشش‌تر یعنی الگوی کوچک‌تر.');
    }

    public function test_only_the_catsuit_claims_negative_ease(): void
    {
        foreach (['one_playsuit', 'one_boilersuit', 'one_coverall', 'one_smock'] as $key) {
            foreach ($this->build($key) as $piece) {
                $this->assertArrayNotHasKey(
                    'stretch_ratio',
                    $piece['meta'],
                    "«{$key}» از پارچه بافته دوخته می‌شود؛ نباید ادعا کند کوچک‌تر از بدن بریده شده.",
                );
            }
        }
    }

    public function test_the_coverall_is_roomier_than_the_boilersuit(): void
    {
        $bustOf = function (array $pieces): float {
            $total = 0.0;

            foreach ($pieces as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                    continue;
                }

                $total += (float) ($piece['meta']['girth']['bust'] ?? 0)
                    * (int) ($piece['meta']['girth_factor'] ?? 1);
            }

            return round($total, 2);
        };

        $boiler = $bustOf($this->build('one_boilersuit'));
        $coverall = $bustOf($this->build('one_coverall'));

        $this->assertGreaterThan(0, $boiler);
        $this->assertGreaterThan(
            $boiler,
            $coverall,
            'کاورال روی لباس دیگر پوشیده می‌شود، پس باید از بویلرسوت گشادتر باشد.',
        );

        $narrow = $bustOf($this->build('one_coverall', '40', ['over_ease' => 0]));
        $this->assertGreaterThan($narrow, $coverall, 'آزادی «روی لباس» باید واقعاً روی الگو بنشیند.');
    }

    public function test_the_boilersuit_closes_all_the_way_from_neck_to_crotch(): void
    {
        $zips = $this->notions($this->build('one_boilersuit', '40', ['closure' => 'zip']), 'zip');

        $this->assertNotEmpty($zips, 'بویلرسوت باید بست سرتاسری داشته باشد.');
        $this->assertGreaterThan(40.0, (float) $zips[0]['length'], 'زیپ سرتاسری باید از یقه تا پایین‌تر از کمر برسد.');

        $buttons = $this->notions($this->build('one_boilersuit', '40', ['closure' => 'button']), 'button');
        $this->assertNotEmpty($buttons, 'گزینه دکمه باید دکمه بشمارد.');

        $placket = array_filter(
            $this->build('one_boilersuit', '40', ['closure' => 'button']),
            fn (array $piece) => ($piece['meta']['part'] ?? '') === 'placket',
        );

        $this->assertNotEmpty($placket, 'بست دکمه‌ای باید پاتلت جدا داشته باشد، نه اضافه روی تنه.');
    }

    public function test_the_coverall_carries_work_pockets_with_flaps(): void
    {
        $pieces = $this->build('one_coverall');
        $pockets = $this->parts($pieces, ['pocket']);

        $this->assertGreaterThanOrEqual(4, count($pockets), 'کاورال باید جیب کار با درپوش داشته باشد.');
        $this->assertNotEmpty($this->notions($pieces, 'snap'), 'جیب کار و بست کاورال با دکمه فشاری بسته می‌شوند.');

        $gusseted = array_filter(
            $pockets,
            fn (array $piece) => ($piece['pleats'] ?? []) !== [],
        );

        $this->assertNotEmpty($gusseted, 'جیب ابزار باید پیلی حجم داشته باشد، وگرنه ابزار در آن جا نمی‌شود.');
    }

    public function test_the_playsuit_leg_is_a_short_not_a_trouser_leg(): void
    {
        $body = $this->body('40');
        $pieces = $this->build('one_playsuit', '40', ['short_length' => 12]);
        $legs = $this->parts($pieces, ['front_leg', 'back_leg']);

        $this->assertCount(2, $legs);

        foreach ($legs as $leg) {
            $this->assertEqualsWithDelta(12.0, (float) $leg['meta']['leg_length'], 0.01, 'بلندی پاچه همان چیزی نیست که خواسته شده.');
            $this->assertLessThan(
                $body['inseam'] / 2,
                Geometry::height($leg['outline']) - (float) $leg['meta']['crotch_depth'],
                'شورت پلی‌سوت نباید به بلندی پاچه شلوار دربیاید.',
            );
        }

        $longer = $this->build('one_playsuit', '40', ['short_length' => 26]);
        $this->assertGreaterThan(
            Geometry::height($legs[0]['outline']),
            Geometry::height($this->parts($longer, ['front_leg'])[0]['outline']),
            'بلندی پاچه باید روی الگو اثر بگذارد.',
        );
    }

    public function test_the_smock_has_no_legs_and_opens_down_the_front(): void
    {
        $pieces = $this->build('one_smock');

        $this->assertSame([], $this->parts($pieces, ['front_leg', 'back_leg']), 'روپوش کار پاچه ندارد.');

        $front = $this->parts($pieces, ['front_bodice'])[0];
        $this->assertFalse((bool) $front['on_fold'], 'روپوش جلوباز است.');
        $this->assertGreaterThan(0, (float) ($front['meta']['button_stand'] ?? 0), 'روپوش جلوباز اضافه جای دکمه دارد.');
        $this->assertGreaterThan(0, (float) ($front['meta']['vent'] ?? 0), 'روپوش بلند باید چاک پهلو داشته باشد.');

        $body = $this->body('40');
        $this->assertGreaterThan(
            $body['height'] * 0.25,
            Geometry::height($front['outline']),
            'روپوش کار باید تا نزدیکی زانو برسد.',
        );
    }
}
