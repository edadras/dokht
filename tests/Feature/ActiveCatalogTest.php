<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * کاتالوگ لباس ورزشی، و مدل‌های رگلان.
 *
 * دو چیز در این دو خانواده جای خطاست و هر دو این‌جا بسته شده‌اند:
 *
 *   الف) علامتِ آزادی. لایهٔ چسبان (سوتین، رکابی، تایت) باید *کوچک‌تر* از بدن
 *        بریده شود و لایهٔ رویی (گرمکن و شلوار گرمکن) *بزرگ‌تر*. اگر این دو جا
 *        عوض شوند، الگو ساخته می‌شود، آزمون هندسی هم سبز می‌ماند، و لباس اصلاً
 *        پوشیده نمی‌شود. پس هر دو نیمه صریح سنجیده می‌شوند.
 *
 *   ب) رگلان. رگلان فقط وقتی درست است که تنه از یقه تا زیر بغل بریده شده باشد و
 *        درزِ رگلانِ آستین دقیقاً هم‌اندازهٔ درزِ رگلانِ تنه باشد. آستین رگلانِ
 *        چسبانده به حلقهٔ نبریده، آستینی است که در حلقه نمی‌نشیند — همان اشتباهی
 *        که یک بار در این پروژه سرآستین ۲۶٫۶ را روبه‌روی حلقهٔ ۵۴٫۸ گذاشت.
 */
class ActiveCatalogTest extends TestCase
{
    /** برچسب‌های مجاز لبه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'strap', 'default'];

    /** رواداری هم‌اندازه بودن دو درزی که به هم دوخته می‌شوند (سانتی‌متر). */
    protected const SEAM_MATCH = 0.3;

    /**
     * پنج بدن آزمون.
     *
     * Measurements::fromSize فقط ۳۴ تا ۴۸ را می‌شناسد و کلید ناشناخته را بی‌صدا
     * به ۴۰ برمی‌گرداند؛ پس بدن‌های غیرجدولی صریح نوشته شده‌اند.
     */
    protected const BODIES = [
        '34' => null,
        '40' => null,
        '48' => null,
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'کوتاه و درشت' => ['height' => 148, 'bust' => 124, 'waist' => 118, 'hip' => 126, 'shoulder_width' => 40, 'arm_length' => 50],
    ];

    /** کلیدهای این دسته. */
    protected const ACTIVE = [
        'active_sports_bra', 'active_track_jacket', 'active_track_pants', 'active_tights', 'active_tank',
    ];

    /** مدل‌های رگلان (در گروه پیراهن‌اند، ولی خطایشان از همین جنس است). */
    protected const RAGLAN = ['tshirt_raglan', 'sweatshirt_raglan'];

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

    /** طول همهٔ لبه‌های یک برچسب روی یک قطعه. */
    protected function tagLength(array $piece, string $tag): float
    {
        $length = 0.0;

        foreach (Geometry::edgesWithTag($piece, $tag) as $edge) {
            $length += Geometry::edgeLength($piece['outline'], $edge);
        }

        return round($length, 3);
    }

    /** شماره لبه‌ای که نشانه‌ای با این کلید جفت رویش نشسته است. */
    protected function edgeOfPair(array $piece, string $pair): ?int
    {
        foreach ($piece['notches'] ?? [] as $notch) {
            if (($notch['pair'] ?? null) === $pair) {
                return (int) $notch['edge'];
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     |  ثبت و سلامت هندسی
     * ------------------------------------------------------------------- */

    public function test_the_active_family_is_registered_with_all_five_models(): void
    {
        $this->assertArrayHasKey('active', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('active');

        foreach (static::ACTIVE as $key) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در گروه لباس ورزشی نیست.");
        }

        foreach (['tshirt_raglan', 'sweatshirt_raglan'] as $key) {
            $this->assertArrayHasKey($key, GeneratorRegistry::group('shirt'), "«{$key}» در گروه پیراهن نیست.");
        }
    }

    public function test_every_model_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_merge(static::ACTIVE, static::RAGLAN) as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

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
                    $this->assertArrayHasKey('girth_role', $piece['meta'], "{$where} نقش دور (girth_role) اعلام نکرده است.");
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  علامت آزادی: چسبان در برابر رویی
     * ------------------------------------------------------------------- */

    public function test_the_compression_layer_is_cut_smaller_than_the_body(): void
    {
        foreach (['active_sports_bra', 'active_tank', 'active_tights'] as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $body = $this->body($size);
                $stretch = null;

                foreach ($pieces as $piece) {
                    if (($piece['meta']['girth_role'] ?? '') === 'shell' && isset($piece['meta']['stretch_ratio'])) {
                        $stretch = (float) $piece['meta']['stretch_ratio'];
                    }
                }

                $this->assertNotNull($stretch, "«{$key}» باید ضریب کشسانی خودش را اعلام کند.");
                $this->assertLessThan(1.0, $stretch, "«{$key}» لباس کشی است و باید کوچک‌تر از بدن بریده شود.");

                $girth = $this->finishedGirth($pieces, $key);

                $this->assertGreaterThan(0.0, $girth, "«{$key}|{$size}» اندازهٔ تمام‌شده‌ای برای سنجیدن نداد.");
                $this->assertLessThan(
                    $key === 'active_tights' ? $body['waist'] : $body['bust'],
                    $girth,
                    "«{$key}|{$size}» اندازهٔ تمام‌شده‌اش از بدن کوچک‌تر نیست؛ لباس ورزشیِ چسبان روی تن نمی‌ماند.",
                );
            }
        }
    }

    public function test_the_outer_layer_has_positive_ease_and_declares_no_stretch(): void
    {
        foreach (['active_track_jacket', 'active_track_pants'] as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $body = $this->body($size);

                foreach ($pieces as $piece) {
                    $this->assertArrayNotHasKey(
                        'stretch_ratio',
                        $piece['meta'],
                        "«{$key}» لایهٔ رویی است و نباید ضریب کشسانی (آزادی منفی) اعلام کند.",
                    );
                }

                $girth = $this->finishedGirth($pieces, $key);
                $target = $key === 'active_track_pants' ? $body['waist'] : $body['bust'];

                $this->assertGreaterThan(
                    $target,
                    $girth,
                    "«{$key}|{$size}» باید از بدن گشادتر باشد؛ روی لایهٔ چسبان پوشیده می‌شود.",
                );
            }
        }
    }

    /**
     * اندازهٔ تمام‌شدهٔ لباس: دور سینه برای بالاتنه و دور کمر برای پایین‌تنه.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function finishedGirth(array $pieces, string $key): float
    {
        $legs = $this->parts($pieces, ['front_leg', 'back_leg']);

        if ($legs !== []) {
            $total = 0.0;

            foreach ($legs as $leg) {
                $total += PieceOps::seamLength($leg, $leg['meta']['waist_edges'] ?? [0]) * 2;
            }

            return round($total, 2);
        }

        $total = 0.0;

        foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
            foreach ($piece['markers'] ?? [] as $marker) {
                if (($marker['key'] ?? '') !== 'bust') {
                    continue;
                }

                $total += abs(((float) $marker['to']['x']) - ((float) $marker['from']['x']))
                    * max(1, (int) ($piece['cut_quantity'] ?? 1))
                    * (empty($piece['on_fold']) ? 1 : 2);
            }
        }

        unset($key);

        return round($total, 2);
    }

    /* ---------------------------------------------------------------------
     |  گرمکن: آستین در حلقه
     * ------------------------------------------------------------------- */

    public function test_the_track_jacket_sleeve_matches_the_armhole_it_goes_into(): void
    {
        foreach (array_keys(static::BODIES) as $size) {
            $pieces = $this->build('active_track_jacket', $size);
            $armhole = 0.0;

            foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
                $armhole += $this->tagLength($piece, 'armhole');
            }

            $sleeve = $this->part($pieces, 'sleeve');
            $this->assertNotNull($sleeve, 'گرمکن باید آستین داشته باشد.');

            $cap = (float) ($sleeve['meta']['cap_length'] ?? $this->tagLength($sleeve, 'armhole'));
            $ease = $cap - $armhole;

            $this->assertGreaterThan(0.0, $armhole);
            $this->assertGreaterThan(-0.5, $ease, "گرمکن|{$size}: سرآستین از حلقه کوتاه‌تر است.");
            $this->assertLessThan(
                max(6.0, $armhole * 0.2),
                $ease,
                "گرمکن|{$size}: سرآستین {$cap} در برابر حلقهٔ {$armhole}؛ آزادی بیش از اندازه است.",
            );
        }
    }

    /* ---------------------------------------------------------------------
     |  پاچه‌ها
     * ------------------------------------------------------------------- */

    public function test_the_two_legs_of_every_active_trouser_sew_together(): void
    {
        foreach (['active_track_pants', 'active_tights'] as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $front = $this->part($this->build($key, $size), 'front_leg');
                $back = $this->part($this->build($key, $size), 'back_leg');

                $this->assertNotNull($front);
                $this->assertNotNull($back);

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

                $this->assertTrue($inseam['matched'], "{$key}|{$size} درز داخل پای جلو و پشت جور نیست.");
                $this->assertTrue($side['matched'], "{$key}|{$size} درز پهلوی جلو و پشت جور نیست.");
            }
        }
    }

    public function test_the_tights_carry_a_gusset_at_the_crotch(): void
    {
        $gusset = $this->part($this->build('active_tights'), 'gusset');

        $this->assertNotNull($gusset, 'تایت باید لنگهٔ فاق داشته باشد؛ بدون آن چهار درز روی یک نقطه جمع می‌شوند.');
        $this->assertGreaterThan(20.0, Geometry::area($gusset['outline']));
    }

    /* ---------------------------------------------------------------------
     |  رگلان
     * ------------------------------------------------------------------- */

    public function test_a_raglan_body_has_no_shoulder_seam_left_on_it(): void
    {
        foreach (static::RAGLAN as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                foreach ($this->parts($this->build($key, $size), ['front_bodice', 'back_bodice']) as $piece) {
                    $this->assertSame(
                        [],
                        Geometry::edgesWithTag($piece, 'shoulder'),
                        "{$key}|{$size}|{$piece['code']}: در رگلان سرشانه به آستین می‌رود و روی تنه نمی‌ماند.",
                    );
                    $this->assertNotEmpty(
                        Geometry::edgesWithTag($piece, 'neck'),
                        "{$key}|{$size}|{$piece['code']}: تکه‌ای از خط یقه باید روی تنه بماند.",
                    );
                }
            }
        }
    }

    public function test_the_raglan_seam_of_the_sleeve_equals_the_raglan_seam_of_the_body(): void
    {
        foreach (static::RAGLAN as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $sleeve = $this->part($pieces, 'sleeve');

                $this->assertNotNull($sleeve, "«{$key}» باید آستین رگلان داشته باشد.");

                foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $bodice) {
                    $side = ($bodice['meta']['side'] ?? 'front') === 'front' ? 'front' : 'back';

                    foreach ([
                        ['raglan_'.$side, 'درز رگلان'],
                        ['underarm_'.$side, 'حلقه پایین'],
                    ] as [$pair, $label]) {
                        $bodyEdge = $this->edgeOfPair($bodice, $pair);
                        $sleeveEdge = $this->edgeOfPair($sleeve, $pair);

                        $this->assertNotNull($bodyEdge, "{$key}|{$size}: نشانهٔ «{$pair}» روی تنه نیست.");
                        $this->assertNotNull($sleeveEdge, "{$key}|{$size}: نشانهٔ «{$pair}» روی آستین نیست.");

                        $walk = PieceOps::walk($bodice, $bodyEdge, $sleeve, $sleeveEdge, [
                            'tolerance' => static::SEAM_MATCH,
                        ]);

                        $this->assertGreaterThan(4.0, $walk['a']['seam'], "{$key}|{$size}: {$label} طول واقعی ندارد.");
                        $this->assertTrue(
                            $walk['matched'],
                            sprintf(
                                '%s|%s %s %s: تنه %.2f و آستین %.2f؛ اختلاف %.2f سانتی‌متر.',
                                $key, $size, $label, $side, $walk['a']['seam'], $walk['b']['seam'], $walk['difference'],
                            ),
                        );
                    }
                }
            }
        }
    }

    public function test_the_raglan_sleeve_covers_the_whole_remaining_armhole(): void
    {
        foreach (static::RAGLAN as $key) {
            foreach (array_keys(static::BODIES) as $size) {
                $pieces = $this->build($key, $size);
                $armhole = 0.0;

                foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
                    $armhole += $this->tagLength($piece, 'armhole');

                    // پس از برش، عددِ اعلامی باید همان چیزی باشد که روی خودِ قطعه است
                    $this->assertEqualsWithDelta(
                        $this->tagLength($piece, 'armhole'),
                        (float) ($piece['meta']['armhole_length'] ?? -1),
                        0.2,
                        "{$key}|{$size}|{$piece['code']} طول حلقه‌ای را ادعا می‌کند که روی خودش نیست.",
                    );
                    $this->assertEqualsWithDelta(
                        $this->tagLength($piece, 'neck'),
                        (float) ($piece['meta']['neck_length'] ?? -1),
                        0.2,
                        "{$key}|{$size}|{$piece['code']} طول یقه‌ای را ادعا می‌کند که روی خودش نیست.",
                    );
                }

                $cap = 0.0;

                foreach ($this->parts($pieces, ['sleeve']) as $sleeve) {
                    $cap += $this->tagLength($sleeve, 'armhole');
                }

                $this->assertGreaterThan(20.0, $armhole);
                $this->assertEqualsWithDelta(
                    $armhole,
                    $cap,
                    static::SEAM_MATCH * 2,
                    "{$key}|{$size}: جمع درز رگلان و حلقهٔ پایینِ آستین با تنه جور نیست.",
                );
            }
        }
    }

    public function test_the_raglan_neck_band_is_measured_on_all_four_pieces(): void
    {
        foreach (static::RAGLAN as $key) {
            $pieces = $this->build($key);
            $band = null;

            foreach ($pieces as $piece) {
                if (str_ends_with((string) $piece['code'], 'neck-rib')) {
                    $band = $piece;
                }
            }

            $this->assertNotNull($band, "«{$key}» باید نوار یقه داشته باشد.");

            $neckline = 0.0;

            foreach ($pieces as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice', 'sleeve'], true)) {
                    continue;
                }

                $neckline += $this->tagLength($piece, 'neck')
                    * (empty($piece['on_fold']) ? 1 : 2)
                    * max(1, (int) ($piece['cut_quantity'] ?? 1));
            }

            // خط یقهٔ رگلان از چهار تکه ساخته می‌شود؛ اگر یقهٔ آستین‌ها جا بماند،
            // نوار کوتاه بریده می‌شود و لباس از سر رد نمی‌شود
            $this->assertEqualsWithDelta(
                $neckline,
                (float) $band['meta']['target_length'],
                0.5,
                "«{$key}»: نوار یقه روی جمع هر چهار تکه حساب نشده است.",
            );
            $this->assertLessThan(
                (float) $band['meta']['target_length'],
                Geometry::width($band['outline']),
                "«{$key}»: نوار یقه باید کوتاه‌تر از خط یقه بریده شود.",
            );
        }
    }

    public function test_the_raglan_sleeve_head_can_be_drafted_in_three_ways(): void
    {
        foreach (['one_piece', 'dart', 'two_piece'] as $head) {
            $pieces = $this->build('sweatshirt_raglan', '40', ['sleeve_head' => $head]);
            $sleeves = $this->parts($pieces, ['sleeve']);

            $this->assertCount(
                $head === 'two_piece' ? 2 : 1,
                $sleeves,
                "سر آستین «{$head}» شمار قطعهٔ درستی نداد.",
            );

            foreach ($sleeves as $sleeve) {
                $this->assertFalse(Geometry::selfIntersects($sleeve['outline']));
            }
        }
    }
}
