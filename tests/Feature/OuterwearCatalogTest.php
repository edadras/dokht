<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * کاتالوگ کت، پالتو و کاپشن.
 *
 * جای خطای این خانواده با بقیهٔ کاتالوگ فرق دارد. لباس شب روی خط کمر می‌شکند و
 * پیراهن روی حلقهٔ آستین؛ لباس رویی روی **آزادی** می‌شکند. این لباس‌ها روی لباس
 * دیگری پوشیده می‌شوند و اگر آزادی‌شان به اندازهٔ همان لایهٔ زیر نباشد، الگو روی
 * کاغذ بی‌عیب است و روی تن، آستین از حلقه بیرون می‌زند و دکمه بسته نمی‌شود.
 *
 * پس این آزمون سه چیز را می‌پاید:
 *
 *   ۱. هر مدل بگوید با چه آزادی‌ای بریده شده، و اندازهٔ تمام‌شده‌اش واقعاً همان
 *      عدد باشد — نه بیشتر و نه کمتر.
 *   ۲. آن آزادی از پیراهنی که زیرش پوشیده می‌شود بیشتر باشد.
 *   ۳. هر مدل واقعاً همان مدل باشد، نه یک کت با نام تازه: پی‌کت یقهٔ پهن و دو
 *      ردیف دکمه داشته باشد، دافل بند و کلاه، پافر خطوط کاناله، و شنل اصلاً
 *      حلقهٔ آستین نداشته باشد.
 */
class OuterwearCatalogTest extends TestCase
{
    /** چهارده مدل این خانواده: کلید ⇒ نام فارسی. */
    protected const MODELS = [
        'jacket_double_breasted' => 'ژاکت دوردیف‌دکمه',
        'jacket_cropped' => 'ژاکت کوتاه',
        'coat_overcoat' => 'پالتو بلند',
        'coat_peacoat' => 'پالتو پی‌کت',
        'coat_duffle' => 'پالتو دافل',
        'coat_wrap' => 'پالتو راپ (بندی)',
        'coat_cape' => 'شنل',
        'jacket_biker' => 'کاپشن بایکر',
        'jacket_puffer' => 'کاپشن پافر',
        'jacket_parka' => 'پارکا',
        'jacket_anorak' => 'آنوراک',
        'jacket_windbreaker' => 'بادگیر',
        'jacket_work' => 'کت کار',
        'vest_utility' => 'جلیقه کارگو',
    ];

    /**
     * شش بدنی که هر مدل باید رویشان بایستد.
     *
     * سه تای اول از جدول سایز است و سه تای بعدی سفارشی. بدن سفارشی صریح نوشته
     * شده و از fromSize گرفته نمی‌شود، چون fromSize برای هر کلید ناشناخته
     * **بی‌صدا سایز ۴۰ را برمی‌گرداند**؛ آزمونی که با آن نوشته شود خیال می‌کند
     * روی بدن کودک سنجیده و در واقع دوباره همان سایز ۴۰ را سنجیده است.
     */
    protected const BODIES = ['34', '40', '48', 'کودک', 'بلندقد', 'سینه‌درشت'];

    /** اندازهٔ بدن‌های سفارشی (سانتی‌متر)؛ عمداً «سخت» انتخاب شده‌اند. */
    protected const BESPOKE = [
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'بلندقد' => ['height' => 195, 'bust' => 84, 'waist' => 66, 'hip' => 90, 'shoulder_width' => 44, 'arm_length' => 72],
        'سینه‌درشت' => ['height' => 168, 'bust' => 118, 'waist' => 70, 'hip' => 100, 'shoulder_width' => 34, 'arm_length' => 59],
    ];

    /** برچسب‌های مجاز لبه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'strap', 'default'];

    /* ---------------------------------------------------------------------
     |  کمک‌کارها
     * ------------------------------------------------------------------- */

    /** @return array<string, float> */
    protected function body(string $name): array
    {
        if (isset(static::BESPOKE[$name])) {
            return Measurements::complete(static::BESPOKE[$name]);
        }

        $this->assertArrayHasKey(
            $name,
            Measurements::SIZE_TABLE,
            "بدن «{$name}» نه سایز جدولی است نه بدن سفارشی این آزمون.",
        );

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
    protected function partsLike(array $pieces, array $parts): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => in_array((string) ($piece['meta']['part'] ?? ''), $parts, true),
        ));
    }

    protected function notes(array $pieces): string
    {
        $notes = [];

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notes'] ?? [] as $note) {
                $notes[] = (string) $note;
            }
        }

        return implode(' | ', $notes);
    }

    /** @return array<int, array<string, mixed>> */
    protected function notions(array $pieces): array
    {
        $rows = [];

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                $rows[] = $notion;
            }
        }

        return $rows;
    }

    /** آیا یراقی با این نوع و این تکه‌متن در برچسبش هست؟ */
    protected function hasNotion(array $pieces, string $type, string $needle = ''): bool
    {
        foreach ($this->notions($pieces) as $notion) {
            if (($notion['type'] ?? '') !== $type) {
                continue;
            }

            if ($needle === '' || str_contains((string) ($notion['label'] ?? ''), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * اندازهٔ تمام‌شدهٔ سینه، کمر و باسن از روی خط‌های نشانه.
     *
     * فقط وقتی شمرده می‌شود که هم جلو و هم پشت آن خط را داشته باشند، وگرنه تنها
     * نیمی از دور لباس اندازه گرفته می‌شود.
     *
     * @return array<string, float>
     */
    protected function girths(array $pieces): array
    {
        $totals = [];
        $sides = [];

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (! in_array($part, ['front_bodice', 'back_bodice'], true)) {
                continue;
            }

            $side = str_contains($part, 'front') ? 'front' : 'back';
            $repeats = (int) ($piece['cut_quantity'] ?? 1) * (empty($piece['on_fold']) ? 1 : 2);

            foreach ($piece['markers'] ?? [] as $marker) {
                $area = (string) ($marker['key'] ?? '');

                if (! in_array($area, ['bust', 'waist', 'hip'], true)) {
                    continue;
                }

                $width = abs(((float) $marker['to']['x']) - ((float) $marker['from']['x']));

                if ($width < 0.01) {
                    continue;
                }

                $totals[$area] = ($totals[$area] ?? 0.0) + ($width * $repeats);
                $sides[$area][$side] = true;
            }
        }

        $girths = [];

        foreach ($totals as $area => $value) {
            if (count($sides[$area] ?? []) === 2) {
                $girths[$area] = round($value, 2);
            }
        }

        return $girths;
    }

    /** آزادی اعلام‌شدهٔ یک الگو (از روی پنل‌های پوسته). */
    protected function declaredEase(array $pieces): array
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['girth_role'] ?? '') === 'shell' && isset($piece['meta']['cut_ease'])) {
                return $piece['meta']['cut_ease'];
            }
        }

        return [];
    }

    /** طول کل حلقهٔ آستین روی قطعه‌هایی که سهمی از آن دارند. */
    protected function armholeOf(array $pieces): float
    {
        $total = 0.0;

        foreach ($this->partsLike($pieces, ['front_bodice', 'back_bodice', 'yoke']) as $piece) {
            foreach (Geometry::edgesWithTag($piece, 'armhole') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge);
            }
        }

        return round($total, 2);
    }

    /** طول کل سرآستین روی قطعه‌های آستین. */
    protected function capOf(array $pieces): float
    {
        $total = 0.0;

        foreach ($this->partsLike($pieces, ['sleeve']) as $piece) {
            foreach (Geometry::edgesWithTag($piece, 'armhole') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge);
            }
        }

        return round($total, 2);
    }

    /* ---------------------------------------------------------------------
     |  الف) خانواده و سلامت هندسی
     * ------------------------------------------------------------------- */

    public function test_the_family_covers_all_fourteen_outerwear_models(): void
    {
        $this->assertArrayHasKey('outerwear', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('outerwear');

        foreach (static::MODELS as $key => $label) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در گروه کت، پالتو و کاپشن نیست.");
            $this->assertSame($label, $group[$key], "نام فارسی «{$key}» درست نیست.");
            $this->assertSame('outerwear', GeneratorRegistry::groupOf($key));
        }

        $this->assertGreaterThanOrEqual(14, count($group));
    }

    public function test_every_model_builds_a_sound_pattern_on_six_bodies(): void
    {
        foreach (array_keys(static::MODELS) as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);
                $this->assertNotEmpty($pieces, "«{$key}» روی «{$size}» قطعه‌ای نساخت.");

                $codes = array_column($pieces, 'code');
                $this->assertSame(
                    count($codes),
                    count(array_unique($codes)),
                    "{$key}|{$size} کد قطعهٔ تکراری دارد.",
                );

                foreach ($pieces as $piece) {
                    $where = "{$key}|{$size}|{$piece['code']}";
                    $outline = array_values($piece['outline'] ?? []);

                    $this->assertGreaterThanOrEqual(3, count($outline), "{$where} مسیر بسته ندارد.");
                    $this->assertFalse(
                        Geometry::selfIntersects($outline),
                        "{$where} مسیرش خودش را قطع می‌کند و بریده نمی‌شود.",
                    );

                    $tags = $piece['meta']['edges'] ?? null;
                    $this->assertIsArray($tags, "{$where} برچسب لبه ندارد.");
                    $this->assertCount(count($outline), $tags, "{$where} شمار برچسب لبه با شمار لبه‌ها جور نیست.");

                    foreach ($tags as $index => $tag) {
                        $this->assertContains($tag, static::EDGE_TAGS, "{$where} لبهٔ {$index} برچسب ناشناخته دارد.");
                    }

                    foreach ($piece['meta']['fold_edges'] ?? [] as $edge) {
                        $this->assertIsInt($edge);
                        $this->assertGreaterThanOrEqual(0, $edge);
                        $this->assertLessThan(count($outline), $edge, "{$where} لبهٔ تای پارچه بیرون مسیر است.");
                    }

                    $this->assertNotEmpty($piece['meta']['girth_role'] ?? null, "{$where} نقش دور بدن ندارد.");
                    $this->assertContains(
                        $piece['meta']['girth_role'],
                        ['shell', 'lining', 'trim', 'sleeve', 'skirt', 'lining_skirt'],
                        "{$where} نقش دور بدنش شناخته‌شده نیست.",
                    );

                    [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);

                    foreach ($piece['markers'] ?? [] as $marker) {
                        foreach (['from', 'to'] as $end) {
                            $this->assertLessThanOrEqual($maxX + 0.05, (float) $marker[$end]['x'], "{$where} خط نشانه بیرون کادر است.");
                            $this->assertGreaterThanOrEqual($minX - 0.05, (float) $marker[$end]['x']);
                            $this->assertLessThanOrEqual($maxY + 0.05, (float) $marker[$end]['y']);
                            $this->assertGreaterThanOrEqual($minY - 0.05, (float) $marker[$end]['y']);
                        }
                    }
                }
            }
        }
    }

    public function test_front_and_back_side_seams_walk_to_the_same_length(): void
    {
        foreach (array_keys(static::MODELS) as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);
                $fronts = $this->partsLike($pieces, ['front_bodice']);
                $backs = $this->partsLike($pieces, ['back_bodice']);

                $this->assertCount(1, $fronts, "«{$key}» باید یک تنهٔ جلو داشته باشد.");
                $this->assertCount(1, $backs, "«{$key}» باید یک تنهٔ پشت داشته باشد.");

                $walk = PieceOps::walk($fronts[0], 'side', $backs[0], 'side', ['tolerance' => 0.15]);

                $this->assertTrue(
                    $walk['matched'],
                    sprintf(
                        '%s|%s درز پهلوی جلو %.2f و پشت %.2f است؛ اختلاف %.2f سانتی‌متر و این دو به هم دوخته می‌شوند.',
                        $key, $size, $walk['a']['seam'], $walk['b']['seam'], $walk['difference'],
                    ),
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  ب) آستین از حلقهٔ همین لباس
     * ------------------------------------------------------------------- */

    public function test_every_sleeve_is_drafted_from_the_armhole_it_actually_has(): void
    {
        $checked = 0;

        foreach (array_keys(static::MODELS) as $key) {
            foreach (static::BODIES as $size) {
                $pieces = $this->build($key, $size);
                $sleeves = $this->partsLike($pieces, ['sleeve']);

                if ($sleeves === []) {
                    // شنل و جلیقه عمداً آستین ندارند
                    $this->assertContains($key, ['coat_cape', 'vest_utility'], "«{$key}» آستین ندارد و نگفته چرا.");

                    continue;
                }

                $checked++;
                $armhole = $this->armholeOf($pieces);
                $cap = $this->capOf($pieces);

                $this->assertGreaterThan(0, $armhole, "{$key}|{$size} آستین دارد ولی تنه حلقه ندارد.");

                // آستین از حلقهٔ اندازه‌گیری‌شدهٔ همین قطعه‌ها درفت شده، نه از عددی ثابت
                $target = null;

                foreach ($sleeves as $sleeve) {
                    if (isset($sleeve['meta']['target_armhole'])) {
                        $target = (float) $sleeve['meta']['target_armhole'];
                    }
                }

                $this->assertNotNull($target, "{$key}|{$size} آستین نمی‌گوید برای چه حلقه‌ای بریده شده.");
                $this->assertEqualsWithDelta(
                    $armhole,
                    $target,
                    max(0.5, $armhole * 0.02),
                    "{$key}|{$size} حلقهٔ اعلام‌شدهٔ آستین ({$target}) با حلقهٔ واقعی تنه ({$armhole}) جور نیست.",
                );

                $ease = $cap - $armhole;

                $this->assertGreaterThan(
                    -0.5,
                    $ease,
                    sprintf('%s|%s سرآستین %.1f از حلقه %.1f کوتاه‌تر است و در آن نمی‌نشیند.', $key, $size, $cap, $armhole),
                );
                $this->assertLessThanOrEqual(
                    max(6.0, $armhole * 0.25),
                    $ease,
                    sprintf('%s|%s آزادی سرآستین %.1f سانتی‌متر است؛ بیش از یک‌چهارم حلقه یعنی درفت از دست رفته.', $key, $size, $ease),
                );
            }
        }

        $this->assertGreaterThan(50, $checked);
    }

    /* ---------------------------------------------------------------------
     |  پ) آزادی: جایی که لباس رویی می‌شکند
     * ------------------------------------------------------------------- */

    public function test_every_model_declares_the_ease_it_was_cut_with_and_measures_up_to_it(): void
    {
        foreach (array_keys(static::MODELS) as $key) {
            foreach (static::BODIES as $size) {
                $body = $this->body($size);
                $pieces = $this->build($key, $size);
                $ease = $this->declaredEase($pieces);

                $this->assertNotEmpty($ease, "«{$key}» نگفته با چه آزادی‌ای بریده شده است.");
                $this->assertArrayHasKey('bust', $ease);

                $girths = $this->girths($pieces);
                $this->assertArrayHasKey('bust', $girths, "{$key}|{$size} خط سینه روی جلو و پشت ندارد.");

                // دور سینهٔ تمام‌شده دقیقاً «دور بدن + آزادیِ اعلام‌شده» است
                $this->assertEqualsWithDelta(
                    $body['bust'] + $ease['bust'],
                    $girths['bust'],
                    0.6,
                    sprintf(
                        '%s|%s دور سینهٔ تمام‌شده %.1f است ولی مدل ادعا می‌کند بدن %.1f به‌علاوهٔ آزادی %.1f.',
                        $key, $size, $girths['bust'], $body['bust'], $ease['bust'],
                    ),
                );

                // «باز شدن دم» سقف مجاز را بالا می‌برد: خط کمر و خط باسنِ لباسی که
                // از زیر بغل به بیرون باز می‌شود، بخشی از آن باز شدن را با خود
                // دارند. بیشترین سهم ممکن، خودِ عددِ باز شدن در چهار پهلوست.
                $flare = 4 * (float) (GeneratorRegistry::make($key)->defaultParams()['hem_flare'] ?? 0);

                // کمر و باسن می‌توانند کمرگیری داشته باشند، ولی هرگز از خودِ بدن تنگ‌تر نمی‌شوند
                foreach (['waist', 'hip'] as $area) {
                    if (! isset($girths[$area])) {
                        continue;
                    }

                    $this->assertGreaterThan(
                        $body[$area],
                        $girths[$area],
                        sprintf('%s|%s دور %s تمام‌شده %.1f از خودِ بدن %.1f تنگ‌تر است.', $key, $size, $area, $girths[$area], $body[$area]),
                    );

                    /*
                     * لباس رویی از زیر بغل تا دم، یک خط است: نه ساسون کمر دارد
                     * نه کمرگیری. پس پارچه‌اش در هر ارتفاع روی خطی می‌نشیند که
                     * سینه را به باسن وصل می‌کند، و خط کمرش هرچه آن خط بدهد.
                     *
                     * پیش‌تر این‌جا فقط سینه مرجع بود. ولی اگر باسنِ بدن از
                     * سینه‌اش بزرگ‌تر باشد — که در بیشتر بدن‌ها هست — همان خط
                     * رو به پایین باز می‌شود و خط کمر هم با خودش می‌برد. پس
                     * مرجع، پهن‌ترین جایی است که پارچه از رویش رد می‌شود.
                     *
                     * سقف همچنان بسته است: لباسی که از هر دوی سینه و باسن هم
                     * گشادتر باشد، از این هم رد نمی‌شود.
                     */
                    $through = max($body['bust'], $body['hip']);

                    // هر ناحیه با آزادیِ اعلام‌شدهٔ *خودش* سنجیده می‌شود، نه با
                    // آزادیِ سینه. پارکا برای باسنش بیست‌وپنج اعلام می‌کند و
                    // برای سینه‌اش بیست‌وسه؛ سنجیدنِ باسن با عددِ سینه هم غلط
                    // بود و هم سست — این‌طور دقیقاً روی حرفِ خودِ مدل می‌ایستد.
                    $claimed = (float) ($ease[$area] ?? $ease['bust']);
                    $ceiling = $body[$area] + $claimed + max(0.0, $through - $body[$area]) + $flare + 1.0;
                    $this->assertLessThanOrEqual(
                        $ceiling,
                        $girths[$area],
                        sprintf('%s|%s دور %s تمام‌شده %.1f از آزادی اعلام‌شده بیشتر است.', $key, $size, $area, $girths[$area]),
                    );
                }
            }
        }
    }

    public function test_outerwear_is_roomier_than_the_shirt_it_goes_over(): void
    {
        $this->assertTrue(GeneratorRegistry::has('shirt_classic'), 'پیراهن مرجع در کاتالوگ نیست.');

        $shirt = $this->girths($this->build('shirt_classic'))['bust'] ?? 0.0;
        $this->assertGreaterThan(0, $shirt);

        foreach (array_keys(static::MODELS) as $key) {
            $coat = $this->girths($this->build($key))['bust'] ?? 0.0;

            $this->assertGreaterThan(
                $shirt,
                $coat,
                sprintf(
                    '«%s» دور سینهٔ تمام‌شده‌اش %.1f است و از پیراهن (%.1f) گشادتر نیست؛ لباس رویی روی پیراهن پوشیده می‌شود.',
                    $key, $coat, $shirt,
                ),
            );
        }
    }

    public function test_the_declared_ease_follows_what_is_worn_underneath(): void
    {
        foreach (['coat_overcoat', 'jacket_puffer', 'jacket_work'] as $key) {
            $thin = $this->declaredEase($this->build($key, '40', ['under_layer' => 'none']))['bust'];
            $thick = $this->declaredEase($this->build($key, '40', ['under_layer' => 'heavy']))['bust'];

            $this->assertGreaterThan(
                $thin,
                $thick,
                "«{$key}» باید برای بافت ضخیم آزادی بیشتری بگیرد تا برای لباس زیر تنها.",
            );
        }

        $this->assertStringContainsString(
            'آزادی',
            $this->notes($this->build('coat_overcoat')),
            'هر مدل باید با عدد بگوید با چه آزادی‌ای بریده شده است.',
        );
    }

    /* ---------------------------------------------------------------------
     |  ت) هر مدل واقعاً همان مدل باشد
     * ------------------------------------------------------------------- */

    public function test_the_double_breasted_jacket_really_has_two_rows_of_buttons(): void
    {
        $pieces = $this->build('jacket_double_breasted', '40', ['button_rows' => 3]);
        $front = $this->partsLike($pieces, ['front_bodice'])[0];

        $this->assertTrue((bool) ($front['meta']['double_breasted'] ?? false));
        $this->assertSame(6, (int) $front['meta']['buttons']);
        $this->assertGreaterThanOrEqual(5.0, (float) $front['meta']['button_stand'], 'هم‌پوشانی دوردیفه باید جای ردیف بیرونی دکمه را بدهد.');

        $keys = array_column($front['drills'], 'key');
        $this->assertNotEmpty(array_filter($keys, fn ($k) => str_starts_with($k, 'button_left_')));
        $this->assertNotEmpty(array_filter($keys, fn ($k) => str_starts_with($k, 'button_right_')));
        $this->assertTrue($this->hasNotion($pieces, 'button', 'دوردیفه'));
    }

    public function test_the_cropped_jacket_stops_above_the_hip_and_has_no_overlap(): void
    {
        $body = $this->body('40');
        $pieces = $this->build('jacket_cropped');
        $front = $this->partsLike($pieces, ['front_bodice'])[0];

        $this->assertSame(0.0, (float) ($front['meta']['button_stand'] ?? 0), 'جلوی لبه‌به‌لبه هم‌پوشانی ندارد.');
        $this->assertLessThan(
            $body['front_length'] + $body['waist_to_hip'],
            Geometry::height($front['outline']),
            'ژاکت کوتاه باید بالای خط باسن تمام شود.',
        );
    }

    public function test_the_overcoat_reaches_below_the_knee_and_opens_at_the_back(): void
    {
        $body = $this->body('40');
        $pieces = $this->build('coat_overcoat');
        $front = $this->partsLike($pieces, ['front_bodice'])[0];
        $back = $this->partsLike($pieces, ['back_bodice'])[0];

        $this->assertGreaterThan(
            $body['front_length'] + $body['waist_to_hip'] + 40,
            Geometry::height($front['outline']),
            'پالتو بلند باید خیلی پایین‌تر از باسن بیاید.',
        );
        $this->assertGreaterThan(20, (float) ($back['meta']['back_vent'] ?? 0), 'پالتوی بلند بدون چاک پشت، قدم برداشتن را قفل می‌کند.');
        $this->assertFalse((bool) $back['on_fold'], 'پشت پالتو درز مرکزی دارد.');
        $this->assertCount(2, $this->partsLike($pieces, ['sleeve']), 'آستین پالتو دوتکهٔ خیاطی است.');
        $this->assertNotEmpty($this->partsLike($pieces, ['lining']));
    }

    public function test_the_peacoat_has_a_wide_notched_collar_and_a_double_breast(): void
    {
        $pieces = $this->build('coat_peacoat');
        $collar = $this->partsLike($pieces, ['collar']);

        $this->assertNotEmpty($collar, 'پی‌کت بدون یقه، پی‌کت نیست.');
        $this->assertGreaterThanOrEqual(
            9.0,
            Geometry::height($collar[0]['outline']),
            'یقهٔ پی‌کت پهن است؛ یقهٔ باریک آن را به کت معمولی تبدیل می‌کند.',
        );
        $this->assertGreaterThan(
            (float) $collar[0]['meta']['target_neck'],
            Geometry::width($collar[0]['outline']),
            'نوک یقه باید از خطِ گردن بیرون بزند.',
        );

        $front = $this->partsLike($pieces, ['front_bodice'])[0];
        $this->assertTrue((bool) ($front['meta']['double_breasted'] ?? false));
        $this->assertGreaterThanOrEqual(8.0, (float) $front['meta']['button_stand']);
    }

    public function test_the_duffle_has_wooden_toggles_and_a_hood(): void
    {
        $pieces = $this->build('coat_duffle', '40', ['toggles' => 4, 'hood' => true]);

        $this->assertNotEmpty($this->partsLike($pieces, ['hood']), 'دافل کلاه دارد.');
        $this->assertTrue($this->hasNotion($pieces, 'button', 'تاگل'), 'بست دافل تاگل است.');

        $front = $this->partsLike($pieces, ['front_bodice'])[0];
        $this->assertSame(4, (int) $front['meta']['toggles']);
        $this->assertNotEmpty(array_filter(array_column($front['drills'], 'key'), fn ($k) => str_starts_with($k, 'toggle_')));

        $loops = array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'toggle-loop'));
        $this->assertNotEmpty($loops, 'نوار حلقهٔ تاگل باید در قطعه‌ها باشد.');

        // آنچه درفت نشده، صادقانه گفته شود
        $this->assertStringContainsString('خریدنی است', $this->notes($pieces));
    }

    public function test_the_wrap_coat_closes_with_a_belt_and_not_a_button(): void
    {
        $pieces = $this->build('coat_wrap', '40', ['wrap_overlap' => 16]);
        $front = $this->partsLike($pieces, ['front_bodice'])[0];

        $this->assertSame('wrap', $front['meta']['front_opening']);
        $this->assertGreaterThanOrEqual(14.0, (float) $front['meta']['wrap_overlap']);
        $this->assertFalse($this->hasNotion($pieces, 'button'), 'پالتو راپ دکمه ندارد.');
        $this->assertNotEmpty(
            array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'belt')),
            'پالتو راپ بدون کمربند بسته نمی‌شود.',
        );
        $this->assertNotEmpty(
            array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'inner-tie')),
            'بند داخلی، لبهٔ زیرین را نگه می‌دارد.',
        );
    }

    public function test_the_cape_has_no_armhole_at_all_but_a_hand_slit(): void
    {
        $pieces = $this->build('coat_cape');

        $this->assertEmpty($this->partsLike($pieces, ['sleeve']), 'شنل آستین ندارد.');

        foreach ($this->partsLike($pieces, ['front_bodice', 'back_bodice']) as $panel) {
            $this->assertSame([], Geometry::edgesWithTag($panel, 'armhole'), 'شنل حلقهٔ آستین ندارد.');
            $this->assertTrue((bool) ($panel['meta']['sleeveless'] ?? false), 'شنل باید صریح بگوید حلقه ندارد.');
        }

        $front = $this->partsLike($pieces, ['front_bodice'])[0];
        $this->assertGreaterThan(12.0, (float) $front['meta']['arm_slit'], 'دست باید از جایی بیرون بیاید.');
        $this->assertNotEmpty(
            array_filter($front['markers'], fn (array $m) => ($m['key'] ?? '') === 'slit'),
            'شکاف دست باید روی الگو خط نشانه داشته باشد.',
        );

        // شنل از سرشانه می‌ریزد: دمش خیلی پهن‌تر از خط زیر بغل است
        $this->assertGreaterThan(
            $front['meta']['girth']['bust'] * 1.4,
            Geometry::width($front['outline']),
            'شنلی که دمش باز نشود، مثل کیسه می‌افتد.',
        );
    }

    public function test_the_biker_has_a_diagonal_zip_a_small_lapel_and_panel_seams(): void
    {
        $pieces = $this->build('jacket_biker');

        $this->assertTrue($this->hasNotion($pieces, 'zip', 'اریب'), 'زیپ بایکر اریب است.');

        $front = $this->partsLike($pieces, ['front_bodice'])[0];
        $zip = array_values(array_filter($front['markers'], fn (array $m) => ($m['key'] ?? '') === 'zip'));

        $this->assertNotEmpty($zip);
        $this->assertNotEqualsWithDelta(
            (float) $zip[0]['from']['x'],
            (float) $zip[0]['to']['x'],
            1.0,
            'خط زیپ باید واقعاً اریب باشد، نه عمودی.',
        );

        // برش‌های پنلی: یوک سینه و یوک پشت
        $yokes = $this->partsLike($pieces, ['yoke']);
        $this->assertCount(2, $yokes, 'بایکر یوک سینه و یوک پشت دارد.');

        foreach ($yokes as $yoke) {
            $this->assertNotEmpty(
                Geometry::edgesWithTag($yoke, 'armhole'),
                'یوک سهم خودش از حلقهٔ آستین را نگه می‌دارد.',
            );
        }

        $collar = $this->partsLike($pieces, ['collar']);
        $this->assertNotEmpty($collar);
        $this->assertLessThanOrEqual(8.0, Geometry::height($collar[0]['outline']), 'یقهٔ بایکر کوچک است، نه پهن.');

        $this->assertTrue($this->hasNotion($pieces, 'snap', 'یقه'), 'نوک یقه با دکمهٔ فشاری به تنه می‌چسبد.');
        $this->assertStringContainsString('قرینه نیست', $this->notes($pieces), 'جلوی نامتقارن باید صادقانه گفته شود.');
    }

    public function test_the_puffer_is_channel_quilted_and_the_roomiest_of_the_family(): void
    {
        $pieces = $this->build('jacket_puffer', '40', ['baffle_spacing' => 10]);
        $front = $this->partsLike($pieces, ['front_bodice'])[0];

        $this->assertGreaterThanOrEqual(2, (int) ($front['meta']['baffles'] ?? 0), 'پافر بدون کاناله، پُرش ته‌نشین می‌شود.');
        $this->assertSame(10.0, (float) $front['meta']['baffle_spacing']);
        $this->assertNotEmpty(
            array_filter($front['markers'], fn (array $m) => ($m['key'] ?? '') === 'baffle'),
            'خطوط کاناله باید روی الگو نشانه داشته باشند.',
        );

        // آستین هم پُر می‌گیرد، پس آن هم کاناله می‌شود
        $sleeve = $this->partsLike($pieces, ['sleeve'])[0];
        $this->assertGreaterThanOrEqual(1, (int) ($sleeve['meta']['baffles'] ?? 0));

        $ease = $this->declaredEase($pieces)['bust'];
        $this->assertGreaterThanOrEqual(20.0, $ease, 'پُر جا می‌خواهد؛ پافر باید گشادترین این خانواده باشد.');

        // آنچه صادقانه درفت نشده
        $this->assertStringContainsString('صادقانه', $this->notes($pieces));
    }

    public function test_the_parka_has_a_furred_hood_a_drawcord_waist_and_reaches_the_thigh(): void
    {
        $body = $this->body('40');
        $pieces = $this->build('jacket_parka');

        $this->assertNotEmpty($this->partsLike($pieces, ['hood']), 'پارکا کلاه دارد.');
        $this->assertNotEmpty(
            array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'fur-band')),
            'خزِ لبهٔ کلاه باید نوار پایه داشته باشد.',
        );
        $this->assertTrue($this->hasNotion($pieces, 'cord', 'بند کمر'), 'کمر پارکا بندی است.');
        $this->assertTrue($this->hasNotion($pieces, 'eyelet'));

        $front = $this->partsLike($pieces, ['front_bodice'])[0];
        $gathers = array_column($front['meta']['gathers'] ?? [], 'label');
        $this->assertContains('بند کمر', $gathers, 'جمع‌شدنِ کمر باید ثبت شود، نه اینکه الگو کوچک بریده شود.');

        $this->assertGreaterThan(
            $body['front_length'] + $body['waist_to_hip'] + 8,
            Geometry::height($front['outline']),
            'پارکا باید از باسن بگذرد و روی ران بایستد.',
        );

        $this->assertStringContainsString('خودِ خز خریدنی است', $this->notes($pieces));
    }

    public function test_the_anorak_pulls_over_the_head_with_a_half_zip_and_a_kangaroo_pocket(): void
    {
        $body = $this->body('40');
        $pieces = $this->build('jacket_anorak');
        $front = $this->partsLike($pieces, ['front_bodice'])[0];

        $this->assertTrue((bool) $front['on_fold'], 'جلوی آنوراک روی تای پارچه است و درز مرکزی ندارد.');

        $zip = null;

        foreach ($this->notions($pieces) as $notion) {
            if (($notion['type'] ?? '') === 'zip') {
                $zip = (float) $notion['length'];
            }
        }

        $this->assertNotNull($zip, 'آنوراک نیم‌زیپ دارد.');
        $this->assertLessThan(
            Geometry::height($front['outline']) * 0.75,
            $zip,
            'اگر زیپ تا پایین برود دیگر نیم‌زیپ نیست.',
        );
        $this->assertGreaterThan(
            $body['bust'] / 8,
            $zip,
            'نیم‌زیپ باید تا زیر خط سینه بیاید، وگرنه سر از دهانه رد نمی‌شود.',
        );

        $pocket = array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'kangaroo'));
        $this->assertNotEmpty($pocket, 'آنوراک جیب کانگورو دارد.');
        $this->assertNotEmpty($this->partsLike($pieces, ['hood']));
    }

    public function test_the_windbreaker_is_gathered_at_every_opening(): void
    {
        $pieces = $this->build('jacket_windbreaker', '40', ['elastic_hem' => 'elastic', 'elastic_cuff' => true]);

        $this->assertTrue($this->hasNotion($pieces, 'elastic', 'دم لباس'), 'دم بادگیر کش دارد.');
        $this->assertTrue($this->hasNotion($pieces, 'elastic', 'مچ'), 'مچ آستین بادگیر کش دارد.');
        $this->assertNotEmpty($this->partsLike($pieces, ['hood']), 'کلاه در یقه جمع می‌شود.');

        $front = $this->partsLike($pieces, ['front_bodice'])[0];
        $this->assertNotEmpty($front['meta']['gathers'] ?? [], 'جمع‌شدنِ دم لباس باید ثبت شود.');

        // نوار کشباف به‌جای کش: همان دهانه، راه دیگر
        $rib = $this->build('jacket_windbreaker', '40', ['elastic_hem' => 'rib']);
        $this->assertNotEmpty(array_filter($rib, fn (array $p) => str_contains((string) $p['code'], 'hem-rib')));
    }

    public function test_the_work_jacket_is_unlined_with_four_patch_pockets(): void
    {
        $pieces = $this->build('jacket_work');

        $this->assertEmpty($this->partsLike($pieces, ['lining']), 'کت کار آستر ندارد.');

        $pockets = $this->partsLike($pieces, ['pocket']);
        $this->assertCount(2, $pockets, 'دو الگوی جیب: سینه و پایین.');

        $total = 0;

        foreach ($pockets as $pocket) {
            $total += (int) $pocket['cut_quantity'];
        }

        $this->assertSame(4, $total, 'روی هم چهار جیب رودوزی بریده می‌شود.');
        $this->assertStringContainsString('آستر ندارد', $this->notes($pieces));
    }

    public function test_the_utility_vest_has_no_sleeve_but_a_finished_armhole_and_bellows_pockets(): void
    {
        $pieces = $this->build('vest_utility', '40', ['cargo_pockets' => 2]);

        $this->assertEmpty($this->partsLike($pieces, ['sleeve']), 'جلیقه آستین ندارد.');

        foreach ($this->partsLike($pieces, ['front_bodice', 'back_bodice']) as $panel) {
            $this->assertNotEmpty(Geometry::edgesWithTag($panel, 'armhole'), 'جلیقه حلقه دارد، فقط آستین ندارد.');
        }

        $this->assertNotEmpty(
            array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'armhole-binding')),
            'حلقهٔ بی‌آستین باید با نوار اریب تمام شود.',
        );

        $gussets = array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'pocket-gusset'));
        $this->assertCount(2, $gussets, 'جیب کارگو بدون نوار جان‌دار، جیب ساده است.');

        $flaps = array_filter($pieces, fn (array $p) => str_contains((string) $p['code'], 'pocket-flap'));
        $this->assertCount(2, $flaps);
        $this->assertTrue($this->hasNotion($pieces, 'snap'));
    }

    /* ---------------------------------------------------------------------
     |  ث) آستر، سجاف، لایی و بست
     * ------------------------------------------------------------------- */

    public function test_lining_facing_interfacing_and_fastenings_are_all_recorded(): void
    {
        $lined = ['jacket_double_breasted', 'coat_overcoat', 'coat_peacoat', 'coat_duffle', 'coat_wrap', 'jacket_biker', 'jacket_puffer', 'jacket_parka', 'vest_utility'];

        foreach ($lined as $key) {
            $pieces = $this->build($key);
            $this->assertNotEmpty(
                $this->partsLike($pieces, ['lining']),
                "«{$key}» پیش‌فرض آستر دارد و قطعه‌های آسترش باید ساخته شوند.",
            );
        }

        foreach (array_keys(static::MODELS) as $key) {
            $pieces = $this->build($key);

            // هر مدل دست‌کم یک قطعهٔ لایی‌خور دارد (یقه، سجاف، پاتلت یا لبهٔ جلو)
            $this->assertNotEmpty(
                array_filter($pieces, fn (array $p) => ! empty($p['meta']['interfacing'])),
                "«{$key}» هیچ قطعه‌ای با لایی ندارد؛ لبهٔ بدون لایی در لباس رویی کش می‌آید.",
            );

            // بستِ واقعی: زیپ، دکمه، دکمهٔ فشاری، قزن یا بند
            $fastened = false;

            foreach (['zip', 'button', 'snap', 'hook', 'cord'] as $type) {
                $fastened = $fastened || $this->hasNotion($pieces, $type);
            }

            $this->assertTrue(
                $fastened || in_array($key, ['coat_wrap'], true),
                "«{$key}» هیچ بستی ثبت نکرده است.",
            );

            // یادداشت فارسی روی الگو
            $this->assertNotEmpty($this->notes($pieces), "«{$key}» هیچ یادداشتی ندارد.");
        }
    }

    public function test_each_pattern_says_out_loud_what_it_could_not_draft(): void
    {
        $confessions = [
            'coat_duffle' => 'خریدنی است',
            'jacket_puffer' => 'صادقانه',
            'jacket_parka' => 'خریدنی است',
            'jacket_biker' => 'قرینه نیست',
            'coat_cape' => 'درفت نشده',
            'jacket_windbreaker' => 'جدا کشیده نشده',
        ];

        foreach ($confessions as $key => $needle) {
            $this->assertStringContainsString(
                $needle,
                $this->notes($this->build($key)),
                "«{$key}» باید بگوید چه چیزی را نتوانسته درفت کند.",
            );
        }
    }
}
