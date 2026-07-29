<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * کاتالوگ پیراهن زنانه.
 *
 * جای شکستن این خانواده یک جاست و همه‌جا همان است: **خط کمر.** بالاتنه و دامن جدا
 * درفت می‌شوند؛ اگر دو کمر به هم نرسند، الگو روی کاغذ درست است و روی پارچه دوخته
 * نمی‌شود. بیشتر آزمون‌های این پرونده همان یک نقطه را می‌پایند، و بقیه‌شان
 * می‌سنجند که هر مدل واقعاً همان مدل باشد — نه پیراهنی با نام دیگر.
 */
class DressCatalogTest extends TestCase
{
    /** مدل‌های گروه «پیراهن زنانه». */
    protected const DRESSES = [
        'dress_slip', 'dress_wrap', 'dress_shirtdress', 'dress_sheath',
        'dress_shift', 'dress_tent', 'dress_empire', 'dress_pinafore',
    ];

    /** مدل‌های تازهٔ گروه «لباس شب و عروس». */
    protected const GOWNS = ['gown_trumpet', 'dress_corset', 'dress_bridesmaid', 'dress_flower_girl'];

    /**
     * بدن‌های آزمون.
     *
     * Measurements::fromSize فقط ۳۴ تا ۴۸ را می‌شناسد و برای هر کلید ناشناخته
     * بی‌صدا به ۴۰ برمی‌گردد. پس بدن کودک و بدن بلندقد صریح نوشته شده‌اند، وگرنه
     * «آزمون روی بدن کودک» در واقع سه بار آزمون روی سایز ۴۰ می‌شد.
     */
    protected const BODIES = [
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'بلندقد' => ['height' => 195, 'bust' => 84, 'waist' => 66, 'hip' => 90, 'shoulder_width' => 44, 'arm_length' => 72],
    ];

    /** پنج بدنی که هر مدل روی آن‌ها ساخته می‌شود. */
    protected const SIZES = ['34', '40', '48', 'کودک', 'بلندقد'];

    /** برچسب‌های مجاز لبه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'strap', 'default'];

    /** @return array<string, float> */
    protected function body(string $size): array
    {
        return isset(static::BODIES[$size])
            ? Measurements::complete(static::BODIES[$size])
            : Measurements::complete(Measurements::fromSize($size));
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

    /** @param  array<int, array<string, mixed>>  $pieces */
    protected function notes(array $pieces): string
    {
        $notes = [];

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notes'] ?? [] as $note) {
                $notes[] = $note;
            }
        }

        return implode(' | ', $notes);
    }

    /**
     * قطعه‌هایی که meta.part آن‌ها در این فهرست است.
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

    /**
     * دور یک برچسب لبه روی چند قطعه.
     *
     * ساسون، چین و تای پارچه همه حساب می‌شوند: چیزی که در دوخت خورده می‌شود جزو
     * اندازهٔ تمام‌شده نیست، و پنلی که دولا بریده می‌شود دو بار می‌آید.
     */
    protected function girth(array $pieces, string $tag, array $parts): float
    {
        $total = 0.0;

        foreach ($this->parts($pieces, $parts) as $piece) {
            $edges = Geometry::edgesWithTag($piece, $tag);

            if ($edges === []) {
                continue;
            }

            // seamLength خودش چین و پیلی ثبت‌شده را کم می‌کند
            $length = PieceOps::seamLength($piece, $edges);

            foreach ($piece['darts'] ?? [] as $dart) {
                if (($dart['edge'] ?? null) === null && isset($dart['intake'])) {
                    $length -= (float) $dart['intake'];
                }
            }

            $total += max(0.0, $length)
                * max(1, (int) ($piece['cut_quantity'] ?? 1))
                * (empty($piece['on_fold']) ? 1 : 2);
        }

        return round($total, 2);
    }

    /* ---------------------------------------------------------------------
     |  فهرست و هندسه
     * ------------------------------------------------------------------- */

    public function test_the_family_is_registered_in_the_right_groups(): void
    {
        $this->assertArrayHasKey('dress', GeneratorRegistry::GROUPS);

        $dresses = GeneratorRegistry::group('dress');

        foreach (static::DRESSES as $key) {
            $this->assertArrayHasKey($key, $dresses, "«{$key}» در گروه پیراهن زنانه نیست.");
        }

        $gowns = GeneratorRegistry::group('evening');

        foreach (static::GOWNS as $key) {
            $this->assertArrayHasKey($key, $gowns, "«{$key}» در گروه لباس شب نیست.");
        }

        // نام هر مدل باید یکتا باشد، وگرنه کاربر در فهرست دو ردیف هم‌نام می‌بیند
        $labels = array_map(fn (string $key) => GeneratorRegistry::make($key)->label(), array_merge(static::DRESSES, static::GOWNS));
        $this->assertSame($labels, array_unique($labels), 'دو مدل نام یکسان دارند.');
    }

    public function test_every_dress_builds_a_sound_pattern_on_five_bodies(): void
    {
        foreach (array_merge(static::DRESSES, static::GOWNS) as $key) {
            foreach (static::SIZES as $size) {
                $pieces = $this->build($key, $size);

                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                $codes = array_column($pieces, 'code');
                $this->assertSame(count($codes), count(array_unique($codes)), "{$key}|{$size} کد قطعهٔ تکراری دارد.");

                foreach ($pieces as $piece) {
                    $where = "{$key}|{$size}|".($piece['name'] ?? '?');
                    $outline = array_values($piece['outline'] ?? []);

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$where} مسیر ندارد.");
                    $this->assertFalse(Geometry::selfIntersects($outline), "{$where} مسیرش خودش را قطع می‌کند.");
                    $this->assertGreaterThan(0.0, Geometry::area($outline), "{$where} مساحت ندارد.");

                    $tags = $piece['meta']['edges'] ?? null;
                    $this->assertIsArray($tags, "{$where} برچسب لبه ندارد.");
                    $this->assertCount(count($outline), $tags, "{$where} شمار برچسب‌ها با شمار لبه‌ها یکی نیست.");

                    foreach ($tags as $tag) {
                        $this->assertContains($tag, static::EDGE_TAGS, "{$where} برچسب ناشناختهٔ «{$tag}» دارد.");
                    }

                    foreach ($piece['meta']['fold_edges'] ?? [] as $edge) {
                        $this->assertIsInt($edge, "{$where} لبهٔ تای پارچه عدد صحیح نیست.");
                        $this->assertLessThan(count($outline), $edge, "{$where} لبهٔ تای پارچه بیرون مسیر است.");
                    }
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  خط کمر: جایی که این خانواده می‌شکند
     * ------------------------------------------------------------------- */

    public function test_the_bodice_waist_and_the_skirt_waist_meet_on_every_body(): void
    {
        foreach (array_merge(static::DRESSES, static::GOWNS) as $key) {
            foreach (static::SIZES as $size) {
                $pieces = $this->build($key, $size);

                $bodice = $this->girth($pieces, 'waist', ['front_bodice', 'back_bodice']);
                $skirt = $this->girth($pieces, 'waist', ['skirt_front', 'skirt_back', 'skirt_panel']);

                if ($bodice <= 0 || $skirt <= 0) {
                    continue; // مدل یک‌تکه؛ خط کمری در کار نیست
                }

                $this->assertEqualsWithDelta(
                    $bodice,
                    $skirt,
                    1.0,
                    "«{$key}» روی «{$size}»: کمر بالاتنه {$bodice} و کمر دامن {$skirt}؛ این دو به هم دوخته می‌شوند.",
                );
            }
        }
    }

    public function test_a_dress_with_a_waist_seam_says_out_loud_how_the_two_waists_were_joined(): void
    {
        foreach (['dress_sheath', 'dress_wrap', 'dress_empire', 'dress_shirtdress', 'dress_pinafore'] as $key) {
            $notes = $this->notes($this->build($key));

            $this->assertMatchesRegularExpression(
                '/(مستقیم به هم دوخته می‌شوند|چین داده می‌شود|به هم نمی‌رسند)/u',
                $notes,
                "«{$key}» باید بگوید کمر دامن و بالاتنه چطور به هم می‌رسند.",
            );

            $this->assertStringNotContainsString('به هم نمی‌رسند', $notes, "«{$key}» نباید کمرِ نامتعادل بدهد.");
        }
    }

    public function test_gathered_fullness_is_recorded_where_the_measurers_read_it(): void
    {
        // meta.fullness را هیچ اندازه‌گیر عمومی‌ای نمی‌خواند؛ چین باید در
        // meta.gathers هم باشد، وگرنه پهنای خام پارچه اندازهٔ کمر شمرده می‌شود.
        foreach (['dress_empire', 'dress_flower_girl'] as $key) {
            $skirt = $this->parts($this->build($key), ['skirt_front', 'skirt_back']);

            $this->assertNotEmpty($skirt, "«{$key}» دامن ندارد.");

            foreach ($skirt as $piece) {
                $this->assertNotEmpty(
                    $piece['meta']['gathers'] ?? [],
                    "«{$key}» دامن چین‌دار دارد ولی چینش در meta.gathers ثبت نشده است.",
                );
            }
        }

        // پیراهن پیراهنیِ چین‌دار هم همان قاعده را دارد
        $gathered = $this->parts($this->build('dress_shirtdress', '40', ['skirt_style' => 'gather']), ['skirt_front', 'skirt_back']);

        foreach ($gathered as $piece) {
            $this->assertNotEmpty($piece['meta']['gathers'] ?? [], 'دامن چین‌دار پیراهن پیراهنی باید چینش را ثبت کند.');
        }

        $bodice = $this->girth($gathered, 'waist', ['skirt_front', 'skirt_back']);
        $this->assertGreaterThan(0, $bodice);
    }

    /* ---------------------------------------------------------------------
     |  درز و بست
     * ------------------------------------------------------------------- */

    public function test_the_front_and_back_side_seams_walk_to_the_same_length(): void
    {
        foreach (array_merge(static::DRESSES, static::GOWNS) as $key) {
            foreach (static::SIZES as $size) {
                $pieces = $this->build($key, $size);

                foreach ([['front_bodice', 'back_bodice'], ['skirt_front', 'skirt_back']] as [$frontPart, $backPart]) {
                    $fronts = $this->parts($pieces, [$frontPart]);
                    $backs = $this->parts($pieces, [$backPart]);

                    if (count($fronts) !== 1 || count($backs) !== 1) {
                        continue;
                    }

                    if (Geometry::edgesWithTag($fronts[0], 'side') === [] || Geometry::edgesWithTag($backs[0], 'side') === []) {
                        continue;
                    }

                    $walk = PieceOps::walk($fronts[0], 'side', $backs[0], 'side', ['tolerance' => 0.15]);

                    $this->assertTrue(
                        $walk['matched'],
                        sprintf(
                            '%s|%s درز پهلوی «%s» %.2f و «%s» %.2f است؛ اختلاف %.2f سانتی‌متر.',
                            $key, $size, $fronts[0]['code'], $walk['a']['seam'], $backs[0]['code'], $walk['b']['seam'], $walk['difference'],
                        ),
                    );
                }
            }
        }
    }

    public function test_a_back_closure_is_long_enough_to_clear_the_hip(): void
    {
        // مدل‌هایی که جلوشان باز است یا از سر پوشیده می‌شوند، بستِ پشت لازم ندارند
        foreach (['dress_sheath', 'dress_shift', 'dress_empire', 'dress_pinafore', 'gown_trumpet', 'dress_bridesmaid', 'dress_flower_girl'] as $key) {
            foreach (['34', '40', '48'] as $size) {
                $body = $this->body($size);
                $length = null;

                foreach ($this->build($key, $size) as $piece) {
                    foreach ($piece['meta']['notions'] ?? [] as $notion) {
                        if (in_array($notion['type'] ?? '', ['zip', 'button'], true) && isset($notion['length'])) {
                            $length = max((float) $length, (float) $notion['length']);
                        }

                        if (($notion['type'] ?? '') === 'zip') {
                            $length = max((float) $length, (float) ($notion['length'] ?? 0));
                        }
                    }
                }

                $this->assertNotNull($length, "«{$key}» روی «{$size}» بستِ پشت ندارد.");
                $this->assertGreaterThan(
                    (float) $body['waist_to_hip'],
                    $length,
                    "بستِ «{$key}» روی «{$size}» باید از باسن رد شود، وگرنه لباس پوشیده نمی‌شود.",
                );
            }
        }

        // لباس کرست‌دار با بند کشی بسته می‌شود، نه با زیپ؛ حلقه‌هایش باید باشند
        $eyelets = 0;

        foreach ($this->build('dress_corset') as $piece) {
            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                if (($notion['type'] ?? '') === 'eyelet') {
                    $eyelets += (int) ($notion['count'] ?? 0);
                }
            }
        }

        $this->assertGreaterThanOrEqual(12, $eyelets, 'بند کشی کرست دست‌کم دوازده حلقه می‌خواهد.');
    }

    public function test_a_dress_is_lined_where_it_needs_to_be(): void
    {
        $lined = fn (array $pieces) => $this->parts($pieces, ['lining']);

        // غلافی پیش‌فرض آستر دارد؛ آستر همان چیزی است که لباس جذب را روی تن نگه می‌دارد
        $this->assertNotEmpty($lined($this->build('dress_sheath')), 'پیراهن غلافی پیش‌فرض آستر دارد.');
        $this->assertEmpty($lined($this->build('dress_sheath', '40', ['lining' => 'none'])), 'آستر باید بشود برداشت.');

        // بالاتنهٔ ساقدوش آستر دارد ولی دامن شیفونش نه
        $bridesmaid = $this->build('dress_bridesmaid');
        $this->assertNotEmpty($lined($bridesmaid), 'بالاتنهٔ لباس ساقدوش آستر دارد.');

        // کرست بدون لایهٔ پشتیبان دوخته نمی‌شود
        $coutil = array_filter($this->build('dress_corset'), fn (array $p) => ! empty($p['meta']['coutil']));
        $this->assertNotEmpty($coutil, 'کرست باید لایهٔ کوتیل داشته باشد.');

        // آستر هرگز خودش دوباره آستر نمی‌گیرد و متعلقاتش دوباره خریده نمی‌شود
        foreach ($lined($this->build('dress_sheath')) as $piece) {
            $this->assertSame('lining', $piece['meta']['girth_role'] ?? null);
            $this->assertArrayNotHasKey('notions', $piece['meta']);
        }
    }

    /* ---------------------------------------------------------------------
     |  هر مدل واقعاً همان مدل باشد
     * ------------------------------------------------------------------- */

    public function test_the_slip_dress_is_cut_on_the_bias_and_hangs_from_narrow_straps(): void
    {
        $pieces = $this->build('dress_slip');

        $shell = $this->parts($pieces, ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back']);
        $this->assertNotEmpty($shell);

        foreach ($shell as $piece) {
            $this->assertTrue((bool) ($piece['meta']['bias'] ?? false), "«{$piece['code']}» باید روی اریب بریده شود.");
        }

        $straps = array_filter($pieces, fn (array $p) => ! empty($p['meta']['strap']));
        $this->assertNotEmpty($straps, 'پیراهن سلیپ بند دارد.');

        foreach ($straps as $strap) {
            $this->assertLessThanOrEqual(4.0, (float) $strap['meta']['finished_width'], 'بند سلیپ باید باریک باشد.');
        }

        // اریب ساسون نمی‌خواهد؛ فرم را خودِ پارچه می‌گیرد
        foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
            $this->assertEmpty($piece['darts'] ?? [], 'بالاتنهٔ اریب ساسون ندارد.');
        }

        $this->assertStringContainsString('اریب', $this->notes($pieces));
    }

    public function test_the_wrap_dress_really_wraps_and_ties(): void
    {
        $overlap = 15.0;
        $pieces = $this->build('dress_wrap', '40', ['overlap' => $overlap]);

        // هم‌پوشانی باید روی بالاتنه و دامن *یکسان* باشد، وگرنه دو لبه در خط کمر
        // روی هم نمی‌افتند
        $front = $this->parts($pieces, ['front_bodice'])[0];
        $skirt = $this->parts($pieces, ['skirt_front'])[0];

        $this->assertSame(2, (int) $front['cut_quantity'], 'جلوی راپ دو تکهٔ قرینه است.');
        $this->assertSame(2, (int) $skirt['cut_quantity'], 'دامن راپ هم دو تکهٔ قرینه است.');
        $this->assertFalse((bool) $front['on_fold']);

        $plain = $this->build('dress_wrap', '40', ['overlap' => 8]);
        $plainFront = $this->parts($plain, ['front_bodice'])[0];

        $this->assertEqualsWithDelta(
            $overlap - 8,
            Geometry::width($front['outline']) - Geometry::width($plainFront['outline']),
            0.6,
            'هم‌پوشانی باید واقعاً پهنای جلو را زیاد کند.',
        );

        // چین درز راپ: کاهش کمر به‌جای ساسون، روی درز کمر چین داده می‌شود
        // (ساسون سینه روی درز پهلو سر جایش می‌ماند و ربطی به این ندارد)
        $waistDarts = array_filter($front['darts'] ?? [], fn (array $d) => ($d['type'] ?? '') === 'waist');
        $this->assertEmpty($waistDarts, 'جلوی راپ ساسون کمر ندارد.');
        $this->assertNotEmpty($front['meta']['gathers'] ?? [], 'جلوی راپ باید چین درز راپ داشته باشد.');

        $ties = array_filter($pieces, fn (array $p) => ! empty($p['meta']['strap']));
        $this->assertGreaterThanOrEqual(2, count($ties), 'پیراهن راپ دو بند کمر دارد.');

        $this->assertStringContainsString('سوراخ', $this->notes($pieces), 'بند بلند باید از سوراخ درز پهلو رد شود.');
    }

    public function test_the_shirtdress_has_a_placket_a_collar_and_buttons_on_both_halves(): void
    {
        $pieces = $this->build('dress_shirtdress');

        $collar = $this->parts($pieces, ['collar']);
        $this->assertNotEmpty($collar, 'پیراهن پیراهنی یقه دارد.');

        $facing = $this->parts($pieces, ['facing']);
        $this->assertNotEmpty($facing, 'جلوی باز بدون سجاف لوله می‌شود.');

        $front = $this->parts($pieces, ['front_bodice'])[0];
        $skirt = $this->parts($pieces, ['skirt_front'])[0];

        $this->assertGreaterThan(0, count($front['drills'] ?? []), 'بالاتنه باید جای دکمه داشته باشد.');
        $this->assertGreaterThan(0, count($skirt['drills'] ?? []), 'دامن هم باید جای دکمه داشته باشد؛ وگرنه لباس از کمر به پایین باز است.');

        // یقه از دور یقهٔ همین الگو بریده می‌شود، نه از جدول
        $neck = 0.0;

        foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
            $neck += (float) ($piece['meta']['neck_length'] ?? 0);
        }

        $stand = array_values(array_filter($collar, fn (array $p) => str_contains((string) $p['code'], 'collar-stand')));
        $this->assertNotEmpty($stand);
        $this->assertEqualsWithDelta($neck, Geometry::width($stand[0]['outline']) - 1.5, 1.5, 'پایهٔ یقه باید هم‌اندازهٔ خط یقه باشد.');
    }

    public function test_the_sheath_is_fitted_with_darts_a_vent_and_a_zip(): void
    {
        $pieces = $this->build('dress_sheath');
        $front = $this->parts($pieces, ['front_bodice'])[0];

        $types = array_column($front['darts'] ?? [], 'type');
        $this->assertContains('bust', $types, 'غلافی ساسون سینه دارد.');
        $this->assertContains('waist', $types, 'غلافی ساسون کمر دارد.');

        $back = $this->parts($pieces, ['skirt_back'])[0];
        $this->assertGreaterThan(1.0, (float) ($back['meta']['vent'] ?? 0), 'دامن غلافی چاک پشت دارد.');

        $this->assertStringContainsString('چاک', $this->notes($pieces));
    }

    public function test_the_shift_is_straight_and_has_no_waist_darts(): void
    {
        $pieces = $this->build('dress_shift');
        $panels = $this->parts($pieces, ['front_bodice', 'back_bodice']);

        $this->assertNotEmpty($panels);
        $this->assertEmpty($this->parts($pieces, ['skirt_front', 'skirt_back']), 'شیفت یک‌تکه است و خط کمر ندارد.');

        foreach ($panels as $piece) {
            $waistDarts = array_filter($piece['darts'] ?? [], fn (array $d) => ($d['type'] ?? '') === 'waist');
            $this->assertEmpty($waistDarts, 'شیفت ساسون کمر ندارد.');
            $this->assertSame([], Geometry::edgesWithTag($piece, 'waist'), 'شیفت لبهٔ کمر ندارد.');
        }
    }

    public function test_the_tent_dress_swings_out_from_the_underarm(): void
    {
        $pieces = $this->build('dress_tent');
        $front = $this->parts($pieces, ['front_bodice'])[0];

        // پهنای دم از کادر خودِ قطعه خوانده می‌شود، نه از یک ارتفاع ثابت: لبهٔ
        // پایینِ جلو افت دارد و روی هر ارتفاعی پهنای دیگری می‌دهد.
        $swing = fn (array $piece) => Geometry::width($piece['outline'])
            - $this->widthAt($piece, (float) $piece['meta']['bust_y'] + 1);

        $this->assertGreaterThan(10.0, $swing($front), 'لباس چادری باید از خط زیر بغل تا دم به‌روشنی باز شود.');
        $this->assertEmpty($front['darts'] ?? [], 'لباس چادری ساسون ندارد.');

        // شیفتِ راست همین‌جا از چادری جدا می‌شود
        $shift = $this->parts($this->build('dress_shift'), ['front_bodice'])[0];

        $this->assertGreaterThan($swing($shift) + 8, $swing($front), 'چادری باید به‌روشنی بازتر از شیفت باشد.');
    }

    public function test_the_empire_waist_sits_above_the_natural_waist(): void
    {
        $pieces = $this->build('dress_empire');
        $back = $this->parts($pieces, ['back_bodice'])[0];

        $body = $this->body('40');
        $height = Geometry::height($back['outline']);

        $this->assertLessThan(
            (float) $body['back_length'] - 4,
            $height,
            'بالاتنهٔ امپایر باید به‌روشنی کوتاه‌تر از قد بالاتنه تا خط کمر باشد.',
        );

        // بالاتنه با دورِ زیر سینه درفت می‌شود، نه با دور کمر
        $waist = $this->girth($pieces, 'waist', ['front_bodice', 'back_bodice']);
        $this->assertGreaterThan((float) $body['waist'], $waist, 'دور زیر سینه از دور کمر بیشتر است.');
        $this->assertLessThan((float) $body['bust'], $waist, 'دور زیر سینه از دور سینه کمتر است.');

        $this->assertStringContainsString('زیر سینه', $this->notes($pieces));
    }

    public function test_the_pinafore_is_roomy_enough_to_go_over_another_garment(): void
    {
        $pinafore = $this->build('dress_pinafore');
        $sheath = $this->build('dress_sheath');

        $bust = fn (array $pieces) => array_sum(array_map(
            fn (array $p) => array_sum(array_map(
                fn (array $m) => ($m['key'] ?? '') === 'bust' ? abs($m['to']['x'] - $m['from']['x']) : 0.0,
                $p['markers'] ?? [],
            )) * max(1, (int) $p['cut_quantity']) * (empty($p['on_fold']) ? 1 : 2),
            $this->parts($pieces, ['front_bodice', 'back_bodice']),
        ));

        $this->assertGreaterThan($bust($sheath) + 6, $bust($pinafore), 'سارافون باید جای لباسِ زیرش را داشته باشد.');

        // حلقه بازتر است و آستین ندارد
        $this->assertEmpty($this->parts($pinafore, ['sleeve']), 'سارافون آستین ندارد.');
        $this->assertNotEmpty(
            array_filter($pinafore, fn (array $p) => str_contains((string) $p['code'], 'armhole-binding')),
            'لبهٔ حلقهٔ سارافون باید با نوار تمام شود.',
        );
    }

    /* ---------------------------------------------------------------------
     |  لباس شب
     * ------------------------------------------------------------------- */

    public function test_the_trumpet_flares_higher_and_gentler_than_the_mermaid(): void
    {
        $narrowest = function (string $key): array {
            $skirt = $this->parts($this->build($key), ['skirt_front']);
            $this->assertNotEmpty($skirt, "«{$key}» دامن جلو ندارد.");

            $piece = $skirt[0];
            $hipY = (float) ($piece['meta']['hip_y'] ?? 20);
            $outline = array_values($piece['outline']);
            $best = null;

            // فقط رأس‌های خودِ درز پهلو شمرده می‌شوند؛ رأسِ دمِ مرکز جلو هم پایین‌تر
            // از باسن است ولی x آن صفر است و باریک‌ترین نقطه را جعل می‌کند.
            foreach ($piece['meta']['side_edges'] ?? [] as $edge) {
                $point = $outline[((int) $edge + 1) % count($outline)];

                if ((float) $point['y'] <= $hipY + 2) {
                    continue;
                }

                if ($best === null || (float) $point['x'] < (float) $best['x']) {
                    $best = $point;
                }
            }

            $this->assertNotNull($best, "«{$key}» روی درز پهلو رأسی زیر خط باسن ندارد.");

            return [(float) $best['y'], (float) $best['x'], (float) ($piece['meta']['quarter_hip'] ?? 25)];
        };

        [$trumpetY, $trumpetX, $trumpetHip] = $narrowest('gown_trumpet');
        [$mermaidY, $mermaidX, $mermaidHip] = $narrowest('evening_mermaid');

        $this->assertLessThan($mermaidY, $trumpetY, 'خط باز شدن ترامپت باید بالاتر از ماهی باشد.');
        $this->assertGreaterThan(
            $mermaidX - $mermaidHip,
            $trumpetX - $trumpetHip,
            'تنگی ران در ترامپت باید ملایم‌تر از ماهی باشد.',
        );
    }

    public function test_the_corset_dress_carries_boning_channels_and_a_waist_stay(): void
    {
        $pieces = $this->build('dress_corset');

        $this->assertNotEmpty(
            array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'boning-channel')),
            'کرست کانال تیغهٔ فنر می‌خواهد.',
        );
        $this->assertNotEmpty(
            array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'waist-stay')),
            'کشش کرست باید روی نوار کمر داخلی بیفتد، نه روی درزها.',
        );

        $blades = 0;

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                if (str_contains((string) ($notion['label'] ?? ''), 'تیغهٔ فنر')) {
                    $blades += (int) ($notion['count'] ?? 0);
                }
            }
        }

        $this->assertGreaterThanOrEqual(4, $blades, 'شمار تیغه‌های فنر باید در صورت مواد بیاید.');

        // کمرِ جمع‌شده روی هر دو قطعه اعمال می‌شود، وگرنه دو کمر به هم نمی‌رسند
        $tight = $this->build('dress_corset', '40', ['waist_reduction' => 8]);
        $loose = $this->build('dress_corset', '40', ['waist_reduction' => 0]);

        $this->assertLessThan(
            $this->girth($loose, 'waist', ['front_bodice', 'back_bodice']) - 5,
            $this->girth($tight, 'waist', ['front_bodice', 'back_bodice']),
            'جمع‌شدن کمر باید واقعاً کمر را کوچک کند.',
        );

        $this->assertEqualsWithDelta(
            $this->girth($tight, 'waist', ['front_bodice', 'back_bodice']),
            $this->girth($tight, 'waist', ['skirt_front', 'skirt_back']),
            1.0,
            'کمرِ جمع‌شده باید روی دامن هم اعمال شود.',
        );
    }

    public function test_the_bridesmaid_dress_can_be_fitted_to_more_than_one_body(): void
    {
        $pieces = $this->build('dress_bridesmaid');

        $sash = array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'sash'));
        $this->assertNotEmpty($sash, 'لباس ساقدوش بند کمر دارد.');

        $shell = $this->parts($pieces, ['front_bodice', 'back_bodice']);
        $this->assertNotEmpty($shell);

        foreach ($shell as $piece) {
            $this->assertGreaterThan(0.0, (float) ($piece['meta']['fit_allowance'] ?? 0), 'درزها باید جای رهاشده برای پرو داشته باشند.');
        }

        $this->assertStringContainsString('پرو', $this->notes($pieces));
    }

    public function test_the_flower_girl_dress_works_on_a_child_body(): void
    {
        $child = $this->body('کودک');
        $pieces = $this->build('dress_flower_girl', 'کودک');

        // بالاتنه واقعاً روی بدن کودک باشد، نه روی بدن بزرگسالِ پیش‌فرض
        $bust = 0.0;

        foreach ($this->parts($pieces, ['front_bodice', 'back_bodice']) as $piece) {
            foreach ($piece['markers'] ?? [] as $marker) {
                if (($marker['key'] ?? '') === 'bust') {
                    $bust += abs(((float) $marker['to']['x']) - ((float) $marker['from']['x']))
                        * max(1, (int) $piece['cut_quantity']) * (empty($piece['on_fold']) ? 1 : 2);
                }
            }
        }

        $this->assertEqualsWithDelta((float) $child['bust'] + 8, $bust, 8.0, 'بالاتنه باید روی دور سینهٔ همین کودک ساخته شود.');

        // کمرِ بندی و دامن پُر
        $this->assertNotEmpty(
            array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'sash')),
            'لباس گل‌دختر کمرِ بندی دارد.',
        );

        $skirt = $this->parts($pieces, ['skirt_front', 'skirt_back']);
        $this->assertNotEmpty($skirt, 'لباس گل‌دختر دامن دارد.');

        $fabric = 0.0;

        foreach ($skirt as $piece) {
            $fabric += Geometry::width($piece['outline']) * max(1, (int) $piece['cut_quantity']) * (empty($piece['on_fold']) ? 1 : 2);
        }

        $this->assertGreaterThan(
            $this->girth($pieces, 'waist', ['front_bodice', 'back_bodice']) * 1.4,
            $fabric,
            'دامن گل‌دختر باید دست‌کم یک و نیم برابر کمر پارچه بخورد.',
        );

        // نه فنر، نه جای فنجان سینه
        foreach ($pieces as $piece) {
            $this->assertFalse((bool) ($piece['meta']['boning'] ?? false), 'لباس کودک تیغهٔ فنر نمی‌خواهد.');
            $this->assertNotSame('cup_pocket', $piece['meta']['part'] ?? null, 'لباس کودک جای فنجان سینه نمی‌خواهد.');
        }
    }

    /* ---------------------------------------------------------------------
     |  کمک‌کار
     * ------------------------------------------------------------------- */

    /** پهنای قطعه روی یک ارتفاع مشخص. */
    protected function widthAt(array $piece, float $y): float
    {
        $points = Geometry::flatten($piece['outline']);
        $count = count($points);
        $xs = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $ay = (float) $a['y'];
            $by = (float) $b['y'];

            if (($ay <= $y && $by >= $y) || ($by <= $y && $ay >= $y)) {
                $span = $by - $ay;
                $t = abs($span) < 1e-6 ? 0.0 : ($y - $ay) / $span;
                $xs[] = ((float) $a['x']) + ((((float) $b['x']) - ((float) $a['x'])) * $t);
            }
        }

        return $xs === [] ? 0.0 : max($xs) - min($xs);
    }
}
