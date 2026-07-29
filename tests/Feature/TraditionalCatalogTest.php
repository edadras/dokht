<?php

namespace Tests\Feature;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاتالوگ لباس سنتی و پوشیده.
 *
 * این خانواده دو چیز دارد که در بقیه کاتالوگ نیست و هر دو جای دروغ گفتن است:
 *
 *   ۱. «پوشیدگی» — لباسی که پوشیده نامیده می‌شود باید بگوید تا کجا را می‌پوشاند،
 *      با عدد. این آزمون‌ها همان عدد را از خودِ الگو می‌خوانند و با مسیر قطعه
 *      می‌سنجند، نه با ادعای متن.
 *
 *   ۲. «همان مدل بودن» — چیپائو باید یقه ایستاده و بستِ اریب داشته باشد، هانبوک
 *      باید بالاتنه کوتاه و دامنِ زیرسینه داشته باشد، شلوار کمیز باید شلوار
 *      داشته باشد. مدلی که فقط اسمش عوض شده باشد، از این آزمون‌ها رد نمی‌شود.
 */
class TraditionalCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * بدن‌های آزمون.
     *
     * Measurements::fromSize() فقط سایزهای جدولی ۳۴ تا ۴۸ را می‌شناسد و برای هر
     * کلید ناشناخته بی‌صدا سایز ۴۰ را برمی‌گرداند؛ پس بدن‌های غیرجدولی این‌جا
     * صریح نوشته شده‌اند، وگرنه آزمون خیال می‌کند پنج بدن را سنجیده و در واقع
     * سه‌تایشان یک بدن بوده‌اند.
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
    protected const BODIES = ['34', '40', '48', 'کودک', 'کوتاه و درشت'];

    /** کلید ⇒ نام فارسی؛ همان چیزی که باید در فهرست باشد. */
    protected const MODELS = [
        'trad_qipao' => 'چیپائو (قباد چینی)',
        'trad_hanbok' => 'لباس الهام‌گرفته از هانبوک',
        'trad_kurta' => 'کورتا',
        'trad_shalwar_kameez' => 'شلوار کمیز',
        'trad_thobe' => 'ثوب (دشداشه مردانه)',
        'trad_jilbab' => 'جلباب',
        'trad_khimar' => 'خمار (مقنعه بلند)',
        'trad_prayer_dress' => 'چادر نماز',
        'trad_modest_tunic' => 'تونیک پوشیده',
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

    /**
     * قطعه‌هایی با meta.part خواسته‌شده.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parts(array $pieces, array $wanted): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => in_array((string) ($piece['meta']['part'] ?? ''), $wanted, true),
        ));
    }

    /** یک قطعه با کد داده‌شده. */
    protected function piece(array $pieces, string $code): ?array
    {
        foreach ($pieces as $piece) {
            if (($piece['code'] ?? '') === $code) {
                return $piece;
            }
        }

        return null;
    }

    /**
     * اندازه دور تمام‌شده روی یک خط نشانه (سینه، کمر یا باسن).
     *
     * از روی خط‌های نشانه خوانده می‌شود، همان‌جوری که ممیزی کاتالوگ می‌خواند.
     */
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

    /* ---------------------------------------------------------------------
     |  فهرست و درستی پایه
     * ------------------------------------------------------------------- */

    public function test_the_family_is_registered_with_all_nine_models(): void
    {
        $this->assertArrayHasKey('traditional', GeneratorRegistry::GROUPS);

        $group = GeneratorRegistry::group('traditional');

        foreach (static::MODELS as $key => $label) {
            $this->assertArrayHasKey($key, $group, "«{$key}» در گروه لباس سنتی و پوشیده نیست.");
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
                        "{$where} مسیرش خودش را قطع می‌کند و بریده نمی‌شود.",
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
        foreach (['trad_qipao', 'trad_kurta', 'trad_shalwar_kameez', 'trad_thobe', 'trad_jilbab', 'trad_modest_tunic', 'trad_hanbok'] as $key) {
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
                        '%s|%s درز پهلوی جلو %.2f و پشت %.2f است؛ این دو به هم دوخته می‌شوند.',
                        $key, $size, $walk['a']['seam'], $walk['b']['seam'],
                    ),
                );
            }
        }
    }

    public function test_every_sleeve_matches_the_armhole_it_goes_into(): void
    {
        foreach (['trad_qipao', 'trad_kurta', 'trad_shalwar_kameez', 'trad_modest_tunic'] as $key) {
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

                $this->assertGreaterThan($armhole - 0.5, $cap, "{$key}|{$size} سرآستین از حلقه کوتاه‌تر است.");
                $this->assertLessThan(
                    $armhole + max(6.0, $armhole * 0.25),
                    $cap,
                    "{$key}|{$size} سرآستین بیش از اندازه از حلقه بلندتر است.",
                );
            }
        }
    }

    public function test_finished_girths_stay_a_sane_distance_from_the_body(): void
    {
        foreach (['trad_qipao', 'trad_kurta', 'trad_shalwar_kameez', 'trad_thobe', 'trad_jilbab', 'trad_modest_tunic', 'trad_hanbok'] as $key) {
            foreach (static::BODIES as $size) {
                $body = $this->body($size);
                $pieces = $this->build($key, $size);
                $bust = $this->girth($pieces, 'bust');

                $this->assertGreaterThan(
                    (float) $body['bust'],
                    $bust,
                    "{$key}|{$size} دور سینه تمام‌شده از خودِ بدن کوچک‌تر است.",
                );
                $this->assertLessThanOrEqual(
                    (float) $body['bust'] + 30.0,
                    $bust,
                    "{$key}|{$size} دور سینه تمام‌شده بیش از سی سانتی‌متر از بدن بازتر است.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  پوشیدگی سنجیدنی
     * ------------------------------------------------------------------- */

    public function test_every_modest_model_says_how_far_it_covers(): void
    {
        $keys = ['trad_qipao', 'trad_kurta', 'trad_shalwar_kameez', 'trad_thobe', 'trad_jilbab', 'trad_khimar', 'trad_prayer_dress', 'trad_modest_tunic', 'trad_hanbok'];

        foreach ($keys as $key) {
            $declared = null;

            foreach ($this->build($key) as $piece) {
                if (isset($piece['meta']['coverage'])) {
                    $declared = $piece['meta']['coverage'];
                }
            }

            $this->assertIsArray($declared, "«{$key}» نگفته تا کجا را می‌پوشاند (meta.coverage).");
            $this->assertNotEmpty($declared, "«{$key}» پوشیدگی خالی اعلام کرده است.");
        }
    }

    public function test_the_declared_hem_matches_the_pattern_piece(): void
    {
        foreach (['trad_qipao', 'trad_kurta', 'trad_shalwar_kameez', 'trad_thobe', 'trad_jilbab', 'trad_modest_tunic'] as $key) {
            foreach (['40', 'کوتاه و درشت'] as $size) {
                $pieces = $this->build($key, $size);
                $front = $this->parts($pieces, ['front_bodice'])[0];
                $declared = (float) ($front['meta']['coverage']['hem_from_shoulder'] ?? 0);

                $this->assertGreaterThan(0.0, $declared, "{$key}|{$size} بلندی پوشش را اعلام نکرده است.");
                $this->assertEqualsWithDelta(
                    Geometry::height($front['outline']),
                    $declared,
                    0.2,
                    "{$key}|{$size} بلندی اعلام‌شده با خودِ قطعه جور نیست.",
                );
            }
        }
    }

    public function test_a_longer_garment_really_covers_more(): void
    {
        $short = $this->build('trad_modest_tunic', '40', ['length' => 24]);
        $long = $this->build('trad_modest_tunic', '40', ['length' => 60]);

        $hemOf = fn (array $pieces) => (float) $this->parts($pieces, ['front_bodice'])[0]['meta']['coverage']['hem_from_shoulder'];

        $this->assertEqualsWithDelta(36.0, $hemOf($long) - $hemOf($short), 0.5, 'بلندی بیشتر باید همان‌قدر پوشش بیشتر بدهد.');
    }

    /* ---------------------------------------------------------------------
     |  «همان مدل بودن»
     * ------------------------------------------------------------------- */

    public function test_the_qipao_has_a_mandarin_collar_and_a_diagonal_closure(): void
    {
        $pieces = $this->build('trad_qipao');
        $front = $this->parts($pieces, ['front_bodice'])[0];

        $this->assertNotNull($this->piece($pieces, 'qipao-collar-stand'), 'چیپائو بدون یقه ایستاده، چیپائو نیست.');

        $closure = array_values(array_filter($front['markers'], fn (array $m) => ($m['key'] ?? '') === 'closure'));
        $this->assertNotEmpty($closure, 'خط بستِ اریب روی تنه جلو علامت نخورده است.');

        // بست از مرکز جلو شروع می‌شود و زیر بغل تمام می‌گردد: هم افقی و هم عمودی جابه‌جا می‌شود
        $marker = $closure[0];
        $this->assertGreaterThan(8.0, abs($marker['to']['x'] - $marker['from']['x']), 'بست باید تا پهلو برود، نه عمودی بماند.');
        $this->assertGreaterThan(4.0, abs($marker['to']['y'] - $marker['from']['y']), 'بست باید اریب باشد، نه افقی.');

        $flap = $this->piece($pieces, 'qipao-under-flap');
        $this->assertNotNull($flap, 'زیرلبه بست ساخته نشده است.');
        $this->assertGreaterThan(0.0, (float) ($flap['meta']['overlap'] ?? 0), 'زیرلبه باید هم‌پوشانی داشته باشد.');
        $this->assertEqualsWithDelta(
            (float) $front['meta']['closure_length'],
            (float) $flap['meta']['closure_length'],
            0.05,
            'طول بستِ اعلام‌شده روی تنه و زیرلبه یکی نیست.',
        );

        $this->assertGreaterThan(0.0, (float) ($front['meta']['vent'] ?? 0), 'چیپائوی بدون چاک پهلو پوشیدنی نیست.');
    }

    public function test_the_qipao_is_fitted_and_darted(): void
    {
        $front = $this->parts($this->build('trad_qipao'), ['front_bodice'])[0];
        $labels = array_column($front['darts'] ?? [], 'type');

        $this->assertContains('bust', $labels, 'چیپائو ساسون سینه دارد.');
        $this->assertContains('waist', $labels, 'چیپائو ساسون کمر دارد.');

        $body = $this->body('40');
        $waist = $this->girth($this->build('trad_qipao'), 'waist');

        $this->assertLessThan(
            $this->girth($this->build('trad_qipao'), 'bust'),
            $waist,
            'کمر چیپائو باید از سینه‌اش جمع‌تر باشد؛ وگرنه چسبان نیست.',
        );
        $this->assertGreaterThan((float) $body['waist'], $waist);
    }

    public function test_the_hanbok_is_two_pieces_with_a_short_bodice_and_an_underbust_skirt(): void
    {
        $pieces = $this->build('trad_hanbok');
        $front = $this->parts($pieces, ['front_bodice'])[0];
        $skirt = $this->parts($pieces, ['skirt_front'])[0];

        // چوگوری باید بالای خط کمر تمام شود
        $this->assertLessThan(
            (float) $front['meta']['waist_y'],
            Geometry::height($front['outline']),
            'چوگوری باید بالای خط کمر تمام شود؛ وگرنه هانبوک نیست.',
        );

        $this->assertGreaterThan(
            60.0,
            Geometry::height($skirt['outline']),
            'چیما دامن بلندی است، نه یک دامن کوتاه.',
        );

        $this->assertNotNull($this->piece($pieces, 'hanbok-malgi'), 'چیما بدون سینه‌بند بالا نمی‌ماند.');
        $this->assertNotNull($this->piece($pieces, 'hanbok-goreum-long'), 'هانبوک با بند گره بسته می‌شود، نه با دکمه.');

        $tie = $this->piece($pieces, 'hanbok-goreum-long');
        $this->assertGreaterThanOrEqual(6.0, Geometry::height($tie['outline']) / 2, 'بند گره هانبوک پهن است.');
    }

    public function test_the_chima_records_its_gathers(): void
    {
        foreach ([1.8, 2.6] as $ratio) {
            $skirt = $this->parts($this->build('trad_hanbok', '40', ['chima_fullness' => $ratio]), ['skirt_front'])[0];
            $gathers = $skirt['meta']['gathers'] ?? [];

            $this->assertNotEmpty($gathers, 'چین چیما در meta.gathers ثبت نشده است.');
            $this->assertEqualsWithDelta($ratio, (float) $gathers[0]['ratio'], 0.05, 'نسبت چین ثبت‌شده با پارامتر جور نیست.');
        }

        $narrow = $this->parts($this->build('trad_hanbok', '40', ['chima_fullness' => 1.8]), ['skirt_front'])[0];
        $wide = $this->parts($this->build('trad_hanbok', '40', ['chima_fullness' => 3.0]), ['skirt_front'])[0];

        $this->assertGreaterThan(
            Geometry::width($narrow['outline']),
            Geometry::width($wide['outline']),
            'چین بیشتر یعنی پارچه بیشتر.',
        );
    }

    public function test_the_kurta_is_straight_while_the_modest_tunic_flares(): void
    {
        $widthAtHem = function (array $pieces): float {
            $front = $this->parts($pieces, ['front_bodice'])[0];
            [, , $maxX] = Geometry::bounds($front['outline']);

            return $maxX - (float) $front['meta']['girth']['bust'];
        };

        $kurta = $widthAtHem($this->build('trad_kurta'));
        $tunic = $widthAtHem($this->build('trad_modest_tunic'));

        $this->assertGreaterThan($kurta, $tunic, 'تونیک پوشیده باید از کورتا بازتر بریزد.');
    }

    public function test_the_shalwar_kameez_really_brings_trousers(): void
    {
        $pieces = $this->build('trad_shalwar_kameez');
        $legs = $this->parts($pieces, ['front_leg', 'back_leg']);

        $this->assertCount(2, $legs, 'شلوار کمیز بدون شلوار، فقط یک کمیز است.');
        $this->assertNotNull($this->piece($pieces, 'shalwar-gusset'), 'شلوار سنتی بدون لِنگه فاق، نشستن را ممکن نمی‌کند.');
        $this->assertNotNull($this->piece($pieces, 'shalwar-casing'), 'کمر شلوار با نیفه و بند بسته می‌شود.');

        // درز داخل پا صاف است: پاچه چهار رأس بیشتر ندارد و منحنی فاق ندارد
        foreach ($legs as $leg) {
            $this->assertCount(4, $leg['outline'], 'پاچه شلوار سنتی چهار خط راست است؛ منحنی فاق ندارد.');
            $this->assertNotEmpty($leg['meta']['gathers'] ?? [], 'چین کمر و مچ پاچه باید ثبت شود.');
        }

        $cord = [];

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                if (($notion['type'] ?? '') === 'cord') {
                    $cord[] = $notion;
                }
            }
        }

        $this->assertNotEmpty($cord, 'بند نیفه در فهرست یراق نیست.');
        $this->assertGreaterThan(0.0, (float) $cord[0]['length'], 'بند با طول سفارش داده می‌شود.');
    }

    public function test_a_fuller_shalwar_uses_more_fabric(): void
    {
        $legOf = function (array $pieces): float {
            foreach ($pieces as $piece) {
                if (($piece['meta']['part'] ?? '') === 'front_leg') {
                    return Geometry::width($piece['outline']);
                }
            }

            return 0.0;
        };

        $narrow = $legOf($this->build('trad_shalwar_kameez', '40', ['shalwar_fullness' => 1.3]));
        $full = $legOf($this->build('trad_shalwar_kameez', '40', ['shalwar_fullness' => 2.4]));

        $this->assertGreaterThan($narrow + 5, $full, 'پُری بیشتر باید پاچه پهن‌تری بدهد.');
    }

    public function test_the_thobe_is_a_long_kimono_cut_shirt_with_a_stand_collar(): void
    {
        $pieces = $this->build('trad_thobe');
        $front = $this->parts($pieces, ['front_bodice'])[0];

        $this->assertTrue((bool) ($front['meta']['one_piece_sleeve'] ?? false), 'ثوب تنه و آستین یک‌سره دارد.');
        $this->assertNotNull($this->piece($pieces, 'thobe-collar-stand'), 'ثوب یقه ایستاده دارد.');
        $this->assertNotNull($this->piece($pieces, 'thobe-placket'), 'چاک دکمه‌خور جلو پاتلت می‌خواهد.');
        $this->assertGreaterThan(
            110.0,
            Geometry::height($front['outline']),
            'ثوب تا مچ پا می‌آید؛ کوتاه‌تر از این دیگر ثوب نیست.',
        );

        $buttons = 0;

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                if (($notion['type'] ?? '') === 'button') {
                    $buttons += (int) $notion['count'];
                }
            }
        }

        $this->assertGreaterThan(0, $buttons, 'چاک ثوب با دکمه بسته می‌شود.');
    }

    public function test_the_jilbab_is_one_piece_with_a_neck_wide_enough_for_the_head(): void
    {
        foreach (static::BODIES as $size) {
            $pieces = $this->build('trad_jilbab', $size);
            $front = $this->parts($pieces, ['front_bodice'])[0];
            $back = $this->parts($pieces, ['back_bodice'])[0];

            $this->assertTrue((bool) $front['on_fold'], 'جلوی جلباب هیچ درز و بستی ندارد؛ روی تای پارچه بریده می‌شود.');

            $opening = 2 * (((float) $front['meta']['neck_length']) + ((float) $back['meta']['neck_length']));
            $head = ((float) $this->body($size)['neck']) * 1.55;

            $this->assertGreaterThan(
                $head,
                $opening,
                "{$size}: دور یقه جلباب باید از دور سر بزرگ‌تر باشد، وگرنه از سر رد نمی‌شود.",
            );
        }

        $this->assertNotNull($this->piece($this->build('trad_jilbab'), 'jilbab-hood'), 'سرپوش پیوسته ساخته نشده است.');
        $this->assertNull(
            $this->piece($this->build('trad_jilbab', '40', ['hood' => false]), 'jilbab-hood'),
            'وقتی سرپوش خواسته نشده نباید ساخته شود.',
        );
    }

    /* ---------------------------------------------------------------------
     |  سرپوش‌ها
     * ------------------------------------------------------------------- */

    public function test_a_head_cover_has_a_face_opening_bigger_than_the_head(): void
    {
        foreach (['trad_khimar' => 'khimar', 'trad_prayer_dress' => 'prayer-cover'] as $key => $code) {
            foreach (static::BODIES as $size) {
                $cover = $this->piece($this->build($key, $size), $code);

                $this->assertNotNull($cover, "«{$key}» سرپوش ندارد.");

                $opening = (float) $cover['meta']['face_opening'];
                $head = ((float) $this->body($size)['neck']) * 1.55;

                $this->assertGreaterThan($head, $opening, "{$key}|{$size} جای صورت از دور سر کوچک‌تر است.");
            }
        }
    }

    public function test_a_head_cover_falls_longer_at_the_back_than_at_the_front(): void
    {
        $cover = $this->piece($this->build('trad_khimar'), 'khimar');

        $this->assertGreaterThan(
            (float) $cover['meta']['front_drop'] + 5,
            (float) $cover['meta']['back_drop'],
            'پشتِ خمار باید از جلویش بلندتر باشد، وگرنه پانچو است نه خمار.',
        );

        $centred = $this->piece($this->build('trad_khimar', '40', ['face_offset' => 0]), 'khimar');

        $this->assertEqualsWithDelta(
            (float) $centred['meta']['front_drop'],
            (float) $centred['meta']['back_drop'],
            0.1,
            'با جابه‌جایی صفر، جلو و پشت باید هم‌اندازه شوند.',
        );
    }

    public function test_the_head_cover_is_cut_on_the_fold_with_the_face_hole_on_the_fold(): void
    {
        $cover = $this->piece($this->build('trad_khimar'), 'khimar');

        $this->assertTrue((bool) $cover['on_fold'], 'سرپوش روی تای پارچه بریده می‌شود.');
        $this->assertGreaterThanOrEqual(2, count($cover['meta']['fold_edges']), 'جای صورت هم روی همان تا می‌نشیند، پس دو لبه تا داریم.');
        $this->assertNotEmpty(Geometry::edgesWithTag($cover, 'neck'), 'لبه صورت باید برچسب یقه داشته باشد.');
    }

    public function test_the_prayer_dress_is_two_pieces_and_asks_for_elastic(): void
    {
        $pieces = $this->build('trad_prayer_dress');

        $this->assertNotNull($this->piece($pieces, 'prayer-cover'), 'چادر نماز سرانداز دارد.');
        $this->assertCount(2, $this->parts($pieces, ['skirt_front', 'skirt_back']), 'چادر نماز دامن دارد.');

        $elastics = [];

        foreach ($pieces as $piece) {
            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                if (($notion['type'] ?? '') === 'elastic') {
                    $elastics[$notion['label']] = (float) $notion['length'];
                }
            }
        }

        $this->assertCount(2, $elastics, 'هم لبه صورت و هم کمر دامن کش می‌خواهند.');

        foreach ($elastics as $label => $length) {
            $this->assertGreaterThan(0.0, $length, "طول «{$label}» حساب نشده است.");
        }

        $cover = $this->piece($pieces, 'prayer-cover');
        $this->assertLessThan(
            (float) $cover['meta']['face_opening'],
            $elastics['کش لبه صورت سرانداز'],
            'کش باید از لبه‌ای که نگه می‌دارد کوتاه‌تر باشد.',
        );
    }

    public function test_a_gathered_prayer_skirt_records_its_fullness(): void
    {
        $skirt = $this->parts($this->build('trad_prayer_dress'), ['skirt_front'])[0];

        $this->assertNotEmpty($skirt['meta']['gathers'] ?? [], 'چین کمر دامن در meta.gathers ثبت نشده است.');

        $waistEdges = Geometry::edgesWithTag($skirt, 'waist');
        $this->assertNotEmpty($waistEdges);

        // طول دوخته‌شده لبه کمر پس از چین، باید همان یک‌چهارم دور کمر بماند
        $body = $this->body('40');
        $sewn = PieceOps::seamLength($skirt, $waistEdges) * 2;

        $this->assertEqualsWithDelta(($body['waist'] + 4) / 2, $sewn, 1.0, 'لبه کمر پس از چین باید به دور کمر برسد.');
    }
}
