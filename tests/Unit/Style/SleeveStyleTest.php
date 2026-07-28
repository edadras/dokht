<?php

namespace Tests\Unit\Style;

use App\Services\Pattern\Generators\BodiceBlockGenerator;
use App\Services\Pattern\Generators\SleeveGenerator;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleRegistry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * آزمون آستین‌هایی که با بالاتنه یکی بریده می‌شوند.
 *
 * همه آزمون‌ها روی سه سایز واقعی (۳۴، ۴۰ و ۴۸) و روی بالاتنه پایه همین برنامه
 * اجرا می‌شوند، چون ادعای اصلی این سبک‌ها این است که بالاتنه پس از جراحی همچنان
 * دوختنی می‌ماند و درزهای تازه با هم جور درمی‌آیند.
 */
class SleeveStyleTest extends TestCase
{
    /** سایزهایی که همه چیز روی آن‌ها سنجیده می‌شود. */
    protected const SIZES = ['34', '40', '48'];

    /** رواداری جورشدن درز، برابر همان چیزی که به کاربر وعده داده می‌شود. */
    protected const TOLERANCE = 0.1;

    protected const KEYS = [
        'sleeve_raglan',
        'sleeve_saddle',
        'sleeve_kimono',
        'sleeve_dolman',
        'sleeve_batwing',
        'sleeve_drop_shoulder',
    ];

    /* ---------------------------------------------------------------------
     |  کمک‌کننده‌ها
     * ------------------------------------------------------------------- */

    /** @return array<int, array<string, mixed>> */
    protected function bodice(string $size): array
    {
        return (new BodiceBlockGenerator())->generate($size === '' ? [] : Measurements::fromSize($size), $this->ease(), []);
    }

    /** @return array<string, float> */
    protected function ease(): array
    {
        return ['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4];
    }

    /** @return array<string, mixed> */
    protected function apply(string $key, string $size, array $params = []): array
    {
        $style = StyleRegistry::make($key);
        $pieces = $this->bodice($size);

        $this->assertTrue($style->supports($pieces, []), $key.' باید روی بالاتنه پایه پذیرفته شود.');

        return $style->apply($pieces, [
            'measurements' => Measurements::fromSize($size),
            'ease' => $this->ease(),
            'params' => $params,
        ]);
    }

    /** @return array<string, mixed> */
    protected function piece(array $result, string $code): array
    {
        foreach ($result['pieces'] as $piece) {
            if ($piece['code'] === $code) {
                return $piece;
            }
        }

        $this->fail("قطعه «{$code}» ساخته نشد؛ قطعه‌ها: ".implode('، ', array_column($result['pieces'], 'code')));
    }

    /** نخستین قطعه‌ای که این بخش را دارد. */
    protected function part(array $result, string $part): ?array
    {
        foreach ($result['pieces'] as $piece) {
            if (($piece['meta']['part'] ?? null) === $part) {
                return $piece;
            }
        }

        return null;
    }

    /** شماره لبه‌ای که نشانه‌ای با این جفت روی آن نشسته است. */
    protected function edgeOf(array $piece, string $pair): int
    {
        foreach ($piece['notches'] ?? [] as $notch) {
            if (($notch['pair'] ?? null) === $pair) {
                return (int) $notch['edge'];
            }
        }

        $this->fail("نشانه «{$pair}» روی قطعه «{$piece['code']}» نیست.");
    }

    protected function assertSewable(array $piece, string $context = ''): void
    {
        $where = $context.' '.$piece['code'];

        $this->assertSame([], Geometry::validatePiece($piece), $where.': قطعه سالم نیست.');
        $this->assertCount(count($piece['outline']), $piece['meta']['edges'], $where.': برچسب هر لبه.');
        $this->assertNotEmpty($piece['grainline'], $where.': راستای پارچه.');

        [$left, $top, $right, $bottom] = Geometry::bounds($piece['outline']);
        $this->assertSame([0.0, 0.0], [round($left, 2), round($top, 2)], $where.': مبدأ گوشه بالا-چپ.');

        foreach (['from', 'to'] as $end) {
            $point = $piece['grainline'][$end];
            $this->assertGreaterThanOrEqual($left - 0.01, $point['x'], $where.': راستای پارچه داخل قطعه.');
            $this->assertLessThanOrEqual($right + 0.01, $point['x'], $where.': راستای پارچه داخل قطعه.');
            $this->assertGreaterThanOrEqual($top - 0.01, $point['y'], $where.': راستای پارچه داخل قطعه.');
            $this->assertLessThanOrEqual($bottom + 0.01, $point['y'], $where.': راستای پارچه داخل قطعه.');
        }

        foreach ($piece['meta']['edges'] as $tag) {
            $this->assertContains($tag, ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'default'], $where.': برچسب لبه شناخته‌شده.');
        }

        foreach ($piece['notches'] ?? [] as $notch) {
            $this->assertLessThan(count($piece['outline']), (int) $notch['edge'], $where.': نشانه روی لبه موجود.');
        }

        foreach ($piece['darts'] ?? [] as $dart) {
            if ($dart['edge'] !== null) {
                $this->assertLessThan(count($piece['outline']), (int) $dart['edge'], $where.': ساسون روی لبه موجود.');
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  فهرست سبک‌ها
     * ------------------------------------------------------------------- */

    public function test_all_cut_with_bodice_sleeves_are_in_the_sleeve_group(): void
    {
        $group = StyleRegistry::group('sleeve');

        foreach (static::KEYS as $key) {
            $this->assertArrayHasKey($key, $group, "سبک «{$key}» در گروه آستین نیست.");
            $this->assertSame('sleeve', $group[$key]::group());
            $this->assertNotSame('', $group[$key]->label());
            $this->assertNotSame('', $group[$key]->description());
            $this->assertNotEmpty($group[$key]->paramsSchema());

            foreach ($group[$key]->paramsSchema() as $name => $field) {
                $this->assertArrayHasKey('label', $field, "{$key}.{$name} باید نام فارسی داشته باشد.");
                $this->assertArrayHasKey('default', $field, "{$key}.{$name} باید پیش‌فرض داشته باشد.");
            }
        }
    }

    public function test_every_style_leaves_valid_pieces_at_every_size(): void
    {
        foreach (static::KEYS as $key) {
            foreach (static::SIZES as $size) {
                $result = $this->apply($key, $size);

                $this->assertNotEmpty($result['notes'], "{$key}: باید توضیح فارسی بدهد.");

                // آستین‌های یکی‌بریده قطعه تازه‌ای نمی‌سازند؛ بقیه آستین جدا می‌دهند
                $expected = in_array($key, ['sleeve_kimono', 'sleeve_dolman', 'sleeve_batwing'], true) ? 2 : 3;
                $this->assertGreaterThanOrEqual($expected, count($result['pieces']), "{$key}: تعداد قطعه‌ها.");

                foreach ($result['pieces'] as $piece) {
                    $this->assertSewable($piece, "{$key} سایز {$size}:");
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  رگلان
     * ------------------------------------------------------------------- */

    public function test_raglan_removes_the_armhole_and_the_shoulder_seam_from_the_bodice(): void
    {
        foreach (static::SIZES as $size) {
            $before = $this->bodice($size);
            $result = $this->apply('sleeve_raglan', $size);

            foreach (['bodice-front', 'bodice-back'] as $code) {
                $piece = $this->piece($result, $code);
                $tags = $piece['meta']['edges'];

                $this->assertNotContains('shoulder', $tags, "{$code} سایز {$size}: درز سرشانه باید برداشته شود.");
                $this->assertCount(2, array_keys($tags, 'armhole', true), 'حلقه یک‌تکه جای خود را به درز رگلان و حلقه پایین می‌دهد.');

                // حلقه آستین بلوک دیگر وجود ندارد: کوتاه‌تر شده و شکل تازه‌ای گرفته
                $original = $this->piece(['pieces' => $before], $code);
                $this->assertLessThan(
                    $original['meta']['armhole_length'],
                    $piece['meta']['raglan']['lower_armhole'],
                    'حلقه پایینِ مانده باید کوتاه‌تر از حلقه اصلی باشد.',
                );
            }
        }
    }

    public function test_raglan_seams_walk_together_with_the_sleeve(): void
    {
        foreach (static::SIZES as $size) {
            foreach (['one_piece', 'dart', 'two_piece'] as $head) {
                $result = $this->apply('sleeve_raglan', $size, ['head' => $head]);
                $meta = $result['meta']['sleeve'];

                $this->assertTrue($meta['matched'], "رگلان {$head} سایز {$size}: درزها جور درنیامدند.");

                foreach ($meta['seams'] as $seam => $difference) {
                    $this->assertLessThanOrEqual(
                        static::TOLERANCE,
                        abs($difference),
                        "رگلان {$head} سایز {$size}: درز «{$seam}» با اختلاف {$difference} پیاده شد.",
                    );
                }

                $this->assertCount(4, $meta['seams'], 'دو درز رگلان و دو حلقه پایین باید پیاده شوند.');
            }
        }
    }

    public function test_raglan_seam_lengths_are_measured_the_same_on_bodice_and_sleeve(): void
    {
        foreach (static::SIZES as $size) {
            $result = $this->apply('sleeve_raglan', $size);
            $sleeve = $this->piece($result, 'raglan-sleeve');

            foreach (['front', 'back'] as $side) {
                $bodice = $this->piece($result, 'bodice-'.$side);
                $raglan = PieceOps::walk(
                    $bodice,
                    $this->edgeOf($bodice, 'raglan_'.$side),
                    $sleeve,
                    $this->edgeOf($sleeve, 'raglan_'.$side),
                );
                $lower = PieceOps::walk(
                    $bodice,
                    $this->edgeOf($bodice, 'underarm_'.$side),
                    $sleeve,
                    $this->edgeOf($sleeve, 'underarm_'.$side),
                );

                $this->assertLessThanOrEqual(static::TOLERANCE, abs($raglan['difference']), "درز رگلان {$side} سایز {$size}");
                $this->assertLessThanOrEqual(static::TOLERANCE, abs($lower['difference']), "حلقه پایین {$side} سایز {$size}");
                $this->assertGreaterThan(5.0, $raglan['a']['seam'], 'درز رگلان باید طول واقعی داشته باشد.');
            }
        }
    }

    public function test_raglan_keeps_the_transferred_shoulder_length(): void
    {
        foreach (static::SIZES as $size) {
            $before = $this->bodice($size);
            $front = $this->piece(['pieces' => $before], 'bodice-front');
            $back = $this->piece(['pieces' => $before], 'bodice-back');

            $shoulders = [
                Geometry::edgeLength($front['outline'], (int) array_search('shoulder', $front['meta']['edges'], true)),
                Geometry::edgeLength($back['outline'], (int) array_search('shoulder', $back['meta']['edges'], true)),
            ];

            $result = $this->apply('sleeve_raglan', $size, ['head' => 'two_piece']);

            foreach (['front' => 0, 'back' => 1] as $side => $index) {
                $half = $this->piece($result, 'raglan-sleeve-'.$side);
                $edge = (int) array_search('shoulder', $half['meta']['edges'], true);

                $this->assertEqualsWithDelta(
                    $shoulders[$index],
                    Geometry::edgeLength($half['outline'], $edge),
                    static::TOLERANCE,
                    "سرشانه {$side} سایز {$size} باید عیناً روی آستین بیفتد.",
                );
            }
        }
    }

    public function test_raglan_two_piece_halves_share_one_seam_from_neck_to_wrist(): void
    {
        foreach (static::SIZES as $size) {
            $result = $this->apply('sleeve_raglan', $size, ['head' => 'two_piece']);
            $front = $this->piece($result, 'raglan-sleeve-front');
            $back = $this->piece($result, 'raglan-sleeve-back');

            $walk = PieceOps::walk($front, [1, 2], $back, [1, 2]);

            $this->assertLessThanOrEqual(
                static::TOLERANCE,
                abs($walk['difference']),
                "سایز {$size}: درز سرشانه تا مچ دو تکه باید هم‌اندازه باشد.",
            );
        }
    }

    public function test_raglan_dart_variant_keeps_the_neckline_length(): void
    {
        foreach (static::SIZES as $size) {
            $plain = $this->apply('sleeve_raglan', $size, ['head' => 'one_piece']);
            $darted = $this->apply('sleeve_raglan', $size, ['head' => 'dart', 'shoulder_shaping' => 2]);

            $plainSleeve = $this->piece($plain, 'raglan-sleeve');
            $dartedSleeve = $this->piece($darted, 'raglan-sleeve');

            $this->assertCount(1, $dartedSleeve['darts'], 'حالت ساسون‌دار باید یک ساسون سرشانه داشته باشد.');
            $this->assertEqualsWithDelta(2.0, $dartedSleeve['darts'][0]['intake'], 0.01);

            // خط یقه آستین پس از بستن ساسون همان طول حالت ساده را می‌دهد
            $this->assertEqualsWithDelta(
                PieceOps::seamLength($plainSleeve, 'neck'),
                PieceOps::seamLength($dartedSleeve, 'neck'),
                0.2,
                "سایز {$size}: ساسون سرشانه نباید خط یقه را کوتاه کند.",
            );
        }
    }

    /* ---------------------------------------------------------------------
     |  سدل
     * ------------------------------------------------------------------- */

    public function test_saddle_keeps_more_of_the_armhole_than_raglan(): void
    {
        foreach (static::SIZES as $size) {
            $saddle = $this->apply('sleeve_saddle', $size);
            $raglan = $this->apply('sleeve_raglan', $size);

            $saddleFront = $this->piece($saddle, 'bodice-front')['meta']['raglan'];
            $raglanFront = $this->piece($raglan, 'bodice-front')['meta']['raglan'];

            $this->assertGreaterThan(
                $raglanFront['lower_armhole'],
                $saddleFront['lower_armhole'],
                "سایز {$size}: در سدل باید بیشترِ حلقه روی بالاتنه بماند.",
            );
            $this->assertLessThan(
                $raglanFront['upper_armhole_taken'],
                $saddleFront['upper_armhole_taken'],
                'بند سدل باید باریک‌تر از بند رگلان باشد.',
            );

            $this->assertNotNull($this->part($saddle, 'sleeve'));
        }
    }

    /* ---------------------------------------------------------------------
     |  کیمونو و لوزی زیربغل
     * ------------------------------------------------------------------- */

    public function test_kimono_without_a_gusset_warns_about_arm_movement(): void
    {
        foreach (static::SIZES as $size) {
            $result = $this->apply('sleeve_kimono', $size, ['gusset' => false]);
            $notes = implode(' | ', $result['notes']);

            $this->assertNull($this->part($result, 'gusset'), 'بدون لوزی نباید قطعه لوزی ساخته شود.');
            $this->assertNull($result['meta']['sleeve']['gusset']);
            $this->assertStringContainsString('لوزی زیربغل ندارد', $notes, "سایز {$size}: باید هشدار بدهد.");
            $this->assertStringContainsString('بالا بردن دست', $notes, 'هشدار باید درباره حرکت دست باشد.');
        }
    }

    public function test_kimono_with_a_gusset_produces_a_diamond_of_the_stated_size(): void
    {
        foreach (static::SIZES as $size) {
            foreach ([7.0, 9.0, 12.0] as $wanted) {
                $result = $this->apply('sleeve_kimono', $size, ['gusset' => true, 'gusset_size' => $wanted]);
                $gusset = $this->piece($result, 'underarm-gusset');

                $this->assertSame('gusset', $gusset['meta']['part']);
                $this->assertCount(4, $gusset['outline'], 'لوزی باید چهار ضلع داشته باشد.');
                $this->assertEqualsWithDelta($wanted, $gusset['meta']['gusset_side'], 0.01);
                $this->assertEqualsWithDelta($wanted * M_SQRT2, $gusset['meta']['gusset_diagonal'], 0.02);

                foreach (array_keys($gusset['outline']) as $edge) {
                    $this->assertEqualsWithDelta(
                        $wanted,
                        Geometry::edgeLength($gusset['outline'], $edge),
                        0.02,
                        "سایز {$size}: هر چهار ضلع لوزی باید {$wanted} باشد.",
                    );
                }

                // روی هر دو قطعه باید چاک و نشانه لوزی گذاشته شده باشد
                foreach (['bodice-front', 'bodice-back'] as $code) {
                    $piece = $this->piece($result, $code);
                    $this->assertSame($wanted, $piece['meta']['grown_on']['gusset']);
                    $this->assertContains('gusset_slash', array_column($piece['markers'], 'key'));
                    $this->assertContains('gusset', array_column($piece['notches'], 'pair'));
                }

                $this->assertStringContainsString(
                    'لوزی زیربغل ساخته شد',
                    implode(' | ', $result['notes']),
                    'اندازه لوزی باید گزارش شود.',
                );
            }
        }
    }

    public function test_grown_on_sleeves_remove_the_armhole_edge_completely(): void
    {
        foreach (['sleeve_kimono', 'sleeve_dolman', 'sleeve_batwing'] as $key) {
            foreach (static::SIZES as $size) {
                $result = $this->apply($key, $size);

                foreach (['bodice-front', 'bodice-back'] as $code) {
                    $piece = $this->piece($result, $code);

                    $this->assertNotContains('armhole', $piece['meta']['edges'], "{$key} {$code}: حلقه باید کاملاً حذف شود.");
                    $this->assertArrayNotHasKey('armhole_edge', $piece['meta']);
                    $this->assertContains('shoulder', $piece['meta']['edges'], 'درز سرشانه تا سر آستین ادامه دارد.');
                }
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  دولمان و بت‌وینگ
     * ------------------------------------------------------------------- */

    public function test_dolman_and_batwing_keep_the_underarm_seam_equal_front_to_back(): void
    {
        foreach (['sleeve_kimono', 'sleeve_dolman', 'sleeve_batwing'] as $key) {
            foreach (static::SIZES as $size) {
                $result = $this->apply($key, $size);
                $front = $this->piece($result, 'bodice-front');
                $back = $this->piece($result, 'bodice-back');

                $walk = PieceOps::walk(
                    $front,
                    $this->edgeOf($front, 'underarm_seam'),
                    $back,
                    $this->edgeOf($back, 'underarm_seam'),
                );

                $this->assertLessThanOrEqual(
                    static::TOLERANCE,
                    abs($walk['difference']),
                    "{$key} سایز {$size}: درز زیر آستین جلو و پشت باید هم‌اندازه باشد.",
                );
                $this->assertTrue($result['meta']['sleeve']['underarm_matched']);

                // درز سرشانه تا سر آستین هم باید در دو طرف هم‌اندازه باشد
                $this->assertEqualsWithDelta(
                    $front['meta']['grown_on']['top_seam'],
                    $back['meta']['grown_on']['top_seam'],
                    static::TOLERANCE,
                    "{$key} سایز {$size}: درز سرشانه تا مچ.",
                );
            }
        }
    }

    public function test_batwing_is_deeper_and_flatter_than_dolman_and_kimono(): void
    {
        foreach (static::SIZES as $size) {
            $drops = [];
            $angles = [];

            foreach (['sleeve_kimono', 'sleeve_dolman', 'sleeve_batwing'] as $key) {
                $result = $this->apply($key, $size);
                $drops[$key] = $this->piece($result, 'bodice-front')['meta']['grown_on']['underarm_drop'];
                $angles[$key] = $result['meta']['sleeve']['angle'];
            }

            $this->assertGreaterThan($drops['sleeve_dolman'], $drops['sleeve_batwing'], "سایز {$size}");
            $this->assertGreaterThan($drops['sleeve_kimono'], $drops['sleeve_dolman']);
            $this->assertLessThan($angles['sleeve_dolman'], $angles['sleeve_batwing'], 'بت‌وینگ باید افقی‌تر باشد.');
        }
    }

    /* ---------------------------------------------------------------------
     |  شانه‌افتاده
     * ------------------------------------------------------------------- */

    public function test_drop_shoulder_lowers_the_armhole_by_exactly_the_requested_amount(): void
    {
        foreach (static::SIZES as $size) {
            foreach ([1.5, 3.0, 6.0] as $drop) {
                $before = $this->bodice($size);
                $result = $this->apply('sleeve_drop_shoulder', $size, ['armhole_drop' => $drop]);

                foreach (['bodice-front', 'bodice-back'] as $code) {
                    $original = $this->piece(['pieces' => $before], $code);
                    $piece = $this->piece($result, $code);

                    // فاصله زیر بغل تا سرگردن، تا از جابه‌جایی مبدأ قطعه مستقل باشد
                    $wasDeep = $original['outline'][3]['y'] - $original['outline'][1]['y'];
                    $isDeep = $piece['outline'][3]['y'] - $piece['outline'][1]['y'];

                    $this->assertEqualsWithDelta(
                        $drop,
                        $isDeep - $wasDeep,
                        0.01,
                        "{$code} سایز {$size}: زیر بغل باید دقیقاً {$drop} پایین برود.",
                    );
                    $this->assertSame($drop, $piece['meta']['drop_shoulder']['armhole_drop']);
                }
            }
        }
    }

    public function test_drop_shoulder_widens_the_shoulder_and_flattens_the_cap(): void
    {
        foreach (static::SIZES as $size) {
            $measurements = Measurements::fromSize($size);
            $setIn = (new SleeveGenerator())->generate($measurements, $this->ease(), []);
            $reference = $setIn[0]['meta']['cap_height'] / $setIn[0]['meta']['bicep_width'];

            $result = $this->apply('sleeve_drop_shoulder', $size);
            $sleeve = $this->piece($result, 'drop-shoulder-sleeve');
            $meta = $result['meta']['sleeve'];

            $this->assertLessThan(
                $reference,
                $sleeve['meta']['cap_height'] / $sleeve['meta']['bicep_width'],
                "سایز {$size}: سر آستین شانه‌افتاده باید از سر آستین حلقه‌ای تخت‌تر باشد.",
            );
            $this->assertLessThan($meta['set_in_cap_ratio'], $meta['cap_ratio']);
            $this->assertGreaterThanOrEqual(
                $setIn[0]['meta']['bicep_width'] - 0.01,
                $sleeve['meta']['bicep_width'],
                'آستین تخت‌تر باید دست‌کم به پهنای بازو باشد.',
            );

            // سرشانه واقعاً بلندتر شده و نشانه‌ها با حلقه جور مانده‌اند
            foreach (['front', 'back'] as $side) {
                $piece = $this->piece($result, 'bodice-'.$side);
                $this->assertGreaterThan(
                    $piece['meta']['drop_shoulder']['armhole_before'] * 0 + 0.4,
                    $piece['meta']['drop_shoulder']['extension'],
                    'سرشانه باید به بیرون کشیده شود.',
                );
                $this->assertLessThanOrEqual(static::TOLERANCE, $meta['notch_walk'][$side]);
            }
        }
    }

    public function test_drop_shoulder_reports_when_the_block_armhole_is_too_short_for_the_arm(): void
    {
        // با پایین‌آوردن کمِ زیر بغل، حلقه این بلوک برای دور بازو کوتاه می‌ماند و سبک
        // باید به‌جای پنهان‌کردن اختلاف، آن را با راه‌حل گزارش کند
        $tight = $this->apply('sleeve_drop_shoulder', '40', ['armhole_drop' => 0, 'cap_ease' => 1]);
        $roomy = $this->apply('sleeve_drop_shoulder', '40', ['armhole_drop' => 7, 'cap_ease' => 1]);

        $this->assertStringContainsString('کوتاه‌تر است', implode(' | ', $tight['notes']));
        $this->assertStringNotContainsString('کوتاه‌تر است', implode(' | ', $roomy['notes']));

        $this->assertEqualsWithDelta(
            1.0,
            $this->piece($roomy, 'drop-shoulder-sleeve')['meta']['cap_ease'],
            0.2,
            'با حلقه به‌اندازه، آزادی سرآستین باید همان چیزی باشد که خواسته شده.',
        );
    }

    /* ---------------------------------------------------------------------
     |  رد کردن مؤدبانه
     * ------------------------------------------------------------------- */

    public function test_supports_refuses_when_there_is_no_bodice_with_an_armhole(): void
    {
        $skirt = [[
            'code' => 'skirt-front',
            'name' => 'دامن جلو',
            'outline' => [
                Geometry::point(0, 0), Geometry::point(30, 0), Geometry::point(32, 60), Geometry::point(0, 60),
            ],
            'meta' => ['part' => 'skirt', 'edges' => ['waist', 'side', 'hem', 'default']],
            'notches' => [],
            'darts' => [],
        ]];

        foreach (static::KEYS as $key) {
            $answer = StyleRegistry::make($key)->supports($skirt, []);

            $this->assertIsString($answer, "{$key} نباید روی دامن پذیرفته شود.");
            $this->assertStringContainsString('حلقه آستین', $answer);
        }
    }

    public function test_supports_refuses_when_a_set_in_sleeve_is_already_there(): void
    {
        $pieces = array_merge(
            $this->bodice('40'),
            (new SleeveGenerator())->generate(Measurements::fromSize('40'), $this->ease(), []),
        );

        foreach (static::KEYS as $key) {
            $answer = StyleRegistry::make($key)->supports($pieces, []);

            $this->assertIsString($answer, "{$key} نباید کنار آستین حلقه‌ای پذیرفته شود.");
            $this->assertStringContainsString('آستین', $answer);
        }
    }

    public function test_supports_refuses_when_only_one_side_of_the_bodice_is_there(): void
    {
        $front = [$this->piece(['pieces' => $this->bodice('40')], 'bodice-front')];

        foreach (static::KEYS as $key) {
            $answer = StyleRegistry::make($key)->supports($front, []);

            $this->assertIsString($answer, "{$key} با یک طرف نباید پذیرفته شود.");
            $this->assertNotSame('', $answer);
        }
    }

    public function test_supports_refuses_a_bodice_whose_edges_are_not_in_order(): void
    {
        $pieces = $this->bodice('40');
        $pieces[0]['meta']['edges'] = ['neck', 'armhole', 'shoulder', 'side', 'waist', 'default'];

        $answer = StyleRegistry::make('sleeve_raglan')->supports($pieces, []);

        $this->assertIsString($answer);
        $this->assertStringContainsString('دنبال هم نیست', $answer);
    }
}
