<?php

namespace Tests\Unit\Simulation;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\SewingRelationBuilder;
use App\Services\Simulation\DrapeBody;
use App\Services\Simulation\DrapeGeometry;
use App\Services\Simulation\DrapePayloadService;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * بستهٔ «دوخت سه‌بعدی».
 *
 * جای خطای این بسته چهار تاست و همه‌شان بی‌صدا خراب می‌کنند:
 *   ۱. قطعهٔ روی تای پارچه باز نشود ⇐ نصف لباس روی مانکن می‌رود.
 *   ۲. بازهٔ رأس یک لبه یک واحد بلغزد ⇐ درزها در مرورگر جفت نمی‌شوند.
 *   ۳. نمونهٔ آینه‌شده جهت پیمایشش برنگردد ⇐ مثلث‌ها پشت‌ورو می‌شوند.
 *   ۴. رابطهٔ دوختی گم شود ⇐ لباس از همان‌جا باز می‌ماند.
 *
 * برای همین آزمون روی چند مدل واقعاً متفاوت کاتالوگ اجرا می‌شود: پیراهن (یوک و
 * آستین)، پیراهن مجلسی (آستر و دامن بلند)، شلوار (دو پاچه)، تنهٔ پرنسسی (درزی که
 * ده‌ها لبهٔ پشت‌سرهم است) و قبای چینی (قطعهٔ بلند روی تا).
 */
class DrapePayloadTest extends TestCase
{
    /** مدل‌هایی که بستهٔ آن‌ها سنجیده می‌شود. */
    protected const MODELS = [
        'shirt_classic', 'evening_a_line', 'pants_straight', 'bodice_princess_armhole', 'trad_qipao',
    ];

    protected const EASE = ['bust' => 8, 'waist' => 6, 'hip' => 6, 'neck' => 2, 'bicep' => 6];

    /* ---------------------------------------------------------------------
     |  ساختن الگو بدون دیتابیس
     * ------------------------------------------------------------------- */

    /** یک الگوی حاضر و آماده از روی یک مدل کاتالوگ (بدون ذخیره در دیتابیس). */
    protected function pattern(string $key, string $size = '40'): Pattern
    {
        $generator = GeneratorRegistry::make($key);
        $body = Measurements::fromSize($size);
        $pieces = $generator->generate($body, static::EASE, $generator->defaultParams());

        $pattern = new Pattern(['name' => $key, 'measurements' => $body]);
        $pattern->id = 1;
        $pattern->exists = true;
        $pattern->setRelation('pieces', collect($pieces)->values()->map(
            fn (array $piece, int $index) => $this->piece($piece, $index),
        ));

        return $pattern;
    }

    /** یک قطعهٔ الگو از خروجی ژنراتور. */
    protected function piece(array $piece, int $index): PatternPiece
    {
        $model = new PatternPiece(array_merge([
            'code' => 'piece-'.$index,
            'name' => 'قطعه',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'on_fold' => false,
            'mirror' => false,
            'outline' => [],
            'darts' => [],
            'notches' => [],
            'drills' => [],
            'pleats' => [],
            'markers' => [],
            'meta' => [],
            'sort' => $index * 10,
        ], array_intersect_key($piece, array_flip([
            'code', 'name', 'layer', 'cut_quantity', 'on_fold', 'mirror', 'outline',
            'grainline', 'darts', 'notches', 'drills', 'pleats', 'markers', 'meta', 'sort',
        ]))));

        $model->id = $index + 1;
        $model->exists = true;

        return $model;
    }

    protected function payload(string $key): array
    {
        return (new DrapePayloadService)->payload($this->pattern($key));
    }

    /** مساحت یک خط شکسته از خودِ بسته. */
    protected function polygonArea(array $polygon): float
    {
        $count = count($polygon);
        $sum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            [$ax, $ay] = $polygon[$i];
            [$bx, $by] = $polygon[($i + 1) % $count];
            $sum += ($ax * $by) - ($bx * $ay);
        }

        return $sum / 2;
    }

    /** محیط یک خط شکسته از خودِ بسته. */
    protected function polygonPerimeter(array $polygon): float
    {
        $count = count($polygon);
        $total = 0.0;

        for ($i = 0; $i < $count; $i++) {
            [$ax, $ay] = $polygon[$i];
            [$bx, $by] = $polygon[($i + 1) % $count];
            $total += sqrt((($ax - $bx) ** 2) + (($ay - $by) ** 2));
        }

        return $total;
    }

    /** @return array<string, array<string, mixed>> */
    protected function byId(array $payload): array
    {
        $out = [];

        foreach ($payload['pieces'] as $piece) {
            $out[$piece['id']] = $piece;
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  شکل کلی بسته
     * ------------------------------------------------------------------- */

    public function test_every_model_builds_a_complete_package(): void
    {
        foreach (static::MODELS as $key) {
            $payload = $this->payload($key);

            $this->assertSame(0.01, $payload['scale']);
            $this->assertNotEmpty($payload['pieces'], "بستهٔ «{$key}» هیچ قطعه‌ای ندارد.");
            $this->assertGreaterThan(0, $payload['budget']['target_edge']);
            $this->assertSame(6000, $payload['budget']['max_vertices']);

            foreach ($payload['pieces'] as $piece) {
                $this->assertGreaterThanOrEqual(3, count($piece['polygon']), "قطعهٔ {$piece['id']} خط شکستهٔ بسته ندارد.");
                $this->assertSame(count($piece['edges']), count($piece['edges']));
                $this->assertContains($piece['role'], ['torso', 'sleeve', 'skirt', 'leg', 'collar', 'detail']);
                $this->assertContains($piece['side'], ['front', 'back', 'left', 'right', null]);
                $this->assertContains($piece['placement']['zone'], [
                    'torso_front', 'torso_back', 'sleeve', 'skirt_front', 'skirt_back',
                    'leg_front', 'leg_back', 'collar', 'detail',
                ]);

                $this->assertGreaterThan(
                    0,
                    $this->polygonArea($piece['polygon']),
                    "جهت پیمایش قطعهٔ {$piece['id']} برگشته است؛ مثلث‌ها در مرورگر پشت‌ورو می‌شوند.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  ۱. باز کردن تای پارچه
     * ------------------------------------------------------------------- */

    public function test_a_piece_cut_on_fold_comes_back_whole(): void
    {
        $seen = 0;

        foreach (static::MODELS as $key) {
            $pattern = $this->pattern($key);
            $payload = (new DrapePayloadService)->payload($pattern);
            $pieces = $this->byId($payload);

            foreach ($pattern->pieces as $model) {
                if (! $model->on_fold || ($model->meta['fold_edges'] ?? []) === []) {
                    continue;
                }

                $seen++;
                $half = Geometry::area($model->outline);
                $whole = abs($this->polygonArea($pieces[$model->code.'#0']['polygon']));

                $this->assertEqualsWithDelta(
                    2 * $half,
                    $whole,
                    max(0.5, 0.01 * $half),
                    "قطعهٔ «{$model->code}» از «{$key}» روی تای پارچه است و باید دو برابر باز شود.",
                );
            }
        }

        $this->assertGreaterThanOrEqual(5, $seen, 'میان این مدل‌ها باید چند قطعهٔ روی تا باشد، وگرنه آزمون چیزی نسنجیده است.');
    }

    public function test_a_mirrored_instance_is_the_mirror_image_and_keeps_its_direction(): void
    {
        $pairs = 0;

        foreach (static::MODELS as $key) {
            $pieces = $this->byId($this->payload($key));

            foreach ($pieces as $id => $piece) {
                if (! $piece['mirrored']) {
                    continue;
                }

                $origin = $pieces[$piece['code'].'#'.($piece['instance'] - 1)] ?? null;

                if ($origin === null) {
                    continue;
                }

                $pairs++;

                $this->assertEqualsWithDelta(
                    abs($this->polygonArea($origin['polygon'])),
                    abs($this->polygonArea($piece['polygon'])),
                    0.05,
                    "نمونهٔ آینه‌شدهٔ «{$id}» هم‌مساحت نمونهٔ اصلی نیست.",
                );

                $this->assertGreaterThan(
                    0,
                    $this->polygonArea($piece['polygon']),
                    "آینه‌کردن جهت پیمایش «{$id}» را برگردانده و برنگشته است.",
                );

                $this->assertTrue($piece['placement']['flip'], "نمونهٔ «{$id}» باید flip داشته باشد.");
            }
        }

        $this->assertGreaterThanOrEqual(4, $pairs, 'میان این مدل‌ها باید چند جفت آینه‌ای باشد.');
    }

    /* ---------------------------------------------------------------------
     |  ۲. پل میان لبهٔ الگو و رأس خط شکسته
     * ------------------------------------------------------------------- */

    public function test_edge_spans_land_on_the_first_vertex_of_their_own_edge(): void
    {
        // یک مسیر دست‌ساز با منحنی در وسط و منحنی روی لبهٔ بسته‌شونده: همان جایی
        // که flatten رأس تکراری نمی‌سازد و شمارش به‌راحتی یک واحد می‌لغزد.
        $outline = [
            Geometry::point(0, 0),
            Geometry::point(20, 0),
            Geometry::curve(20, 30, 26, 15),
            Geometry::point(0, 30),
        ];

        foreach ([$outline, array_merge($outline, [Geometry::curve(0, 0, -6, 15)])] as $variant) {
            $flat = DrapeGeometry::flattenWithSpans($variant);

            $this->assertSame(Geometry::flatten($variant), $flat['polygon'], 'خط شکسته باید دقیقاً همان Geometry::flatten باشد.');
            $this->assertCount(count($variant), $flat['spans'], 'به ازای هر لبهٔ اصلی باید یک بازه باشد.');

            foreach ($flat['spans'] as $edge => [$start, $end]) {
                $this->assertEqualsWithDelta((float) $variant[$edge]['x'], $flat['polygon'][$start]['x'], 1e-6);
                $this->assertEqualsWithDelta((float) $variant[$edge]['y'], $flat['polygon'][$start]['y'], 1e-6);

                $next = $variant[($edge + 1) % count($variant)];
                $this->assertEqualsWithDelta((float) $next['x'], $flat['polygon'][$end]['x'], 1e-6);
                $this->assertEqualsWithDelta((float) $next['y'], $flat['polygon'][$end]['y'], 1e-6);
            }
        }
    }

    public function test_edges_tile_the_polygon_without_gap_or_overlap(): void
    {
        foreach (static::MODELS as $key) {
            foreach ($this->payload($key)['pieces'] as $piece) {
                $edges = $piece['edges'];
                $count = count($edges);

                $this->assertSame(0, $edges[0]['start'], "لبهٔ نخست قطعهٔ {$piece['id']} باید از رأس صفر شروع شود.");

                for ($i = 0; $i < $count; $i++) {
                    $this->assertSame(
                        $edges[($i + 1) % $count]['start'],
                        $edges[$i]['end'],
                        "لبه‌های قطعهٔ {$piece['id']} پشت سر هم نیستند (لبهٔ {$i}).",
                    );

                    $this->assertGreaterThanOrEqual(0, $edges[$i]['start']);
                    $this->assertLessThan(count($piece['polygon']), $edges[$i]['start']);
                }

                $this->assertEqualsWithDelta(
                    $this->polygonPerimeter($piece['polygon']),
                    array_sum(array_column($edges, 'length')),
                    0.1,
                    "مجموع طول لبه‌های قطعهٔ {$piece['id']} با محیط خط شکسته‌اش یکی نیست.",
                );
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  ۳. درزها
     * ------------------------------------------------------------------- */

    public function test_no_sewing_relation_is_lost_without_a_word(): void
    {
        foreach (static::MODELS as $key) {
            $payload = $this->payload($key);
            $total = $payload['meta']['relations'];

            $this->assertGreaterThan(0, $total, "«{$key}» باید دست‌کم یک رابطهٔ دوخت داشته باشد.");

            $covered = [];

            foreach ($payload['seams'] as $seam) {
                if ($seam['relation'] !== null) {
                    $covered[$seam['relation']] = true;
                }
            }

            foreach ($payload['meta']['unmatched'] as $miss) {
                $covered[$miss['relation']] = true;
                $this->assertNotSame('', $miss['reason'], 'هر رابطهٔ جفت‌نشده باید دلیل داشته باشد.');
            }

            $this->assertCount(
                $total,
                $covered,
                "بعضی رابطه‌های دوخت «{$key}» نه به درز رسیدند نه گزارش شدند.",
            );
        }
    }

    public function test_seam_arcs_point_at_real_vertices_and_report_their_ease(): void
    {
        foreach (static::MODELS as $key) {
            $payload = $this->payload($key);
            $pieces = $this->byId($payload);

            foreach ($payload['seams'] as $seam) {
                $this->assertContains($seam['kind'], ['seam', 'dart', 'fold']);

                foreach (['a', 'b'] as $side) {
                    $piece = $pieces[$seam[$side]['piece']] ?? null;

                    $this->assertNotNull($piece, "درز «{$seam['label']}» به قطعه‌ای بیرون از بسته اشاره می‌کند.");
                    $this->assertArrayHasKey($seam[$side]['from'], $piece['polygon']);
                    $this->assertArrayHasKey($seam[$side]['to'], $piece['polygon']);
                    $this->assertGreaterThan(0, $seam[$side]['length'], "کمان درز «{$seam['label']}» طول ندارد.");
                }

                $this->assertEqualsWithDelta(
                    $seam['b']['length'] - $seam['a']['length'],
                    $seam['ease'],
                    0.01,
                    "ease درز «{$seam['label']}» اختلاف طول دو سمت نیست.",
                );
            }
        }
    }

    public function test_most_seams_join_arcs_of_a_similar_length(): void
    {
        $total = 0;
        $close = 0;

        foreach (static::MODELS as $key) {
            foreach ($this->payload($key)['seams'] as $seam) {
                $longer = max($seam['a']['length'], $seam['b']['length']);
                $total++;

                if ($longer < 1e-6 || abs($seam['ease']) / $longer < 0.15) {
                    $close++;
                }
            }
        }

        $this->assertGreaterThan(0, $total);
        $this->assertGreaterThanOrEqual(
            0.7,
            $close / $total,
            'بیشتر درزها باید دو کمان هم‌اندازه را جفت کنند؛ اگر افت کرد یعنی جفت‌کردن کمان‌ها به‌هم ریخته.',
        );
    }

    public function test_a_panel_seam_joins_the_two_halves_of_the_princess_line(): void
    {
        $payload = $this->payload('bodice_princess_armhole');
        $panels = array_values(array_filter(
            $payload['seams'],
            fn (array $seam) => $seam['a']['length'] > 20 && str_contains($seam['a']['piece'], 'center'),
        ));

        $this->assertNotEmpty($panels, 'درز پرنسسی باید در بسته بیاید؛ این همان درزی است که یک لبه نیست، ده‌ها لبه است.');

        foreach ($panels as $seam) {
            $this->assertEqualsWithDelta(
                $seam['a']['length'],
                $seam['b']['length'],
                0.15 * $seam['a']['length'],
                'دو سمت درز پرنسسی باید تقریباً هم‌اندازه باشند.',
            );
        }
    }

    public function test_a_two_instance_piece_sews_left_to_left_and_right_to_right(): void
    {
        $payload = $this->payload('pants_straight');
        $pieces = $this->byId($payload);
        $checked = 0;

        foreach ($payload['seams'] as $seam) {
            $a = $pieces[$seam['a']['piece']];
            $b = $pieces[$seam['b']['piece']];

            if ($a['role'] !== 'leg' || $b['role'] !== 'leg') {
                continue;
            }

            $checked++;
            $this->assertSame(
                $a['mirrored'],
                $b['mirrored'],
                'درز پاچه باید پای چپ را به پای چپ بدوزد، نه پای چپ را به پای راست.',
            );
        }

        $this->assertGreaterThanOrEqual(6, $checked, 'شلوار راسته باید چند درز پاچه داشته باشد.');
    }

    /* ---------------------------------------------------------------------
     |  ۴. ساسون
     * ------------------------------------------------------------------- */

    public function test_darts_arrive_with_two_legs_and_an_apex(): void
    {
        $seen = 0;

        foreach (static::MODELS as $key) {
            foreach ($this->payload($key)['pieces'] as $piece) {
                foreach ($piece['darts'] as $dart) {
                    $seen++;

                    $this->assertCount(2, $dart['legs'], "ساسون قطعهٔ {$piece['id']} دو ساق ندارد.");
                    $this->assertCount(2, $dart['apex']);
                    $this->assertGreaterThan(0, $dart['intake'], "پهنای ساسون قطعهٔ {$piece['id']} صفر است.");
                    $this->assertIsInt($dart['on_edge']);
                    $this->assertArrayHasKey($dart['on_edge'], $piece['edges']);

                    foreach (['start', 'end'] as $key2) {
                        if ($dart[$key2] !== null) {
                            $this->assertArrayHasKey($dart[$key2], $piece['polygon']);
                        }
                    }
                }
            }
        }

        $this->assertGreaterThanOrEqual(6, $seen, 'میان این مدل‌ها باید چند ساسون باشد.');
    }

    public function test_a_dart_of_an_unfolded_piece_is_mirrored_too(): void
    {
        $pieces = $this->byId($this->payload('trad_qipao'));
        $front = $pieces['qipao-front#0'];

        // قبای چینی روی تا بریده می‌شود و دو ساسون دارد؛ بعد از باز شدن باید چهار
        // تا باشد، وگرنه نیمهٔ دوم لباس بی‌ساسون و گشاد می‌ماند.
        $this->assertCount(4, $front['darts']);

        $xs = array_column($front['polygon'], 0);
        $centre = (min($xs) + max($xs)) / 2;
        $left = array_filter($front['darts'], fn (array $dart) => $dart['apex'][0] < $centre);

        $this->assertCount(2, $left, 'ساسون‌ها باید روی هر دو نیمهٔ قطعهٔ باز‌شده پخش باشند.');
    }

    /* ---------------------------------------------------------------------
     |  ۵. چیدن اولیه
     * ------------------------------------------------------------------- */

    public function test_the_front_and_the_back_do_not_sit_on_the_same_angle(): void
    {
        foreach (static::MODELS as $key) {
            foreach ($this->payload($key)['pieces'] as $piece) {
                $zone = $piece['placement']['zone'];
                $u0 = $piece['placement']['u0'];
                $u1 = $piece['placement']['u1'];

                $this->assertLessThanOrEqual($u1, $u0, "بازهٔ زاویه‌ای {$piece['id']} وارونه است.");

                if (in_array($zone, ['torso_front', 'skirt_front'], true)) {
                    $this->assertGreaterThanOrEqual(-(M_PI / 2) - 1e-3, $u0, "قطعهٔ جلوی {$piece['id']} از نیمهٔ جلو بیرون زده.");
                    $this->assertLessThanOrEqual((M_PI / 2) + 1e-3, $u1, "قطعهٔ جلوی {$piece['id']} از نیمهٔ جلو بیرون زده.");
                }

                if (in_array($zone, ['torso_back', 'skirt_back'], true)) {
                    $this->assertGreaterThanOrEqual((M_PI / 2) - 1e-3, $u0, "قطعهٔ پشتِ {$piece['id']} روی مرکز جلو افتاده.");
                    $this->assertLessThanOrEqual((3 * M_PI / 2) + 1e-3, $u1, "قطعهٔ پشتِ {$piece['id']} روی مرکز جلو افتاده.");
                }
            }
        }
    }

    public function test_panels_of_one_zone_are_laid_side_by_side_not_on_top_of_each_other(): void
    {
        $pieces = $this->byId($this->payload('bodice_princess_armhole'));
        $centre = $pieces['front-center#0']['placement'];
        $side = $pieces['front-side#0']['placement'];

        $this->assertGreaterThanOrEqual(
            $centre['u1'] - 1e-3,
            $side['u0'],
            'پنل پهلوی پرنسسی باید بیرون از تنهٔ مرکزی بنشیند، نه رویش.',
        );
    }

    public function test_a_sleeve_does_not_start_inside_the_body(): void
    {
        foreach (['shirt_classic', 'trad_qipao'] as $key) {
            $sleeves = array_filter($this->payload($key)['pieces'], fn (array $piece) => $piece['role'] === 'sleeve');

            $this->assertNotEmpty($sleeves, "«{$key}» باید آستین داشته باشد.");

            foreach ($sleeves as $sleeve) {
                $this->assertSame('sleeve', $sleeve['placement']['zone'], 'آستین باید روی دستگاه بازو چیده شود، نه دور تنه.');
                $this->assertContains($sleeve['placement']['radius_hint'], ['bicep', 'wrist']);
                $this->assertContains($sleeve['side'], ['left', 'right']);
            }
        }
    }

    public function test_every_radius_hint_is_a_level_the_viewer_knows(): void
    {
        // نام‌ها باید با جدول radii در garment-viewer.js یکی باشند، وگرنه مرورگر
        // شعاع پیش‌فرض می‌گذارد و قطعه جای دیگری می‌نشیند.
        $known = [
            'hip', 'highHip', 'waist', 'underBust', 'bust', 'neck', 'armhole',
            'bicep', 'wrist', 'thigh', 'knee', 'ankle', 'shoulder',
        ];

        foreach (static::MODELS as $key) {
            foreach ($this->payload($key)['pieces'] as $piece) {
                $this->assertContains($piece['placement']['radius_hint'], $known);
                $this->assertGreaterThan(0.0, $piece['placement']['y_top']);
                $this->assertLessThanOrEqual(1.0, $piece['placement']['y_top']);
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  ۶. حالت‌های مرزی
     * ------------------------------------------------------------------- */

    public function test_a_pattern_without_any_sewing_relation_still_builds(): void
    {
        $pattern = new Pattern(['name' => 'تک‌قطعه', 'measurements' => Measurements::fromSize('40')]);
        $pattern->id = 1;
        $pattern->exists = true;
        $pattern->setRelation('pieces', collect([
            $this->piece([
                'code' => 'lonely',
                'name' => 'قطعهٔ تنها',
                'outline' => [
                    Geometry::point(0, 0),
                    Geometry::point(30, 0),
                    Geometry::curve(30, 50, 36, 25),
                    Geometry::point(0, 50),
                ],
                'meta' => ['edges' => ['waist', 'default', 'hem', 'default']],
            ], 0),
        ]));

        $payload = (new DrapePayloadService)->payload($pattern);

        $this->assertSame([], SewingRelationBuilder::suggest($pattern));
        $this->assertCount(1, $payload['pieces']);
        $this->assertSame([], $payload['seams']);
        $this->assertSame([], $payload['meta']['unmatched']);
        $this->assertGreaterThan(0, $this->polygonArea($payload['pieces'][0]['polygon']));
    }

    public function test_a_pattern_without_any_piece_builds_an_empty_package(): void
    {
        $pattern = new Pattern(['name' => 'خالی', 'measurements' => []]);
        $pattern->id = 1;
        $pattern->exists = true;
        $pattern->setRelation('pieces', collect());

        $payload = (new DrapePayloadService)->payload($pattern);

        $this->assertSame([], $payload['pieces']);
        $this->assertSame([], $payload['seams']);
        $this->assertSame(0, $payload['meta']['relations']);
    }

    public function test_a_broken_piece_is_reported_and_does_not_break_the_package(): void
    {
        $pattern = new Pattern(['name' => 'ناقص', 'measurements' => Measurements::fromSize('40')]);
        $pattern->id = 1;
        $pattern->exists = true;
        $pattern->setRelation('pieces', collect([
            $this->piece(['code' => 'broken', 'outline' => [Geometry::point(0, 0), Geometry::point(5, 0)]], 0),
        ]));

        $payload = (new DrapePayloadService)->payload($pattern);

        $this->assertSame([], $payload['pieces']);
        $this->assertNotEmpty($payload['meta']['notes'], 'قطعهٔ ناقص باید گزارش شود، نه اینکه بی‌صدا بیفتد.');
    }
    /**
     * حلقهٔ آستینِ یوک بی‌دوخت نمی‌ماند.
     *
     * پیراهنِ یوک‌دار: سرِ آستین باید هم به حلقهٔ تنه برسد و هم به آن تکه از
     * حلقه که روی یوک افتاده. رابطه‌های سازنده یک درز می‌نویسند و لبهٔ ۵٫۹
     * سانتی‌متریِ یوک بی‌شریک می‌ماند؛ آن وقت ۱۸٫۴ سانتی‌متر سرآستین روی ۱۱٫۴
     * سانتی‌متر حلقه چپانده می‌شود و روی مانکن یک زبانهٔ آزاد سر شانه می‌ماند.
     * این همان چیزی بود که در نمای سه‌بعدی دیده شد.
     */
    public function test_the_yoke_armhole_finds_its_sleeve(): void
    {
        $payload = $this->payload('shirt_classic');
        $joined = [];

        foreach ($payload['seams'] as $seam) {
            foreach ([['a', 'b'], ['b', 'a']] as [$one, $two]) {
                if (str_starts_with($seam[$one]['piece'], 'yoke') && str_starts_with($seam[$two]['piece'], 'sleeve')) {
                    $joined[$seam[$two]['piece']] = $seam;
                }
            }
        }

        $this->assertCount(2, $joined, 'هر دو آستین باید به حلقهٔ یوکِ سمت خودشان دوخته شوند.');

        foreach ($joined as $sleeve => $seam) {
            $this->assertEqualsWithDelta(
                $seam['a']['length'],
                $seam['b']['length'],
                0.25 * max($seam['a']['length'], $seam['b']['length']),
                "درز یوک و «{$sleeve}» باید دو کمانِ هم‌اندازه را جفت کند.",
            );
        }

        // و سرِ آستین دیگر روی حلقهٔ کوچکِ تنه چپانده نمی‌شود
        foreach ($payload['seams'] as $seam) {
            if (! str_starts_with($seam['a']['piece'], 'sleeve') || ! str_contains($seam['b']['piece'], 'back')) {
                continue;
            }

            $longer = max($seam['a']['length'], $seam['b']['length']);

            $this->assertLessThan(
                0.25,
                abs($seam['ease']) / $longer,
                'حلقهٔ آستین به تنهٔ پشت باید هم‌اندازه باشد؛ اضافه‌اش سهمِ یوک است.',
            );
        }
    }

    /**
     * کمانِ روی مرکزِ پشت به هیچ سمتی تعلق ندارد.
     *
     * تا وقتی سمت را از خودِ قطعه می‌گرفتیم، هر دو حلقهٔ یوک «چپ» بودند و
     * جریمهٔ سمتِ مخالف آستینِ راست را از یوک دور می‌کرد. سمت، مالِ کمان است
     * نه مالِ قطعه.
     */
    public function test_a_centre_piece_reaches_both_sides_of_the_body(): void
    {
        $payload = $this->payload('shirt_classic');
        $sides = [];

        foreach ($payload['seams'] as $seam) {
            foreach ([['a', 'b'], ['b', 'a']] as [$one, $two]) {
                if (str_starts_with($seam[$one]['piece'], 'yoke') && str_starts_with($seam[$two]['piece'], 'sleeve')) {
                    $sides[] = $seam[$two]['piece'];
                }
            }
        }

        $this->assertCount(2, array_unique($sides), 'یوکِ روی تای پارچه باید به آستینِ چپ و راست، هر دو، برسد.');
    }

    /**
     * یقه از خط یقه‌اش دوخته می‌شود، نه از لبهٔ بیرونی.
     *
     * پنج جای کاتالوگ یقه را با برچسبِ جابه‌جا می‌ساختند: لبهٔ بیرونیِ آزاد
     * «neck» و خط یقه «default». نمای سه‌بعدی به همان برچسب اعتماد می‌کند، پس
     * یقه را وارونه می‌دوخت — ۲۷٫۸ سانتی‌متر لبهٔ بیرونی روی خط یقهٔ ۲۴
     * سانتی‌متری — و دور گردن چین می‌خورد و بالا می‌زد.
     *
     * ملاک اندازه‌پذیر است: کمانی که به خط یقهٔ تنه دوخته می‌شود باید هم‌اندازهٔ
     * آن باشد، و کوتاه‌ترین لبهٔ یقه هم نباید به تنه برسد.
     */
    public function test_a_collar_is_sewn_by_its_neck_edge(): void
    {
        foreach (['shirt_classic', 'shirt_band_collar', 'shirt_oversized'] as $key) {
            if (! GeneratorRegistry::has($key)) {
                continue;
            }

            $payload = $this->payload($key);
            $checked = 0;

            foreach ($payload['seams'] as $seam) {
                foreach ([['a', 'b'], ['b', 'a']] as [$one, $two]) {
                    if (! str_contains($seam[$one]['piece'], 'collar')) {
                        continue;
                    }

                    if (! preg_match('/front|back|yoke/', $seam[$two]['piece'])) {
                        continue;
                    }

                    $checked++;

                    $this->assertLessThan(
                        0.3,
                        abs($seam['ease']) / max(0.01, max($seam['a']['length'], $seam['b']['length'])),
                        "«{$key}»: کمانِ یقه و خط یقه باید هم‌اندازه باشند؛ اگر نیست یعنی یقه از لبهٔ اشتباه دوخته شده.",
                    );
                }
            }

            $this->assertGreaterThan(0, $checked, "«{$key}» باید یقه‌اش به خط یقه دوخته شود.");
        }
    }
    /**
     * پنل‌های یک آستین کنار هم می‌نشینند، نه روی هم.
     *
     * آستین دوتکه دو پنل دارد و هر دو «آستین»اند؛ پیش از این هر دو وسط‌چین
     * می‌شدند و u = -π..π می‌گرفتند، یعنی هر دو تمامِ دور بازو را ادعا می‌کردند
     * و از قدم اول در هم فرو می‌رفتند. پوششِ آستین روی کت ۴۵ درجه از ۳۶۰
     * اندازه گرفته شد و روی کت‌وشلوار ۶۰ — بازو عملاً لخت.
     */
    public function test_sleeve_panels_share_the_arm(): void
    {
        $seen = 0;

        foreach (['blazer', 'suit_jacket'] as $key) {
            if (! GeneratorRegistry::has($key)) {
                continue;
            }

            $payload = $this->payload($key);
            $arms = [];

            foreach ($payload['pieces'] as $piece) {
                if (($piece['role'] ?? '') !== 'sleeve' || ($piece['meta']['part'] ?? '') === 'cuff') {
                    continue;
                }

                // سبد فقط «سمتِ بدن» است، نه ارتفاع: پنل‌های یک آستین از زیربغل
                // هم‌تراز می‌شوند و چون کپشان هم‌اندازه نیست، سرِ قطعه‌شان روی یک
                // ارتفاع نمی‌ماند (کت رسمی: ۱۱٫۹ سانتی‌متر اختلاف)
                $arms[(string) $piece['side']][] = $piece;
            }

            foreach ($arms as $panels) {
                if (count($panels) < 2) {
                    continue;
                }

                $seen++;
                $span = 0.0;

                foreach ($panels as $panel) {
                    $one = (float) $panel['placement']['u0'];
                    $two = (float) $panel['placement']['u1'];
                    $span += $two - $one;

                    $this->assertLessThan(
                        2 * M_PI - 0.01,
                        $two - $one,
                        "«{$key}»: پنلِ {$panel['id']} تمامِ دور بازو را برداشته؛ پنلِ دیگر جایی ندارد.",
                    );
                }

                // هم‌پوشانی مجاز است (جای درز)، ولی نه دو برابرِ دورِ بازو
                $this->assertLessThan(
                    2 * (2 * M_PI),
                    $span,
                    "«{$key}»: مجموعِ کمانِ پنل‌ها دو برابرِ دورِ بازو شد؛ روی هم افتاده‌اند.",
                );
            }
        }

        $this->assertGreaterThan(0, $seen, 'هیچ آستین دوتکه‌ای پیدا نشد؛ آزمون چیزی را نسنجید.');
    }

    /**
     * و کنارِ هم، *هم‌تراز از زیربغل* — نه از سرِ قطعه.
     *
     * دو تکهٔ یک آستین از درزِ پهلو به هم دوخته می‌شوند و آن درز از زیربغل شروع
     * می‌شود؛ پس زیربغلِ هر دو باید روی یک ارتفاع بنشیند. تا وقتی سرِ قطعه‌ها
     * هم‌تراز می‌شد، کت رسمی — که سرآستینِ رویش کپِ ۱۱٫۹ سانتی‌متری دارد و
     * آستین زیرش هیچ — با ۱۱٫۹ سانتی‌متر اختلاف چیده می‌شد و دم آستینِ زیر همان
     * اندازه بالاتر می‌افتاد، در حالی که درزِ پهلوی هر دو ۴۸٫۲ سانتی‌متر است.
     * قیدِ درز باید همان را جبران می‌کرد: آستین روی بازو مچاله می‌شد و ساعد لخت
     * می‌ماند (پوششِ دورِ بازو ۹۰ درجه از ۳۶۰، و ۲۳۶۱ پیکسل پوستِ قرمز در عکس).
     */
    public function test_sleeve_panels_are_levelled_at_the_underarm(): void
    {
        $seen = 0;

        foreach (['blazer', 'coat_trench', 'suit_jacket'] as $key) {
            if (! GeneratorRegistry::has($key)) {
                continue;
            }

            $pattern = $this->pattern($key);
            $height = (new DrapeBody(Measurements::complete($pattern->measurements ?? [])))->height;
            $arms = [];

            foreach ((new DrapePayloadService)->payload($pattern)['pieces'] as $piece) {
                // فقط پنل‌های خودِ آستین؛ مچ‌بند و تسمهٔ مچ دورِ مچ می‌پیچند و
                // ربطی به زیربغل ندارند (ترنچ‌کت تسمه دارد)
                if (($piece['role'] ?? '') !== 'sleeve' || preg_match('/cuff|strap/', (string) $piece['id'])) {
                    continue;
                }

                $under = $this->underarmOf($piece);

                if ($under === null) {
                    continue;
                }

                // ارتفاعِ زیربغلِ این پنل روی بدن، سانتی‌متر
                $arms[$piece['side'].'|'.$piece['layer']][$piece['id']] =
                    ((float) $piece['placement']['y_top'] * $height) - $under;
            }

            foreach ($arms as $side => $levels) {
                if (count($levels) < 2) {
                    continue;
                }

                $seen++;

                $this->assertLessThan(
                    0.5,
                    max($levels) - min($levels),
                    "«{$key}» بازوی {$side}: زیربغلِ پنل‌های یک آستین روی یک ارتفاع ننشسته؛ "
                        .'درزِ پهلوشان هم‌طول است و باید از یک نقطه شروع شود.',
                );
            }
        }

        $this->assertGreaterThan(0, $seen, 'هیچ آستین دوتکه‌ای پیدا نشد؛ آزمون چیزی را نسنجید.');
    }

    /**
     * فاصلهٔ سرِ یک پنلِ آستین تا سرِ درزِ پهلوی خودش، سانتی‌متر.
     *
     * @param  array<string, mixed>  $piece
     */
    protected function underarmOf(array $piece): ?float
    {
        $polygon = $piece['polygon'] ?? [];
        $top = null;
        $seam = null;

        foreach ($polygon as $point) {
            $top = $top === null ? (float) $point[1] : min($top, (float) $point[1]);
        }

        foreach ($piece['edges'] ?? [] as $edge) {
            if (($edge['tag'] ?? '') !== 'side') {
                continue;
            }

            foreach ([$edge['start'], $edge['end']] as $at) {
                $y = (float) ($polygon[$at][1] ?? 0);
                $seam = $seam === null ? $y : min($seam, $y);
            }
        }

        return ($top === null || $seam === null) ? null : $seam - $top;
    }

    /**
     * هیچ کمانی دو بار کامل ادعا نمی‌شود.
     *
     * کمربندِ دامنِ کلوش یک نوارِ راستِ ۴۲٫۵ سانتی‌متری است و خط کمرِ دامن ۱۲
     * کمان؛ سازندهٔ رابطه‌ها برای هر کمان یک رابطه می‌نویسد و در همه‌شان همان یک
     * نوار را می‌گذارد. share() باید نوار را میانشان ببُرد — ولی بریدن روی رأس
     * انجام می‌شود و نوارِ راست با گامِ ۵ سانتی‌متری تنها ۸ پاره داشت. splitArc
     * نمی‌توانست ۱۲ تکه بسازد و بی‌صدا رد می‌شد، پس هر دوازده رابطه کلِ ۴۲٫۵
     * سانتی‌متر را می‌گرفتند: نوار از دوازده جا هم‌زمان کشیده می‌شد و اختلاف طولِ
     * درز به ۳۶ سانتی‌متر می‌رسید.
     */
    public function test_no_arc_is_claimed_twice(): void
    {
        $seen = 0;

        foreach (['skirt_circle_full', 'dress_wrap', 'shirt_classic'] as $key) {
            if (! GeneratorRegistry::has($key)) {
                continue;
            }

            $payload = $this->payload($key);
            $claims = [];

            foreach ($payload['seams'] as $seam) {
                foreach (['a', 'b'] as $end) {
                    $claims[$seam[$end]['piece'].'|'.$seam[$end]['from'].'..'.$seam[$end]['to']][] = $seam['label'];
                }
            }

            foreach ($claims as $arc => $labels) {
                $seen++;

                $this->assertLessThan(
                    2,
                    count($labels),
                    "«{$key}»: کمانِ {$arc} را ".count($labels).' رابطه کامل ادعا کرده‌اند ('
                        .implode('، ', array_unique($labels)).')؛ باید میانشان بریده شود.',
                );
            }
        }

        $this->assertGreaterThan(0, $seen, 'هیچ درزی سنجیده نشد.');
    }
    /**
     * یقهٔ ایستاده به خط یقه دوخته می‌شود، نه به آستین.
     *
     * bandPiece برای کمربند و بند نوشته شده و لبه‌های بلندش را «default»
     * می‌گذارد؛ standCollarPiece همان را برمی‌داشت، پس یقهٔ ایستاده هیچ لبهٔ
     * «neck» نداشت و کمانِ آزادِ خط یقه هیچ‌وقت شریکش نمی‌شد. روی قپائو از
     * ۱۰۹ سانتی‌متر محیط تنها ۴٫۵ دوخته بود — و آن ۴٫۵ هم به سجاف رفته بود، نه
     * به گردن. یقه آزاد دور گردن شناور می‌ماند و آستین را هم با خودش می‌کشید.
     * نُه لباس همین یقه را دارند.
     *
     * و درز پهلو میان دو قطعهٔ هم‌نقش است: درز زیربغلِ آستینِ قپائو ۴ سانتی‌متر
     * است و لبهٔ کنارِ یقه‌بند ۴٫۵ — اختلاف ۱۱٪، زیر حدِ ۱۲٪ — پس آستین به یقه
     * دوخته می‌شد. پوششِ آستین روی بازو ۳۰ درجه از ۳۶۰ اندازه گرفته شد.
     */
    public function test_a_stand_collar_reaches_the_neckline(): void
    {
        $seen = 0;

        foreach (['trad_qipao', 'coat_cape', 'jacket_anorak', 'dress_shirtdress'] as $key) {
            if (! GeneratorRegistry::has($key)) {
                continue;
            }

            $payload = $this->payload($key);
            $stand = null;

            foreach ($payload['pieces'] as $piece) {
                if (str_contains($piece['id'], 'collar-stand')) {
                    $stand = $piece['id'];

                    break;
                }
            }

            if ($stand === null) {
                continue;
            }

            $seen++;
            $sewn = 0.0;
            $wrong = [];

            foreach ($payload['seams'] as $seam) {
                foreach ([['a', 'b'], ['b', 'a']] as [$one, $two]) {
                    if ($seam[$one]['piece'] !== $stand) {
                        continue;
                    }

                    $sewn += (float) $seam[$one]['length'];

                    if (str_contains($seam[$two]['piece'], 'sleeve')) {
                        $wrong[] = $seam[$two]['piece'];
                    }
                }
            }

            $this->assertSame(
                [],
                $wrong,
                "«{$key}»: یقهٔ ایستاده به آستین دوخته شد؛ درز پهلو میان دو قطعهٔ هم‌نقش است.",
            );

            $this->assertGreaterThan(
                20.0,
                $sewn,
                "«{$key}»: از یقهٔ ایستاده تنها {$sewn} سانتی‌متر دوخته شد؛ لبهٔ neck ندارد.",
            );
        }

        $this->assertGreaterThan(0, $seen, 'هیچ یقهٔ ایستاده‌ای پیدا نشد؛ آزمون چیزی را نسنجید.');
    }
    /**
     * جیب به آستین دوخته نمی‌شود.
     *
     * «دوخت به قطعه‌ی همسایه» چارهٔ آخر است: قطعهٔ بی‌درز را به نزدیک‌ترین کمانِ
     * آزاد می‌دوزد. ولی فاصله را distance() می‌سنجد و آن برای دو دستگاهِ متفاوت
     * (تنه و بازو) فقط اختلافِ ارتفاع را برمی‌گرداند و زاویه را کنار می‌گذارد —
     * چون زاویهٔ روی بازو با زاویهٔ روی تنه یکی نیست. پس جیبِ روی تنه و کمانِ
     * آستین در یک ارتفاع «فاصلهٔ صفر» می‌گرفتند: روی ترنچ‌کت، جیب و حلقهٔ کمربند
     * و سجافِ گردن هر سه به آستین دوخته شدند و آستین را ۱۶ سانتی‌متر روی بازو
     * پایین کشیدند.
     *
     * درزِ واقعیِ میان‌دستگاهی — آستین به حلقه — از برچسبِ armhole می‌آید نه از
     * این چارهٔ آخر، پس این ادعا آن را نمی‌گیرد.
     */
    public function test_a_detail_is_not_sewn_across_frames(): void
    {
        $frame = fn (string $role) => match ($role) {
            'sleeve' => 'arm',
            'leg' => 'limb',
            default => 'body',
        };

        $seen = 0;

        foreach (['coat_trench', 'blazer', 'suit_jacket', 'trad_qipao'] as $key) {
            if (! GeneratorRegistry::has($key)) {
                continue;
            }

            $payload = $this->payload($key);
            $roles = [];

            foreach ($payload['pieces'] as $piece) {
                $roles[$piece['id']] = (string) ($piece['role'] ?? 'torso');
            }

            foreach ($payload['seams'] as $seam) {
                if ($seam['label'] !== 'دوخت به قطعه‌ی همسایه') {
                    continue;
                }

                $seen++;

                $one = $frame($roles[$seam['a']['piece']] ?? 'torso');
                $two = $frame($roles[$seam['b']['piece']] ?? 'torso');

                $this->assertSame(
                    $one,
                    $two,
                    "«{$key}»: {$seam['a']['piece']} ({$one}) به {$seam['b']['piece']} ({$two}) دوخته شد؛"
                        .' فاصلهٔ میان دو دستگاه سنجیدنی نیست.',
                );
            }
        }

        $this->assertGreaterThan(0, $seen, 'هیچ درزِ همسایه‌ای پیدا نشد؛ آزمون چیزی را نسنجید.');
    }

    /**
     * نوار فاقِ بادی لایهٔ داخلی است، نه یک قطعهٔ آزاد روی یقه.
     *
     * الگوریتمِ قدیمی لبهٔ 14 سانتی‌متری آن را به نزدیک‌ترین لبهٔ هم‌طولِ تنه،
     * یعنی خط یقه، می‌دوخت. خروجیِ بصری همان فلپِ کج روی شانه بود.
     */
    public function test_loose_bodysuit_gusset_is_not_adopted_by_the_neck(): void
    {
        $payload = $this->payload('top_bodysuit');

        $this->assertSame([], array_values(array_filter(
            $payload['pieces'],
            fn (array $piece) => str_contains((string) $piece['code'], 'bodysuit-gusset'),
        )));

        $crotch = [];

        foreach ($payload['seams'] as $seam) {
            $ends = [(string) $seam['a']['piece'], (string) $seam['b']['piece']];
            $this->assertFalse(
                collect($ends)->contains(fn (string $id) => str_contains($id, 'bodysuit-gusset')),
                'نوار فاق نباید با دوختِ حدسی به یقه یا هر قطعهٔ دیگری برسد.',
            );

            if (($seam['label'] ?? '') === 'درز فاق بادی') {
                $crotch[] = $seam;
                $this->assertStringContainsString('bodysuit-front', implode('|', $ends));
                $this->assertStringContainsString('bodysuit-back', implode('|', $ends));
            }
        }

        $this->assertNotEmpty($crotch, 'لبهٔ فاق جلو و پشت بادی باید صریحاً به هم دوخته شوند.');
    }

    /**
     * قطعهٔ آینه‌شده تکراری نیست؛ آن یکی طرف است.
     *
     * پاچهٔ راست و چپ یک کد دارند، یک بازهٔ زاویه‌ای و یک ارتفاع — فرقشان فقط
     * آینه بودن است. حذفِ «هم‌جا»ها دومی را می‌انداخت و شلوار روی مانکن یک پاچه
     * داشت. هر مدلی که قطعهٔ آینه‌شده دارد باید هر دو نمونه‌اش را ببیند.
     */
    public function test_a_mirrored_piece_is_not_dropped_as_a_duplicate(): void
    {
        foreach (['pants_straight', 'blazer'] as $key) {
            $payload = $this->payload($key);
            $byCode = [];

            foreach ($payload['pieces'] as $piece) {
                $byCode[$piece['code']][] = $piece;
            }

            $pattern = $this->pattern($key);
            $checked = 0;

            foreach ($pattern->pieces as $model) {
                if (! $model->mirror || (int) $model->cut_quantity < 2) {
                    continue;
                }

                // رویهٔ لپه و سجاف و کیسهٔ جیب درونِ لباس‌اند و عمداً در بسته نمی‌آیند (ببینید DrapePayloadService::payload)
                if (in_array($model->meta['part'] ?? null, ['lapel', 'facing', 'pocket-bag', 'bag'], true)) {
                    continue;
                }

                $checked++;
                $instances = $byCode[$model->code] ?? [];

                $this->assertCount(
                    2,
                    $instances,
                    "«{$key}»: قطعهٔ آینه‌شدهٔ {$model->code} باید دو نمونه داشته باشد.",
                );

                $this->assertNotSame(
                    $instances[0]['mirrored'],
                    $instances[1]['mirrored'],
                    "«{$key}»: دو نمونهٔ {$model->code} هر دو یک طرف‌اند.",
                );
            }

            $this->assertGreaterThan(0, $checked, "«{$key}» هیچ قطعهٔ آینه‌شده‌ای نداشت؛ آزمون چیزی را نسنجید.");
        }
    }

    /**
     * دو بازو باید یک‌جور دوخته شوند.
     *
     * حلقهٔ آستین میان دو پنلِ آستینِ دوتکه تقسیم می‌شود، و تقسیمش به ترتیبِ
     * راه‌رفتن روی کمان بود. نمونهٔ آینه‌شده کمان را از سرِ دیگر می‌پیماید، پس
     * روی یک بازو پنلِ بالا به سرشانه می‌خورد و روی بازوی دیگر به زیربغل. اندازه
     * گرفته شد: همبستگیِ ارتفاعِ دو سرِ درز روی نمونهٔ اول ‎+۰٫۹۹ بود و روی
     * آینه‌اش ‎−۰٫۹۸، و آستین ده سانتی‌متر از شانه سُر می‌خورد.
     *
     * سنجه‌اش ساده است: هر درزی که روی یک بازو هست، باید جفتِ هم‌اندازه‌ای روی
     * بازوی دیگر داشته باشد.
     *
     * کت رسمی هم این‌جاست. ناقرینگیِ آن از جای دیگری می‌آمد: حلقه‌اش روی چهار
     * پنل پخش است و suggest() فقط مرکز جلو و مرکز پشت را می‌شناخت، پس نیمی از
     * سرآستین بی‌شریک می‌ماند و splice() سرِخود پُرش می‌کرد — روی یک بازو با
     * «پهلوی پشت» و روی آن یکی با «مرکز پشت». حالا هر پنلی که حلقه دارد سهم
     * خودش را از سرآستین می‌گیرد و چیزی برای حدس زدن نمی‌ماند.
     */
    public function test_both_sleeves_are_stitched_the_same_way(): void
    {
        $split = function (string $id): array {
            $at = strrpos($id, '#');

            return $at === false ? [$id, 0] : [substr($id, 0, $at), (int) substr($id, $at + 1)];
        };

        foreach (['blazer', 'coat_trench', 'suit_jacket'] as $key) {
            $arms = [0 => [], 1 => []];

            foreach ($this->payload($key)['seams'] as $seam) {
                [$codeA, $armA] = $split((string) $seam['a']['piece']);
                [$codeB, $armB] = $split((string) $seam['b']['piece']);
                $onA = str_contains($codeA, 'sleeve');
                $onB = str_contains($codeB, 'sleeve');

                if (! $onA && ! $onB) {
                    continue;
                }

                // بازو را از خودِ قطعهٔ آستین می‌شناسیم، نه از قطعهٔ تنه: حلقهٔ
                // آستینِ راست به نیمهٔ چپِ تنه دوخته می‌شود و برعکس
                $arm = $onA ? $armA : $armB;

                // شناسهٔ درز بی شمارهٔ نمونه: کدها، طولِ دو سر، و جهتِ پیمایش
                $arms[$arm][] = sprintf(
                    '%s|%s|%.1f|%.1f|%d',
                    $codeA,
                    $codeB,
                    $seam['a']['length'],
                    $seam['b']['length'],
                    $seam['reverse'] ? 1 : 0,
                );
            }

            sort($arms[0]);
            sort($arms[1]);

            $this->assertNotEmpty($arms[0], "«{$key}» هیچ درزِ آستینی نداشت؛ آزمون چیزی را نسنجید.");
            $this->assertSame(
                $arms[0],
                $arms[1],
                "«{$key}»: دو بازو یک‌جور دوخته نشده‌اند — آستینِ آینه‌شده جابه‌جا یا وارونه وصل شده.",
            );
        }
    }

    /**
     * سرآستین باید به *همهٔ* پنل‌هایی برسد که حلقه رویشان است.
     *
     * ریشهٔ ناقرینگیِ کت رسمی همین بود. حلقه‌اش میان چهار پنل پخش است — مرکز
     * جلو ۱۱٫۲، پهلوی جلو ۱۱٫۳، پهلوی پشت ۱۰٫۶ و مرکز پشت ۱۰٫۷ سانتی‌متر — ولی
     * سازندهٔ رابطه‌ها فقط یکی از هر سو را می‌شناخت. آن وقت سرآستینِ ۴۶
     * سانتی‌متری روی ۲۱٫۹ سانتی‌متر چپانده می‌شد و باقی‌اش را splice() سرِخود
     * به هر کمانِ آزادی که می‌یافت می‌دوخت؛ روی هر بازو به یک قطعهٔ متفاوت.
     *
     * پس این‌جا شمرده می‌شود، نه فقط قرینگی: هر پنلِ رویه‌ای که لبهٔ «armhole»
     * دارد باید روی *هر دو* نمونه‌اش درزِ آستین بگیرد.
     */
    public function test_every_armhole_panel_of_the_suit_jacket_gets_the_sleeve(): void
    {
        $payload = $this->payload('suit_jacket');
        $panels = [];

        foreach ($payload['pieces'] as $piece) {
            $code = (string) ($piece['id'] ?? '');

            if (($piece['layer'] ?? 'outer') !== 'outer' || ($piece['role'] ?? '') === 'sleeve') {
                continue;
            }

            foreach ($piece['edges'] ?? [] as $edge) {
                if (($edge['tag'] ?? '') === 'armhole') {
                    $panels[$code] = 0;

                    break;
                }
            }
        }

        $this->assertNotEmpty($panels, 'هیچ پنلی با لبهٔ حلقه پیدا نشد؛ آزمون چیزی را نسنجید.');

        foreach ($payload['seams'] as $seam) {
            $sleeve = str_contains((string) $seam['a']['piece'], 'sleeve')
                || str_contains((string) $seam['b']['piece'], 'sleeve');

            if (! $sleeve) {
                continue;
            }

            foreach (['a', 'b'] as $end) {
                $code = (string) $seam[$end]['piece'];

                if (array_key_exists($code, $panels)) {
                    $panels[$code]++;
                }
            }
        }

        foreach ($panels as $code => $seams) {
            $this->assertGreaterThan(
                0,
                $seams,
                "حلقهٔ «{$code}» به آستین دوخته نشد؛ سرآستین جای دیگری چپانده می‌شود.",
            );
        }
    }
}

