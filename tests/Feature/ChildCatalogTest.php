<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاتالوگ لباس کودک.
 *
 * این آزمون یک فرض را نمی‌پذیرد: اینکه لباس کودک همان لباس بزرگسال کوچک‌شده
 * است. برای همین همه سنجش‌های اصلی روی بدن کودکِ واقعی انجام می‌شود، نه روی
 * سایز ۴۰. سه چیزی که فقط روی بدن کودک خودش را نشان می‌دهد:
 *
 *   ۱. سر بزرگ است. یقه‌ای که روی بزرگسال جا دارد، روی کودک از سر رد نمی‌شود.
 *   ۲. کمر فرورفتگی ندارد. بلوکی که کمر را می‌گیرد روی شکم کودک می‌ایستد.
 *   ۳. کمر کشی باید از باسن رد شود. اختلاف کمر و باسنِ کودک کم است، ولی صفر
 *      نیست؛ اگر لبه کمر به اندازه کمر بریده شود، شلوار بالا نمی‌آید.
 *
 * نکته آزمونی: Measurements::fromSize() فقط سایز ۳۴ تا ۴۸ را می‌شناسد و برای
 * کلید ناشناخته بی‌صدا سایز ۴۰ را برمی‌گرداند. پس بدن کودک این‌جا آرایه صریح
 * است، وگرنه این آزمون در واقع دوباره بزرگسال را می‌سنجید.
 */
class ChildCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** کلیدهای این خانواده. */
    protected const MODELS = [
        'child_bodysuit',
        'child_playsuit',
        'child_pinafore_dress',
        'child_school_uniform',
        'child_pajama',
    ];

    /**
     * بدن‌های آزمون؛ همه با آرایه صریح.
     *
     * @var array<string, array<string, float|int>>
     */
    protected const BESPOKE = [
        'نوزاد' => ['height' => 74, 'bust' => 48, 'waist' => 48, 'hip' => 50, 'shoulder_width' => 20, 'arm_length' => 24],
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'نوجوان' => ['height' => 146, 'bust' => 74, 'waist' => 66, 'hip' => 76, 'shoulder_width' => 33, 'arm_length' => 50],
    ];

    /** بدن‌های کودکِ واقعی — سنجش‌های اصلی روی این‌هاست. */
    protected const CHILD_BODIES = ['نوزاد', 'کودک', 'نوجوان'];

    /** پنج بدنی که هر مدل باید رویشان بایستد (دو تای آخر بزرگسال، برای پایداری). */
    protected const BODIES = ['نوزاد', 'کودک', 'نوجوان', '34', '48'];

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
    protected function build(string $key, string $size = 'کودک', array $params = []): array
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

    /** دور تمام‌شده یک لبه برچسب‌خورده روی این دسته قطعه‌ها. */
    protected function girthOfTag(array $pieces, array $parts, string $tag): float
    {
        $total = 0.0;

        foreach ($this->parts($pieces, $parts) as $piece) {
            $edges = Geometry::edgesWithTag($piece, $tag);

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
        $this->assertArrayHasKey('child', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('child');

        foreach (static::MODELS as $key) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در گروه لباس کودک نیست.");
            $this->assertNotSame('', GeneratorRegistry::make($key)->label());
        }
    }

    public function test_the_test_bodies_are_really_children(): void
    {
        // اگر این آزمون بیفتد یعنی بقیه آزمون‌ها در واقع بزرگسال را سنجیده‌اند
        foreach (static::CHILD_BODIES as $name) {
            $body = $this->body($name);

            $this->assertLessThan(150, $body['height'], "بدن «{$name}» قد کودک ندارد.");
            $this->assertLessThan(80, $body['bust'], "بدن «{$name}» دور سینه کودک ندارد.");
            $this->assertLessThan(
                12,
                $body['hip'] - $body['waist'],
                "بدن «{$name}» اختلاف کمر و باسنش مثل بزرگسال است؛ کودک کمر فرورفته ندارد.",
            );
        }
    }

    public function test_every_model_builds_a_clean_closed_pattern_on_five_bodies(): void
    {
        $allowed = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'strap', 'default'];

        foreach (static::MODELS as $key) {
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

    public function test_front_and_back_side_seams_walk_to_the_same_length(): void
    {
        foreach (static::MODELS as $key) {
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

    public function test_the_sleeve_cap_matches_the_measured_armhole(): void
    {
        foreach (static::MODELS as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);
                $sleeves = $this->parts($pieces, ['sleeve']);

                if ($sleeves === []) {
                    continue;
                }

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
     |  سرِ کودک
     * ------------------------------------------------------------------- */

    public function test_every_pull_on_garment_lets_the_head_through(): void
    {
        foreach (static::MODELS as $key) {
            foreach (static::CHILD_BODIES as $size) {
                $clearance = null;

                foreach ($this->build($key, $size) as $piece) {
                    $clearance = $piece['meta']['head_clearance'] ?? $clearance;
                }

                if ($key === 'child_school_uniform') {
                    // فرم مدرسه پیش‌فرض جلوباز است و از سر پوشیده نمی‌شود
                    continue;
                }

                $this->assertIsArray($clearance, "«{$key}» روی «{$size}» چیزی درباره رد شدن از سر نگفته است.");
                $this->assertGreaterThan(0, (float) $clearance['head'], 'دور سر تخمینی باید عدد باشد.');

                $this->assertTrue(
                    $clearance['passes'] || $clearance['slit'] > 0,
                    sprintf(
                        '%s|%s دور یقه %.1f و دور سر %.1f است و هیچ چاکی هم باز نشده؛ لباس از سر رد نمی‌شود.',
                        $key, $size, $clearance['neck_girth'], $clearance['head'],
                    ),
                );

                if ($clearance['passes']) {
                    $this->assertGreaterThan(
                        (float) $clearance['head'],
                        (float) $clearance['neck_girth'],
                        "«{$key}»|{$size} ادعا می‌کند از سر رد می‌شود ولی یقه‌اش از دور سر کوچک‌تر است.",
                    );
                }
            }
        }
    }

    public function test_the_estimated_head_girth_is_declared_honestly(): void
    {
        $notes = implode(' ', $this->build('child_bodysuit', 'کودک')[0]['meta']['notes'] ?? []);

        $this->assertStringContainsString(
            'دور سر',
            $notes,
            'دور سر در اندازه‌های سامانه نیست؛ تخمین باید روی خود الگو نوشته شود.',
        );
        $this->assertStringContainsString('تخمین', $notes, 'باید گفته شود که این عدد تخمینی است، نه اندازه‌گرفته‌شده.');
    }

    public function test_the_head_grows_slower_than_the_body(): void
    {
        // همین نسبت است که لباس کودک را از لباس بزرگسالِ کوچک‌شده جدا می‌کند:
        // بدنِ دو برابر، سرِ دو برابر ندارد
        $ratio = function (string $size): float {
            $body = $this->body($size);

            foreach ($this->build('child_pajama', $size) as $piece) {
                if (isset($piece['meta']['head_clearance'])) {
                    return (float) $piece['meta']['head_clearance']['head'] / (float) $body['bust'];
                }
            }

            return 0.0;
        };

        $baby = $ratio('نوزاد');
        $teen = $ratio('نوجوان');

        $this->assertGreaterThan(0, $teen);
        $this->assertGreaterThan(
            $teen * 1.15,
            $baby,
            'هرچه کودک کوچک‌تر، سرش نسبت به تنش بزرگ‌تر؛ همین نسبت است که یقه لباس نوزاد را تعیین می‌کند.',
        );
    }

    /* ---------------------------------------------------------------------
     |  بدن کودک، نه بزرگسالِ کوچک‌شده
     * ------------------------------------------------------------------- */

    public function test_no_child_bodice_takes_the_waist_in_with_a_dart(): void
    {
        foreach (static::MODELS as $key) {
            foreach (static::CHILD_BODIES as $size) {
                foreach ($this->parts($this->build($key, $size), ['front_bodice', 'back_bodice']) as $piece) {
                    foreach ($piece['darts'] ?? [] as $dart) {
                        $this->assertNotSame(
                            'waist',
                            $dart['type'] ?? '',
                            "«{$key}»|{$size} ساسون کمر دارد؛ کمر کودک فرورفتگی ندارد که ساسون بگیرد.",
                        );
                    }

                    $this->assertLessThanOrEqual(
                        0.01,
                        (float) ($piece['meta']['waist_dart_intake'] ?? 0),
                        "«{$key}»|{$size} کمر را با ساسون گرفته است.",
                    );
                }
            }
        }
    }

    public function test_an_elastic_waist_bottom_still_goes_over_the_hips(): void
    {
        foreach (['child_playsuit', 'child_pajama', 'child_school_uniform'] as $key) {
            foreach (static::CHILD_BODIES as $size) {
                $body = $this->body($size);
                $pieces = $this->build($key, $size);
                $waist = $this->girthOfTag($pieces, ['front_leg', 'back_leg'], 'waist');

                $this->assertGreaterThan(0, $waist, "«{$key}»|{$size} پاچه با لبه کمر ندارد.");
                $this->assertGreaterThan(
                    (float) $body['hip'],
                    $waist,
                    sprintf(
                        '%s|%s لبه کمر %.1f و دور باسن %.1f است؛ شلوار کمر کشی از باسن رد نمی‌شود.',
                        $key, $size, $waist, $body['hip'],
                    ),
                );
            }
        }
    }

    public function test_the_elastic_is_cut_shorter_than_the_edge_it_gathers(): void
    {
        foreach (['child_playsuit', 'child_pajama', 'child_school_uniform'] as $key) {
            $band = null;

            foreach ($this->build($key, 'کودک') as $piece) {
                if (str_contains((string) $piece['code'], 'waist-elastic')) {
                    $band = $piece;
                }
            }

            $this->assertNotNull($band, "«{$key}» نوار کش کمر ندارد.");
            $this->assertLessThan(1.0, (float) $band['meta']['stretch_ratio'], 'کش باید کوتاه‌تر از لبه باشد.');
            $this->assertLessThan(
                (float) $band['meta']['target_length'],
                Geometry::width($band['outline']),
                'نوار کش باید کوتاه‌تر از دور کمر بریده شود، وگرنه چیزی را جمع نمی‌کند.',
            );
        }
    }

    public function test_a_child_pattern_is_not_just_a_shrunken_size_forty(): void
    {
        // اگر مدل اندازه‌ها را نادیده بگیرد، الگوی کودک و بزرگسال یکی درمی‌آید
        $bustOf = function (array $pieces): float {
            $total = 0.0;

            foreach ($pieces as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                    continue;
                }

                $total += (float) ($piece['meta']['girth']['bust'] ?? 0) * (int) ($piece['meta']['girth_factor'] ?? 1);
            }

            return round($total, 2);
        };

        foreach (static::MODELS as $key) {
            $child = $bustOf($this->build($key, 'کودک'));
            $adult = $bustOf($this->build($key, '48'));

            $this->assertGreaterThan(0, $child, "«{$key}» دور سینه تمام‌شده اعلام نمی‌کند.");
            $this->assertGreaterThan($child * 1.4, $adult, "«{$key}» به اندازه بدن اعتنا نمی‌کند.");
            $this->assertGreaterThan(
                (float) $this->body('کودک')['bust'],
                $child,
                "«{$key}» روی بدن کودک تنگ‌تر از خودِ بدن درآمده است.",
            );
        }
    }

    /* ---------------------------------------------------------------------
     |  تفاوت مدل‌ها
     * ------------------------------------------------------------------- */

    public function test_the_bodysuit_opens_at_the_crotch_with_snaps(): void
    {
        foreach (static::CHILD_BODIES as $size) {
            $pieces = $this->build('child_bodysuit', $size);
            $snaps = $this->notions($pieces, 'snap');

            $this->assertNotEmpty($snaps, "بادی روی «{$size}» باید قزن فاق داشته باشد؛ بدون آن پوشک عوض نمی‌شود.");
            $this->assertGreaterThanOrEqual(3, (int) $snaps[0]['count'], 'ردیف قزن دست‌کم سه تاست.');

            $front = $this->parts($pieces, ['front_bodice'])[0];
            $back = $this->parts($pieces, ['back_bodice'])[0];

            $this->assertGreaterThan(
                Geometry::height($back['outline']),
                Geometry::height($front['outline']),
                'زبانه فاق جلو باید بلندتر از پشت باشد تا رویش بیفتد و قزن جا بگیرد.',
            );

            $this->assertNotEmpty($front['drills'], 'جای قزن باید روی الگو علامت خورده باشد.');
        }
    }

    public function test_the_bodysuit_leaves_a_curved_leg_line_for_the_nappy(): void
    {
        $pieces = $this->build('child_bodysuit', 'نوزاد');
        $front = $this->parts($pieces, ['front_bodice'])[0];

        $legEdge = (int) ($front['meta']['leg_edge'] ?? -1);
        $this->assertGreaterThanOrEqual(0, $legEdge, 'بادی باید بگوید کدام لبه‌اش خط پاست.');

        $outline = $front['outline'];
        $from = Geometry::pointOnEdge($outline, $legEdge, 0.0);
        $to = Geometry::pointOnEdge($outline, $legEdge, 1.0);
        $chord = Geometry::distance($from, $to);

        $this->assertGreaterThan(
            $chord + 0.5,
            Geometry::edgeLength($outline, $legEdge),
            'خط پا باید منحنی باشد؛ خط صاف لای پا را نمی‌بُرد و پوشک جا نمی‌شود.',
        );

        $this->assertGreaterThan(
            0,
            (float) ($front['meta']['crotch_tab'] ?? 0),
            'بادی باید زبانه فاق داشته باشد.',
        );
    }

    public function test_the_school_uniform_is_a_tunic_plus_a_bottom(): void
    {
        $pants = $this->build('child_school_uniform', 'کودک', ['bottom' => 'pants']);
        $skirt = $this->build('child_school_uniform', 'کودک', ['bottom' => 'skirt']);
        $alone = $this->build('child_school_uniform', 'کودک', ['bottom' => 'none']);

        $this->assertNotEmpty($this->parts($alone, ['front_bodice']), 'تونیک همیشه هست.');
        $this->assertSame([], $this->parts($alone, ['front_leg', 'skirt_front']), 'گزینه «فقط تونیک» نباید پایین‌تنه بسازد.');

        $this->assertCount(2, $this->parts($pants, ['front_leg', 'back_leg']), 'گزینه شلوار باید دو پاچه بسازد.');
        $this->assertSame([], $this->parts($pants, ['skirt_front', 'skirt_back']));

        $this->assertCount(2, $this->parts($skirt, ['skirt_front', 'skirt_back']), 'گزینه دامن باید دو پنل دامن بسازد.');
        $this->assertSame([], $this->parts($skirt, ['front_leg', 'back_leg']));

        // دامن کمر کشی هم باید از باسن رد شود
        $body = $this->body('کودک');
        $cut = 0.0;

        foreach ($this->parts($skirt, ['skirt_front', 'skirt_back']) as $panel) {
            $cut += PieceOps::edgeLength($panel, 'waist') * (empty($panel['on_fold']) ? 1 : 2)
                * (int) ($panel['cut_quantity'] ?? 1);
        }

        $this->assertGreaterThan((float) $body['hip'], $cut, 'دامن کمر کشی باید از باسن رد شود.');
    }

    public function test_gathers_are_recorded_where_a_generic_measurer_can_see_them(): void
    {
        foreach ([
            ['child_pinafore_dress', ['gather_ratio' => 2.0]],
            ['child_school_uniform', ['bottom' => 'skirt']],
        ] as [$key, $params]) {
            $panels = $this->parts($this->build($key, 'کودک', $params), ['skirt_front', 'skirt_back']);

            $this->assertNotEmpty($panels, "«{$key}» پنل دامن ندارد.");

            foreach ($panels as $panel) {
                $gathers = $panel['meta']['gathers'] ?? [];

                $this->assertNotEmpty($gathers, "«{$key}»|{$panel['code']} چین را در meta.gathers ثبت نکرده است.");
                $this->assertGreaterThan(0, (float) $gathers[0]['amount']);
                $this->assertSame('waist', $gathers[0]['tag'], 'چین باید روی لبه کمر ثبت شود.');

                // یک چین دو بار شمرده نمی‌شود: طول دوخته‌شده باید کوتاه‌تر از
                // طول بریده‌شده باشد، ولی فقط به اندازه همان یک چین
                $raw = PieceOps::edgeLength($panel, 'waist');
                $sewn = PieceOps::seamLength($panel, 'waist');

                $this->assertEqualsWithDelta(
                    $raw - (float) $gathers[0]['amount'],
                    $sewn,
                    0.05,
                    "«{$key}»|{$panel['code']} چین دو بار از لبه کم شده است.",
                );
            }
        }
    }

    public function test_the_pinafore_is_roomier_than_the_pyjama_top(): void
    {
        $bustOf = function (array $pieces): float {
            $total = 0.0;

            foreach ($pieces as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                    continue;
                }

                $total += (float) ($piece['meta']['girth']['bust'] ?? 0) * (int) ($piece['meta']['girth_factor'] ?? 1);
            }

            return round($total, 2);
        };

        $this->assertGreaterThan(
            $bustOf($this->build('child_pajama', 'کودک')),
            $bustOf($this->build('child_pinafore_dress', 'کودک')),
            'سارافون روی پیراهن پوشیده می‌شود، پس باید از بالاتنه پیژامه گشادتر باشد.',
        );

        $pinafore = $this->build('child_pinafore_dress', 'کودک');
        $this->assertNotEmpty($this->parts($pinafore, ['skirt_front', 'skirt_back']), 'سارافون دامن دارد.');
        $this->assertSame([], $this->parts($pinafore, ['sleeve']), 'سارافون بی‌آستین است.');
    }

    public function test_the_pinafore_waist_seam_matches_between_bodice_and_skirt(): void
    {
        foreach (static::CHILD_BODIES as $size) {
            $pieces = $this->build('child_pinafore_dress', $size);

            $upper = $this->girthOfTag($pieces, ['front_bodice', 'back_bodice'], 'waist');
            // seamLength همان طولی است که خیاط با متر روی درز اندازه می‌گیرد:
            // طول لبه منهای چینی که رویش بسته می‌شود. اگر چین در meta.gathers
            // ثبت نشده باشد، این عدد جور درنمی‌آید.
            $lower = $this->girthOfTag($pieces, ['skirt_front', 'skirt_back'], 'waist');

            $this->assertGreaterThan(0, $upper);
            $this->assertEqualsWithDelta(
                $upper,
                $lower,
                1.0,
                "سارافون روی «{$size}»: کمر بالاتنه و کمر دامن به هم دوخته می‌شوند و باید هم‌اندازه باشند.",
            );
        }
    }

    public function test_the_playsuit_waist_seam_matches_between_bodice_and_shorts(): void
    {
        foreach (static::CHILD_BODIES as $size) {
            $pieces = $this->build('child_playsuit', $size);

            $upper = $this->girthOfTag($pieces, ['front_bodice', 'back_bodice'], 'waist');
            $lower = $this->girthOfTag($pieces, ['front_leg', 'back_leg'], 'waist');

            $this->assertGreaterThan(0, $upper);
            $this->assertEqualsWithDelta(
                $upper,
                $lower,
                1.0,
                "سرهمی کودک روی «{$size}»: کمر بالاتنه و کمر شورت باید هم‌اندازه باشند.",
            );
        }
    }

    public function test_the_pyjama_has_no_hard_fastening(): void
    {
        $pieces = $this->build('child_pajama', 'کودک');

        $this->assertSame([], $this->notions($pieces, 'zip'), 'زیپ زیر بدنِ خوابیده روی پوست جا می‌اندازد.');
        $this->assertSame([], $this->notions($pieces, 'button'), 'پیژامه دکمه ندارد.');

        $this->assertNotEmpty($this->parts($pieces, ['front_leg', 'back_leg']), 'پیژامه شلوار هم دارد.');
        $this->assertNotEmpty($this->parts($pieces, ['sleeve']), 'پیژامه آستین بلند دارد.');
    }
}
