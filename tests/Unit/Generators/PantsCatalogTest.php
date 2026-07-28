<?php

namespace Tests\Unit\Generators;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\PatternGenerator;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * آزمون کاتالوگ شلوار و شلوارک.
 *
 * همه اعداد روی سه سایز واقعی ۳۴، ۴۰ و ۴۸ سنجیده می‌شوند، چون ایرادهای درفت
 * معمولاً در سایز کوچک یا بزرگ خودشان را نشان می‌دهند نه در سایز میانی.
 */
class PantsCatalogTest extends TestCase
{
    /** سایزهایی که همه آزمون‌ها روی آن‌ها اجرا می‌شوند. */
    protected const SIZES = ['34', '40', '48'];

    /** مدل‌هایی که با درفت تازه (PantsBlock) ساخته شده‌اند. */
    protected const KEYS = [
        'pants_skinny', 'pants_tapered', 'pants_bootcut', 'pants_flare', 'pants_cigarette',
        'pants_culottes', 'pants_pleated', 'pants_cargo', 'pants_elastic_waist', 'pants_jogger',
        'pants_harem', 'pants_paperbag', 'leggings',
        'shorts_bermuda', 'shorts_short', 'shorts_cycling', 'shorts_paperbag',
    ];

    /** بلندی‌های فاق که هرکدام باید جداگانه جواب بدهند. */
    protected const RISES = ['low', 'mid', 'high'];

    protected function generator(string $key): PatternGenerator
    {
        return GeneratorRegistry::make($key);
    }

    /**
     * ساخت یک الگو با پارامترهای پیش‌فرض و تغییرهای خواسته‌شده.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function build(string $key, string $size, array $params = [], array $ease = []): array
    {
        $generator = $this->generator($key);

        return $generator->generate(
            Measurements::fromSize($size),
            $ease,
            array_merge($generator->defaultParams(), $params),
        );
    }

    /**
     * پای جلو و پای پشت یک الگو.
     *
     * @return array{front: array<string, mixed>, back: array<string, mixed>}
     */
    protected function legs(array $pieces): array
    {
        $legs = [];

        foreach ($pieces as $piece) {
            if (in_array($piece['meta']['part'] ?? '', ['front_leg', 'back_leg'], true)) {
                $legs[$piece['meta']['side']] = $piece;
            }
        }

        $this->assertArrayHasKey('front', $legs, 'الگو پای جلو ندارد.');
        $this->assertArrayHasKey('back', $legs, 'الگو پای پشت ندارد.');

        return $legs;
    }

    /** دور کمر تمام‌شده: مجموع لبه‌های کمر منهای ساسون، پیلی و چین. */
    protected function waistGirth(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_leg', 'back_leg'], true)) {
                continue;
            }

            $repeats = ! empty($piece['on_fold']) ? 2 : max(1, (int) $piece['cut_quantity']);
            $total += $repeats * PieceOps::seamLength($piece, $piece['meta']['waist_edges']);
        }

        return round($total, 3);
    }

    /* ---------------------------------------------------------------------
     |  دور کمر
     * ------------------------------------------------------------------- */

    public function test_finished_waist_matches_the_target_on_every_size_and_rise(): void
    {
        foreach (static::KEYS as $key) {
            foreach (static::SIZES as $size) {
                foreach (static::RISES as $rise) {
                    $pieces = $this->build($key, $size, ['rise' => $rise]);
                    $legs = $this->legs($pieces);
                    $target = (float) $legs['front']['meta']['waist_target'];

                    $this->assertEqualsWithDelta(
                        $target,
                        $this->waistGirth($pieces),
                        0.3,
                        "دور کمر تمام‌شده {$key} در سایز {$size} با فاق {$rise} با هدف نمی‌خواند.",
                    );
                }
            }
        }
    }

    public function test_natural_waist_rise_uses_the_body_waist_plus_ease(): void
    {
        $ease = ['waist' => 3.5, 'hip' => 5];

        foreach (['pants_tapered', 'pants_cargo', 'shorts_bermuda'] as $key) {
            foreach (static::SIZES as $size) {
                $body = Measurements::fromSize($size);
                $pieces = $this->build($key, $size, ['rise' => 'high'], $ease);
                $legs = $this->legs($pieces);

                // فاق بلند یعنی خط کمر روی گودی کمر است، پس هدف دقیقاً کمر + آزادی
                $this->assertEqualsWithDelta(
                    $body['waist'] + $ease['waist'],
                    (float) $legs['front']['meta']['waist_target'],
                    0.01,
                    "هدف دور کمر {$key} در سایز {$size} با کمر بدن + آزادی نمی‌خواند.",
                );

                $this->assertEqualsWithDelta(
                    $body['waist'] + $ease['waist'],
                    $this->waistGirth($pieces),
                    0.3,
                    "دور کمر تمام‌شده {$key} در سایز {$size} با کمر بدن + آزادی نمی‌خواند.",
                );
            }
        }
    }

    public function test_a_lower_rise_sits_on_a_wider_part_of_the_body(): void
    {
        foreach (static::SIZES as $size) {
            $body = Measurements::fromSize($size);
            $targets = [];

            foreach (static::RISES as $rise) {
                $legs = $this->legs($this->build('pants_tapered', $size, ['rise' => $rise]));
                $targets[$rise] = (float) $legs['front']['meta']['waist_target'];
            }

            $this->assertGreaterThan($targets['mid'], $targets['low'], 'کمر کوتاه باید روی جای گشادتری بنشیند.');
            $this->assertGreaterThan($targets['high'], $targets['mid'], 'کمر متوسط باید از کمر بلند گشادتر باشد.');
            $this->assertLessThan($body['hip'], $targets['low'], 'حتی کمر کوتاه هم نباید به دور باسن برسد.');
        }
    }

    /* ---------------------------------------------------------------------
     |  درزها
     * ------------------------------------------------------------------- */

    public function test_front_and_back_inseams_and_side_seams_walk_to_the_same_length(): void
    {
        foreach (static::KEYS as $key) {
            foreach (static::SIZES as $size) {
                foreach (static::RISES as $rise) {
                    $legs = $this->legs($this->build($key, $size, ['rise' => $rise]));

                    $inseamFront = PieceOps::edgeLength($legs['front'], $legs['front']['meta']['inseam_edges']);
                    $inseamBack = PieceOps::edgeLength($legs['back'], $legs['back']['meta']['inseam_edges']);
                    $sideFront = PieceOps::edgeLength($legs['front'], $legs['front']['meta']['side_edges']);
                    $sideBack = PieceOps::edgeLength($legs['back'], $legs['back']['meta']['side_edges']);

                    $this->assertGreaterThan(3, $inseamFront, "درز داخل پای {$key} طول ندارد.");

                    $this->assertEqualsWithDelta(
                        $inseamFront,
                        $inseamBack,
                        0.05,
                        "درز داخل پای جلو و پشت {$key} در سایز {$size} (فاق {$rise}) هم‌اندازه نیست.",
                    );

                    $this->assertEqualsWithDelta(
                        $sideFront,
                        $sideBack,
                        0.05,
                        "درز پهلوی جلو و پشت {$key} در سایز {$size} (فاق {$rise}) هم‌اندازه نیست.",
                    );
                }
            }
        }
    }

    public function test_the_back_crotch_curve_is_deeper_and_longer_than_the_front(): void
    {
        foreach (static::KEYS as $key) {
            foreach (static::SIZES as $size) {
                $legs = $this->legs($this->build($key, $size));

                $front = PieceOps::edgeLength($legs['front'], $legs['front']['meta']['crotch_edges']);
                $back = PieceOps::edgeLength($legs['back'], $legs['back']['meta']['crotch_edges']);

                $this->assertGreaterThan(
                    $front + 0.8,
                    $back,
                    "منحنی فاق پشت {$key} در سایز {$size} باید بلندتر و گودتر از جلو باشد.",
                );

                // پنجه فاق پشت هم باید بلندتر باشد: پهنای قطعه پشت از جلو بیشتر است
                $this->assertGreaterThan(
                    Geometry::width($legs['front']['outline']),
                    Geometry::width($legs['back']['outline']),
                    "قطعه پشت {$key} باید از قطعه جلو پهن‌تر باشد.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  فاق و تناسب بدن
     * ------------------------------------------------------------------- */

    public function test_rise_and_crotch_depth_stay_anatomically_sane(): void
    {
        foreach (static::KEYS as $key) {
            foreach (static::SIZES as $size) {
                $body = Measurements::fromSize($size);

                foreach (static::RISES as $rise) {
                    $legs = $this->legs($this->build($key, $size, ['rise' => $rise]));
                    $meta = $legs['front']['meta'];

                    $bodyRise = (float) $meta['body_rise'];
                    $where = "{$key} سایز {$size} فاق {$rise}";

                    // فاق ایستاده بین ۱۵٪ و ۲۲٪ قد بدن است
                    $this->assertGreaterThan($body['height'] * 0.14, $bodyRise, "فاق ایستاده {$where} کوتاه است.");
                    $this->assertLessThan($body['height'] * 0.22, $bodyRise, "فاق ایستاده {$where} بلند است.");

                    // فاق ایستاده همیشه از کمر تا باسن گودتر است
                    $this->assertGreaterThan(
                        $body['waist_to_hip'] + 3,
                        $bodyRise,
                        "فاق ایستاده {$where} از کمر تا باسن به‌اندازه کافی گودتر نیست.",
                    );

                    // خط فاق الگو = فاق ایستاده − پایین آمدن کمر + پایین افتادن فاق مدل
                    $expected = $bodyRise - (float) $meta['waist_drop'] + (float) $meta['crotch_drop'];
                    $this->assertEqualsWithDelta($expected, (float) $meta['crotch_depth'], 0.05, "گودی فاق {$where} نمی‌خواند.");

                    // خط فاق همیشه پایین‌تر از خط باسن است
                    $hipY = (float) $meta['hip_y'] - (float) ($meta['paperbag'] ?? 0);
                    $this->assertGreaterThan($hipY + 3, (float) $meta['crotch_depth'], "خط فاق {$where} به خط باسن چسبیده.");

                    // و هیچ‌وقت به نصف قد داخل پا نمی‌رسد
                    $this->assertLessThan(
                        $body['inseam'] * 0.75,
                        (float) $meta['crotch_depth'],
                        "خط فاق {$where} بی‌اندازه پایین افتاده.",
                    );
                }
            }
        }
    }

    public function test_the_leg_is_never_narrower_than_the_body_unless_the_fabric_stretches(): void
    {
        $knits = ['leggings', 'shorts_cycling', 'pants_skinny'];

        foreach (static::KEYS as $key) {
            foreach (static::SIZES as $size) {
                $body = Measurements::fromSize($size);
                $legs = $this->legs($this->build($key, $size));
                $thigh = (float) $legs['front']['meta']['thigh_finished'];

                if (in_array($key, $knits, true)) {
                    // پارچه کشی: الگو کوچک‌تر از بدن است ولی نه بی‌اندازه
                    $this->assertGreaterThan($body['thigh'] * 0.8, $thigh, "دور ران {$key} سایز {$size} خفه است.");

                    continue;
                }

                $this->assertGreaterThan(
                    $body['thigh'] + 1,
                    $thigh,
                    "دور ران {$key} در سایز {$size} از خود ران کوچک‌تر است.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  شخصیت هر مدل
     * ------------------------------------------------------------------- */

    public function test_skinny_narrows_the_hem_without_touching_the_hip(): void
    {
        foreach (static::SIZES as $size) {
            $ease = ['waist' => 3, 'hip' => 6];
            $skinny = $this->build('pants_skinny', $size, ['stretch' => 1.0], $ease);
            $tapered = $this->build('pants_tapered', $size, [], $ease);

            $skinnyLegs = $this->legs($skinny);
            $taperedLegs = $this->legs($tapered);

            $skinnyHem = (float) $skinnyLegs['front']['meta']['hem_width'] + (float) $skinnyLegs['back']['meta']['hem_width'];
            $taperedHem = (float) $taperedLegs['front']['meta']['hem_width'] + (float) $taperedLegs['back']['meta']['hem_width'];

            $this->assertLessThan($taperedHem - 1.5, $skinnyHem, "دم پای اسکینی در سایز {$size} تنگ‌تر نشده.");

            $skinnyKnee = (float) $skinnyLegs['front']['meta']['knee_width'] + (float) $skinnyLegs['back']['meta']['knee_width'];
            $this->assertLessThan($skinnyKnee, $skinnyHem, "دم پای اسکینی باید از زانویش هم تنگ‌تر باشد.");

            // خط باسن یکی است: تنگی اسکینی از باسن گرفته نمی‌شود
            $this->assertEqualsWithDelta(
                (float) $taperedLegs['front']['meta']['hip_target'],
                (float) $skinnyLegs['front']['meta']['hip_target'],
                0.01,
                "اسکینی در سایز {$size} دور باسن را دست‌کاری کرده.",
            );

            $this->assertEqualsWithDelta(
                Measurements::fromSize($size)['hip'] + $ease['hip'],
                (float) $skinnyLegs['front']['meta']['hip_target'],
                0.01,
            );
        }
    }

    public function test_bootcut_and_flare_open_below_the_knee_while_tapered_closes(): void
    {
        foreach (static::SIZES as $size) {
            $widths = [];

            foreach (['pants_tapered', 'pants_bootcut', 'pants_flare'] as $key) {
                $legs = $this->legs($this->build($key, $size));
                $widths[$key] = [
                    'knee' => (float) $legs['front']['meta']['knee_width'] + (float) $legs['back']['meta']['knee_width'],
                    'hem' => (float) $legs['front']['meta']['hem_width'] + (float) $legs['back']['meta']['hem_width'],
                ];
            }

            $this->assertLessThan($widths['pants_tapered']['knee'], $widths['pants_tapered']['hem'], 'شلوار باریک‌شونده باید از زانو تنگ‌تر شود.');
            $this->assertGreaterThan($widths['pants_bootcut']['knee'], $widths['pants_bootcut']['hem'], 'بوت‌کات باید از زانو بازتر شود.');
            $this->assertGreaterThan($widths['pants_bootcut']['hem'], $widths['pants_flare']['hem'], 'فون باید از بوت‌کات هم بازتر باشد.');
        }
    }

    public function test_culottes_read_as_a_wide_short_trouser_not_a_skirt(): void
    {
        foreach (static::SIZES as $size) {
            $body = Measurements::fromSize($size);
            $pieces = $this->build('pants_culottes', $size);
            $legs = $this->legs($pieces);

            foreach ($legs as $side => $leg) {
                // شلوار است: منحنی فاق و درز داخل پا دارد
                $this->assertNotEmpty($leg['meta']['crotch_edges'], "کولوت پای {$side} منحنی فاق ندارد.");
                $this->assertGreaterThan(
                    10,
                    PieceOps::edgeLength($leg, $leg['meta']['inseam_edges']),
                    "کولوت پای {$side} درز داخل پا ندارد و دامن شده.",
                );
            }

            // کوتاه: بین یک‌سوم تا دوسوم قد داخل پا
            $length = (float) $legs['front']['meta']['leg_length'];
            $this->assertGreaterThan($body['inseam'] * 0.3, $length);
            $this->assertLessThan($body['inseam'] * 0.75, $length);

            // گشاد: دور دم پا از دور زانو بیشتر و از دور ران بدن هم بزرگ‌تر است
            $hem = (float) $legs['front']['meta']['hem_width'] + (float) $legs['back']['meta']['hem_width'];
            $knee = (float) $legs['front']['meta']['knee_width'] + (float) $legs['back']['meta']['knee_width'];

            $this->assertGreaterThan($knee, $hem, 'کولوت باید رو به پایین باز شود.');
            $this->assertGreaterThan($body['thigh'], $hem, 'دم کولوت باید از دور ران هم گشادتر باشد.');
        }
    }

    public function test_harem_drops_the_crotch_and_records_its_gathers(): void
    {
        foreach (static::SIZES as $size) {
            $pieces = $this->build('pants_harem', $size);
            $legs = $this->legs($pieces);
            $meta = $legs['front']['meta'];

            $this->assertGreaterThan(8, (float) $meta['crotch_drop'], 'فاق هارمی باید پایین بیفتد.');
            $this->assertEqualsWithDelta(
                (float) $meta['body_rise'] - (float) $meta['waist_drop'] + (float) $meta['crotch_drop'],
                (float) $meta['crotch_depth'],
                0.05,
            );

            foreach ($legs as $side => $leg) {
                $waist = FullnessRecorder::amountOn($leg, $leg['meta']['waist_edges'][0], 'gathers');
                $this->assertGreaterThan(1.0, $waist, "چین کمر هارمی روی پای {$side} ثبت نشده.");

                $hemEdge = $leg['meta']['hem_edges'][0];
                $this->assertGreaterThan(
                    1.0,
                    FullnessRecorder::amountOn($leg, $hemEdge, 'gathers'),
                    "چین دم پای هارمی روی پای {$side} ثبت نشده.",
                );
            }

            // قد شلوار با پایین افتادن فاق کوتاه نمی‌شود
            $this->assertGreaterThan(70, (float) $legs['front']['meta']['hem_y']);
        }
    }

    public function test_leggings_use_negative_ease_and_have_no_darts(): void
    {
        foreach (static::SIZES as $size) {
            $body = Measurements::fromSize($size);
            $pieces = $this->build('leggings', $size);
            $legs = $this->legs($pieces);

            foreach ($legs as $side => $leg) {
                $this->assertSame([], $leg['darts'], "ساپورت نباید ساسون داشته باشد (پای {$side}).");
                $this->assertSame([], $leg['pleats'], "ساپورت نباید پیلی داشته باشد (پای {$side}).");
                $this->assertLessThan(0, (float) $leg['meta']['waist_ease'], 'آزادی کمر ساپورت باید منفی باشد.');
                $this->assertLessThan(0, (float) $leg['meta']['hip_ease'], 'آزادی باسن ساپورت باید منفی باشد.');
            }

            $this->assertLessThan(
                $body['hip'],
                (float) $legs['front']['meta']['hip_target'],
                "الگوی ساپورت در سایز {$size} باید از خود باسن کوچک‌تر بریده شود.",
            );

            $this->assertLessThan($body['waist'], $this->waistGirth($pieces));
        }
    }

    public function test_paperbag_adds_the_stated_extension_above_the_waist(): void
    {
        foreach (['pants_paperbag', 'shorts_paperbag'] as $key) {
            foreach (static::SIZES as $size) {
                foreach ([3.0, 6.0, 9.5] as $extension) {
                    $plain = $this->legs($this->build($key, $size, ['paperbag' => 2.0]));
                    $bagged = $this->legs($this->build($key, $size, ['paperbag' => $extension]));

                    foreach (['front', 'back'] as $side) {
                        $this->assertEqualsWithDelta(
                            $extension,
                            (float) $bagged[$side]['meta']['paperbag'],
                            0.01,
                            "{$key}: بلندی نوار پاکتی روی پای {$side} ثبت نشده.",
                        );

                        $this->assertEqualsWithDelta(
                            Geometry::height($plain[$side]['outline']) + $extension - 2.0,
                            Geometry::height($bagged[$side]['outline']),
                            0.05,
                            "{$key}: قطعه به اندازه نوار پاکتی بلندتر نشده (پای {$side}، سایز {$size}).",
                        );

                        // خط کمر همان‌جاست که نوار پاکتی تمام می‌شود
                        $waistMarker = null;

                        foreach ($bagged[$side]['markers'] as $marker) {
                            if (($marker['key'] ?? '') === 'waist') {
                                $waistMarker = $marker;
                            }
                        }

                        $this->assertNotNull($waistMarker, "{$key}: خط کمر روی قطعه پاکتی علامت نخورده.");
                        $this->assertEqualsWithDelta($extension, (float) $waistMarker['from']['y'], 0.05);
                    }
                }
            }
        }
    }

    public function test_pleated_front_puts_its_waist_reduction_into_pleats_not_darts(): void
    {
        foreach (static::SIZES as $size) {
            $legs = $this->legs($this->build('pants_pleated', $size, ['rise' => 'high', 'front_pleats' => 2]));
            $front = $legs['front'];

            $this->assertSame([], $front['darts'], 'جلوی شلوار پیلی‌دار نباید ساسون داشته باشد.');
            $this->assertCount(2, $front['pleats'], 'دو پیلی جلو ساخته نشده.');

            $edge = $front['meta']['waist_edges'][0];
            $recorded = FullnessRecorder::amountOn($front, $edge, 'pleats');
            $drawn = array_sum(array_map(fn ($pleat) => (float) $pleat['depth'], $front['pleats']));

            $this->assertGreaterThan(1.0, $recorded, 'پیلی جلو در meta ثبت نشده.');
            $this->assertEqualsWithDelta($recorded, $drawn, 0.02, 'پیلی ثبت‌شده با پیلی رسم‌شده نمی‌خواند.');
        }
    }

    public function test_shorts_are_short_and_have_no_knee_line(): void
    {
        foreach (['shorts_bermuda', 'shorts_short', 'shorts_cycling', 'shorts_paperbag'] as $key) {
            foreach (static::SIZES as $size) {
                $body = Measurements::fromSize($size);
                $legs = $this->legs($this->build($key, $size));
                $meta = $legs['front']['meta'];

                $this->assertLessThan(
                    $body['inseam'] * 0.5,
                    (float) $meta['leg_length'],
                    "{$key} در سایز {$size} برای شلوارک بلند است.",
                );

                $this->assertNull($meta['knee_y'], "{$key} نباید خط زانو داشته باشد.");

                // دم شلوارک روی ران می‌افتد، پس باید از خود ران گشادتر باشد
                $hem = (float) $legs['front']['meta']['hem_width'] + (float) $legs['back']['meta']['hem_width'];
                $ratio = $key === 'shorts_cycling' ? 0.75 : 1.0;

                $this->assertGreaterThan(
                    $this->legGirth($body, (float) $meta['leg_length']) * $ratio,
                    $hem,
                    "دم {$key} در سایز {$size} روی پا گیر می‌کند.",
                );
            }
        }
    }

    /** دور پای بدن در فاصله داده‌شده از خط فاق (درون‌یابی ران، زانو و مچ پا). */
    protected function legGirth(array $body, float $below): float
    {
        $ratio = max(0.0, min(1.0, $below / $body['inseam']));

        return $ratio <= 0.47
            ? $body['thigh'] + (($body['knee'] - $body['thigh']) * ($ratio / 0.47))
            : $body['knee'] + (($body['ankle'] - $body['knee']) * (($ratio - 0.47) / 0.53));
    }

    /* ---------------------------------------------------------------------
     |  کاتالوگ
     * ------------------------------------------------------------------- */

    public function test_every_generator_is_discovered_in_the_pants_group(): void
    {
        GeneratorRegistry::flush();
        $group = GeneratorRegistry::group('pants');

        foreach (static::KEYS as $key) {
            $this->assertArrayHasKey($key, $group, "مدل «{$key}» در گروه پایین‌تنه پیدا نشد.");
            $this->assertNotSame('', $group[$key], "مدل «{$key}» نام فارسی ندارد.");
            $this->assertSame('pants', GeneratorRegistry::groupOf($key));
        }

        // دو مدل قدیمی هم سر جایشان هستند
        $this->assertArrayHasKey('pants_straight', $group);
        $this->assertArrayHasKey('pants_wide_leg', $group);

        // نام‌ها یکتا هستند
        $this->assertSame(count($group), count(array_unique($group)), 'دو مدل پایین‌تنه نام یکسان دارند.');
    }

    public function test_every_pants_generator_yields_valid_pieces(): void
    {
        foreach (GeneratorRegistry::group('pants') as $key => $label) {
            $generator = $this->generator($key);

            foreach (static::SIZES as $size) {
                $pieces = $generator->generate(
                    Measurements::fromSize($size),
                    [],
                    $generator->defaultParams(),
                );

                $this->assertNotEmpty($pieces, "مدل {$key} هیچ قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $this->assertSame(
                        [],
                        Geometry::validatePiece($piece),
                        "قطعه «{$piece['code']}» مدل {$key} در سایز {$size} سالم نیست.",
                    );

                    $this->assertCount(count($piece['outline']), $piece['meta']['edges']);
                    $this->assertNotEmpty($piece['grainline'], "قطعه «{$piece['code']}» راستای پارچه ندارد.");

                    [$minX, $minY] = Geometry::bounds($piece['outline']);
                    $this->assertEqualsWithDelta(0.0, $minX, 0.001);
                    $this->assertEqualsWithDelta(0.0, $minY, 0.001);
                }
            }
        }
    }

    public function test_every_generator_marks_hip_crotch_and_knee_lines_and_pairs_its_notches(): void
    {
        foreach (static::KEYS as $key) {
            $legs = $this->legs($this->build($key, '40'));

            foreach ($legs as $side => $leg) {
                $markers = array_column($leg['markers'], 'key');

                foreach (['hip', 'crotch', 'crease'] as $needed) {
                    $this->assertContains($needed, $markers, "{$key}: خط {$needed} روی پای {$side} علامت نخورده.");
                }

                if ($leg['meta']['knee_y'] !== null) {
                    $this->assertContains('knee', $markers, "{$key}: خط زانو روی پای {$side} علامت نخورده.");
                }

                // راستای پارچه روی خط اتوی پا می‌افتد
                $this->assertEqualsWithDelta(
                    (float) $leg['meta']['crease_x'],
                    (float) $leg['grainline']['from']['x'],
                    0.05,
                    "{$key}: راستای پارچه روی خط اتوی پای {$side} نیست.",
                );
            }

            // نشانه‌های جفت روی جلو و پشت با هم می‌خوانند
            $frontPairs = array_column($legs['front']['notches'], 'pair');
            $backPairs = array_column($legs['back']['notches'], 'pair');

            foreach (['hip', 'thigh', 'fork'] as $pair) {
                $this->assertContains($pair, $frontPairs, "{$key}: نشانه «{$pair}» روی پای جلو نیست.");
                $this->assertContains($pair, $backPairs, "{$key}: نشانه «{$pair}» روی پای پشت نیست.");
            }
        }
    }

    public function test_generators_are_deterministic(): void
    {
        foreach (static::KEYS as $key) {
            $first = $this->build($key, '40');
            $second = $this->build($key, '40');

            $this->assertSame($first, $second, "مدل {$key} دو بار اجرا شد و دو خروجی داد.");
        }
    }

    public function test_every_generator_declares_a_usable_params_schema(): void
    {
        foreach (static::KEYS as $key) {
            $generator = $this->generator($key);
            $schema = $generator->paramsSchema();
            $defaults = $generator->defaultParams();

            $this->assertNotEmpty($schema, "مدل {$key} پارامتری ندارد.");

            foreach ($schema as $name => $field) {
                $this->assertArrayHasKey('label', $field, "پارامتر {$name} مدل {$key} برچسب ندارد.");
                $this->assertArrayHasKey('default', $field, "پارامتر {$name} مدل {$key} پیش‌فرض ندارد.");
                $this->assertArrayHasKey($name, $defaults);

                if (isset($field['min'], $field['max'])) {
                    $this->assertGreaterThanOrEqual($field['min'], $field['default']);
                    $this->assertLessThanOrEqual($field['max'], $field['default']);
                }

                if (($field['type'] ?? '') === 'select') {
                    $this->assertArrayHasKey('options', $field);
                    $this->assertArrayHasKey($field['default'], $field['options']);
                }
            }
        }
    }

    public function test_parameters_move_the_draft_in_the_expected_direction(): void
    {
        $base = $this->legs($this->build('pants_tapered', '40'));

        $longer = $this->legs($this->build('pants_tapered', '40', ['length_extra' => 8]));
        $this->assertEqualsWithDelta(
            (float) $base['front']['meta']['hem_y'] + 8,
            (float) $longer['front']['meta']['hem_y'],
            0.05,
        );

        $deeper = $this->legs($this->build('pants_tapered', '40', ['rise_extra' => 3]));
        $this->assertEqualsWithDelta(
            (float) $base['front']['meta']['crotch_depth'] + 3,
            (float) $deeper['front']['meta']['crotch_depth'],
            0.05,
        );

        $wider = $this->legs($this->build('pants_tapered', '40', ['hem_ease' => 30, 'knee_ease' => 20]));
        $this->assertGreaterThan(
            (float) $base['front']['meta']['hem_width'] + 4,
            (float) $wider['front']['meta']['hem_width'],
        );
    }
}
