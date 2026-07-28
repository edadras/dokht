<?php

namespace Tests\Unit\Style;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleModifier;
use App\Services\Pattern\Style\StyleRegistry;
use App\Services\Pattern\Transform\FullnessRecorder;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * آزمون کاتالوگ یقه.
 *
 * محک همه این آزمون‌ها یک چیز است: یقه باید به اندازه خط یقه‌ای دربیاید که واقعاً
 * روی قطعه‌ها اندازه گرفته می‌شود، نه عددی که در جدول نوشته شده. پس هر یقه روی
 * بلوک بالاتنه و روی چند خط یقه آماده درفت می‌شود و لبه‌اش با متر پیاده می‌شود.
 */
class CollarTest extends TestCase
{
    /** برچسب‌های مجاز لبه در همه‌جای سامانه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'default'];

    /** یقه‌هایی که بدون چاک جلو معنا ندارند. */
    protected const NEEDS_OPENING = ['collar_notched', 'collar_peak', 'collar_shawl', 'collar_zip_stand', 'collar_hood'];

    /* ---------------------------------------------------------------------
     |  ابزار
     * ------------------------------------------------------------------- */

    protected function measurements(string $size = '40'): array
    {
        return Measurements::fromSize($size);
    }

    /** @return array<int, array<string, mixed>> */
    protected function block(string $size = '40'): array
    {
        return GeneratorRegistry::make('bodice_block')->generate(
            $this->measurements($size),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
            [],
        );
    }

    /** همان بلوک، با چاک و دکمه جلو (با سبک بست واقعی، نه دست‌کاری دستی). */
    protected function openedBlock(string $size = '40'): array
    {
        return StyleRegistry::make('closure_single_breasted')->apply($this->block($size), [
            'measurements' => $this->measurements($size),
            'params' => [],
        ])['pieces'];
    }

    /** بلوک با یک خط یقه آماده روی آن. */
    protected function withNeckline(string $key, bool $opened = false, string $size = '40'): array
    {
        $pieces = $opened ? $this->openedBlock($size) : $this->block($size);

        $pieces = StyleRegistry::make($key)->apply($pieces, [
            'measurements' => $this->measurements($size),
            'params' => ['finish' => 'none'],
        ])['pieces'];

        return array_values($pieces);
    }

    /** یک قطعه دامن، برای آزمون نپذیرفتن. */
    protected function skirt(): array
    {
        return GeneratorRegistry::make('skirt_a_line')->generate(
            $this->measurements(),
            ['waist' => 2, 'hip' => 4],
            [],
        );
    }

    protected function collar(string $key): StyleModifier
    {
        return StyleRegistry::make($key);
    }

    /** @return array<string, mixed> */
    protected function apply(string $key, array $pieces, array $params = [], array $context = []): array
    {
        $style = $this->collar($key);
        $context = array_merge(['measurements' => $this->measurements(), 'params' => $params], $context);

        $this->assertTrue($style->supports($pieces, $context), "سبک «{$key}» این قطعه‌ها را نپذیرفت.");

        return $style->apply($pieces, $context);
    }

    protected function pieceWithCode(array $result, string $code): array
    {
        foreach ($result['pieces'] as $piece) {
            if ($piece['code'] === $code) {
                return $piece;
            }
        }

        $this->fail("قطعه «{$code}» ساخته نشد.");
    }

    /** طول دوخته‌شده یک لبه. */
    protected function seam(array $piece, string $tag): float
    {
        return PieceOps::seamLength($piece, PieceOps::edges($piece, $tag));
    }

    /** دور کامل خط یقه، همان‌طور که یقه اندازه می‌گیرد. */
    protected function neckline(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (in_array($part, ['collar', 'facing', 'interfacing', 'lapel', 'hood', 'binding'], true)) {
                continue;
            }

            if (Geometry::edgesWithTag($piece, 'neck') === []) {
                continue;
            }

            $copies = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
            $total += $this->seam($piece, 'neck') * $copies;
        }

        return round($total, 3);
    }

    /** هر قطعه باید بریدنی باشد: مسیر بسته و سالم، با برچسب کامل لبه‌ها. */
    protected function assertPieceIsCuttable(array $piece, string $where): void
    {
        $this->assertSame([], Geometry::validatePiece($piece), $where.' — قطعه «'.$piece['code'].'» سالم نیست.');

        $edges = $piece['meta']['edges'] ?? null;

        $this->assertIsArray($edges, $where.' — قطعه «'.$piece['code'].'» برچسب لبه ندارد.');
        $this->assertCount(
            count($piece['outline']),
            $edges,
            $where.' — شمار برچسب لبه‌های «'.$piece['code'].'» با شمار لبه‌های مسیر یکی نیست.',
        );

        foreach ($edges as $tag) {
            $this->assertContains($tag, static::EDGE_TAGS, $where.' — برچسب لبه ناشناخته روی «'.$piece['code'].'».');
        }

        $this->assertNotEmpty($piece['grainline'] ?? null, $where.' — قطعه «'.$piece['code'].'» راستای پارچه ندارد.');
    }

    /* ---------------------------------------------------------------------
     |  فهرست و ثبت
     * ------------------------------------------------------------------- */

    public function test_the_collar_catalogue_is_complete_and_registered_under_the_collar_group(): void
    {
        $styles = StyleRegistry::group('collar');

        $expected = [
            'collar_band', 'collar_peter_pan', 'collar_shirt', 'collar_convertible',
            'collar_notched', 'collar_shawl', 'collar_peak', 'collar_sailor',
            'collar_ruffle', 'collar_tie', 'collar_funnel', 'collar_rib',
            'collar_zip_stand', 'collar_hood',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $styles, "یقه «{$key}» در فهرست سبک‌ها نیست.");
        }

        foreach ($styles as $key => $style) {
            $this->assertSame('collar', $style::group());
            $this->assertNotSame('', trim($style->label()), "یقه «{$key}» نام فارسی ندارد.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $style->label(), "نام «{$key}» فارسی نیست.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $style->description(), "توضیح «{$key}» فارسی نیست.");

            $schema = $style->paramsSchema();

            $this->assertNotSame([], $schema, "یقه «{$key}» پارامتری ندارد.");
            $this->assertLessThanOrEqual(14, count($schema), "پارامترهای «{$key}» زیادی است؛ فرم باید کوتاه بماند.");

            foreach ($schema as $field => $spec) {
                $this->assertArrayHasKey('label', $spec, "پارامتر «{$field}» در «{$key}» برچسب ندارد.");
                $this->assertArrayHasKey('default', $spec, "پارامتر «{$field}» در «{$key}» پیش‌فرض ندارد.");
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', (string) $spec['label']);
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  یقه پیراهنی دوتکه — سنجه اصلی درستی
     * ------------------------------------------------------------------- */

    public function test_two_piece_shirt_collar_neck_edge_matches_the_drafted_neckline_at_every_size(): void
    {
        foreach (['34', '40', '48'] as $size) {
            $pieces = $this->openedBlock($size);
            $target = $this->neckline($pieces) / 2;   // یقه روی تای مرکز پشت، پس نیم دور

            $result = $this->apply('collar_shirt', $pieces, [], ['measurements' => $this->measurements($size)]);
            $stand = $this->pieceWithCode($result, 'collar-stand');
            $measured = $this->seam($stand, 'neck');

            $this->assertEqualsWithDelta(
                $target,
                $measured,
                0.1,
                "سایز {$size}: لبه یقه پایه {$measured} درآمد ولی خط یقه {$target} است.",
            );

            $walk = PieceOps::walk($stand, 'neck', $stand, 'neck');
            $this->assertTrue($walk['matched'], 'پیاده‌کردن لبه یقه روی خودش باید بخواند.');
        }
    }

    public function test_shirt_collar_stand_top_is_shorter_than_the_fall_outer_edge_so_it_rolls(): void
    {
        $result = $this->apply('collar_shirt', $this->openedBlock());
        $stand = $this->pieceWithCode($result, 'collar-stand');
        $fall = $this->pieceWithCode($result, 'collar-fall');

        $neck = $this->seam($stand, 'neck');
        $top = $this->seam($stand, 'hem');
        $attach = $this->seam($fall, 'neck');
        $outer = $this->seam($fall, 'hem');

        // پایه به بالا تنگ می‌شود
        $this->assertLessThan($neck, $top, 'لبه بالای پایه باید از لبه یقه کوتاه‌تر باشد وگرنه پایه نمی‌ایستد.');

        // رویه دقیقاً روی لبه بالای پایه می‌نشیند
        $this->assertEqualsWithDelta($top, $attach, 0.1, 'لبه چسبیده رویه باید هم‌اندازه لبه بالای پایه باشد.');

        // و لبه بیرونی رویه بلندتر است؛ همین یقه را می‌خواباند
        $this->assertGreaterThan($top, $outer, 'لبه بیرونی رویه باید از لبه بالای پایه بلندتر باشد تا یقه برگردد.');
        $this->assertTrue($result['meta']['collar']['rolls']);

        $roll = PieceOps::walk($stand, 'hem', $fall, 'neck');
        $this->assertLessThanOrEqual(0.1, abs((float) $roll['difference']), 'درز خط خواب باید بدون کشیدن دوخته شود.');

        // خط خواب روی هر دو قطعه علامت خورده
        $this->assertContains('roll_line', array_column($stand['markers'], 'key'));
        $this->assertContains('roll_line', array_column($fall['markers'], 'key'));
    }

    /* ---------------------------------------------------------------------
     |  یقه شال
     * ------------------------------------------------------------------- */

    public function test_shawl_collar_marks_the_roll_line_and_its_outer_edge_is_longer_than_its_neck_edge(): void
    {
        $result = $this->apply('collar_shawl', $this->openedBlock());
        $collar = $this->pieceWithCode($result, 'collar-shawl');

        $neck = $this->seam($collar, 'neck');
        $outer = $this->seam($collar, 'hem');

        $this->assertGreaterThan($neck + 1.0, $outer, 'لبه بیرونی یقه شال باید از لبه یقه‌اش بلندتر باشد.');

        $this->assertContains('roll_line', array_column($collar['markers'], 'key'), 'خط خواب یقه شال علامت نخورده.');
        $this->assertGreaterThan(0, $collar['meta']['roll_line'] ?? 0);

        // راستای پارچه یقه شال روی خط خواب می‌افتد، نه موازی مرکز پشت
        $this->assertTrue((bool) ($collar['meta']['grain_on_roll_line'] ?? false));
        $this->assertMatchesRegularExpression('/خواب/u', (string) ($collar['grainline']['label'] ?? ''));

        // برگردان روی خود تنه بریده شده و خط خواب و نقطه شکست روی تنه علامت خورده‌اند
        $front = $this->pieceWithCode($result, 'bodice-front');

        $this->assertSame('collar_shawl', $front['meta']['lapel'] ?? null);
        $this->assertContains('roll_line', array_column($front['markers'], 'key'));
        $this->assertContains('break_point', array_column($front['notches'], 'pair'));
    }

    /* ---------------------------------------------------------------------
     |  یقه چین‌دار
     * ------------------------------------------------------------------- */

    public function test_ruffle_collar_records_its_gathers_with_the_fullness_recorder(): void
    {
        $pieces = $this->block();
        $neckline = $this->neckline($pieces);
        $result = $this->apply('collar_ruffle', $pieces, ['style' => 'gathered', 'fullness' => 2.0]);

        $ruffle = $this->pieceWithCode($result, 'collar-ruffle');
        $edge = PieceOps::edges($ruffle, 'neck')[0];

        $raw = PieceOps::edgeLength($ruffle, 'neck');
        $seam = $this->seam($ruffle, 'neck');

        // پارچه دو برابر بریده می‌شود ...
        $this->assertEqualsWithDelta($neckline * 2, $raw, 0.2, 'نوار باید دو برابر خط یقه بریده شود.');

        // ... ولی روی خط یقه به اندازه خط یقه می‌نشیند، چون چین ثبت شده است
        $this->assertEqualsWithDelta($neckline, $seam, 0.1, 'طول دوخته‌شده نوار باید برابر خط یقه باشد.');

        $recorded = FullnessRecorder::all($ruffle);

        $this->assertNotEmpty($recorded, 'چین یقه با FullnessRecorder ثبت نشده.');
        $this->assertSame('gathers', $recorded[0]['kind']);
        $this->assertSame($edge, $recorded[0]['edge'], 'چین باید روی لبه یقه ثبت شود.');
        $this->assertEqualsWithDelta($neckline, FullnessRecorder::amountOn($ruffle, $edge, 'gathers'), 0.2);
        $this->assertEqualsWithDelta($raw - $seam, FullnessRecorder::consumedOn($ruffle, $edge), 0.05);

        // یقه دایره‌ای همان کار را بدون چین می‌کند: لبه بیرونی بلندتر است
        $circular = $this->apply('collar_ruffle', $pieces, ['style' => 'circular', 'fullness' => 2.5]);
        $flounce = $this->pieceWithCode($circular, 'collar-ruffle-circular');

        $this->assertSame([], FullnessRecorder::all($flounce), 'یقه دایره‌ای چین ندارد؛ موجش از برش می‌آید.');
        $this->assertGreaterThan($this->seam($flounce, 'neck'), $this->seam($flounce, 'hem'));

        $sections = (int) $circular['meta']['collar']['sections'];
        $this->assertEqualsWithDelta(
            $neckline,
            $this->seam($flounce, 'neck') * $sections,
            0.3,
            'مجموع تکه‌های حلقه باید دور کامل خط یقه را بپوشاند.',
        );
    }

    /* ---------------------------------------------------------------------
     |  کلاه
     * ------------------------------------------------------------------- */

    public function test_hood_neck_edge_matches_the_neckline_and_reports_its_front_opening(): void
    {
        $pieces = $this->openedBlock();
        $half = $this->neckline($pieces) / 2;

        $result = $this->apply('collar_hood', $pieces, ['panels' => '2']);
        $panel = $this->pieceWithCode($result, 'hood-side');

        $this->assertEqualsWithDelta($half, $this->seam($panel, 'neck'), 0.1, 'لبه گردن هر نیمه کلاه باید نیم دور خط یقه باشد.');

        // دهانه صورت: عددی که گزارش می‌شود باید همان لبه اندازه‌گیری‌شده باشد
        $opening = $this->seam($panel, 'hem');

        $this->assertEqualsWithDelta($opening, (float) $panel['meta']['front_opening'], 0.1);
        $this->assertEqualsWithDelta($opening * 2, (float) $result['meta']['collar']['face_opening_total'], 0.1);
        $this->assertGreaterThan($this->seam($panel, 'neck'), $opening, 'دهانه صورت باید از لبه گردن بازتر باشد.');

        // کلاه سه‌تکه: ترک میانی دقیقاً هم‌اندازه درز نیمه‌هاست
        $three = $this->apply('collar_hood', $pieces, ['panels' => '3', 'gusset' => 9]);
        $side = $this->pieceWithCode($three, 'hood-side');
        $gusset = $this->pieceWithCode($three, 'hood-gusset');

        $walk = PieceOps::walk($side, 'default', $gusset, 'side');

        $this->assertLessThanOrEqual(0.1, abs((float) $walk['difference']), 'ترک میانی با درز نیمه‌ها نمی‌خواند.');
        $this->assertEqualsWithDelta(
            $half,
            $this->seam($side, 'neck') + ($this->seam($gusset, 'neck') / 2),
            0.1,
            'لبه گردن نیمه‌ها به‌علاوه نیمِ سر ترک باید نیم دور خط یقه شود.',
        );

        // بندکش: دو سوراخ مته روی هر نیمه
        $withCord = $this->apply('collar_hood', $pieces, ['drawstring' => true]);
        $this->assertCount(2, $this->pieceWithCode($withCord, 'hood-side')['drills']);
    }

    /* ---------------------------------------------------------------------
     |  نپذیرفتن‌های صادقانه
     * ------------------------------------------------------------------- */

    public function test_supports_refuses_a_hood_on_a_skirt_in_persian(): void
    {
        $reason = $this->collar('collar_hood')->supports($this->skirt(), []);

        $this->assertIsString($reason);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $reason);
        $this->assertStringContainsString('خط یقه', $reason);
        $this->assertStringContainsString('دامن', $reason);
    }

    public function test_supports_refuses_every_collar_when_the_pieces_have_no_neck_edge(): void
    {
        $skirt = $this->skirt();

        foreach (StyleRegistry::group('collar') as $key => $style) {
            $reason = $style->supports($skirt, []);

            $this->assertIsString($reason, "یقه «{$key}» روی دامن پذیرفته شد!");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $reason, "دلیل «{$key}» فارسی نیست.");
            $this->assertStringContainsString('خط یقه', $reason);
        }
    }

    public function test_supports_refuses_lapel_and_shawl_collars_on_a_garment_with_no_front_opening(): void
    {
        $closed = $this->block();

        foreach (['collar_notched', 'collar_peak', 'collar_shawl'] as $key) {
            $reason = $this->collar($key)->supports($closed, []);

            $this->assertIsString($reason, "یقه «{$key}» روی لباس بدون چاک جلو پذیرفته شد!");
            $this->assertStringContainsString('چاک جلو', $reason);
            $this->assertStringContainsString('بست', $reason);

            // با بست جلو، همان یقه پذیرفته می‌شود
            $this->assertTrue($this->collar($key)->supports($this->openedBlock(), []));
        }
    }

    public function test_supports_refuses_a_zip_stand_collar_without_a_front_opening(): void
    {
        $reason = $this->collar('collar_zip_stand')->supports($this->block(), []);

        $this->assertIsString($reason);
        $this->assertStringContainsString('زیپ', $reason);
        $this->assertTrue($this->collar('collar_zip_stand')->supports($this->openedBlock(), []));
    }

    public function test_supports_refuses_a_hood_when_the_head_cannot_pass_the_closed_neckline(): void
    {
        $reason = $this->collar('collar_hood')->supports($this->block(), ['measurements' => $this->measurements()]);

        $this->assertIsString($reason, 'کلاه روی یقه بسته و تنگ نباید پذیرفته شود.');
        $this->assertStringContainsString('دور سر', $reason);

        // روی خط یقه باز (قایقی) سر رد می‌شود و کلاه پذیرفته می‌شود
        $wide = $this->withNeckline('neck_boat');
        $this->assertTrue($this->collar('collar_hood')->supports($wide, ['measurements' => $this->measurements()]));
    }

    public function test_supports_refuses_a_rib_band_on_a_woven_fabric(): void
    {
        $reason = $this->collar('collar_rib')->supports($this->block(), [
            'fabric' => ['name' => 'کتان', 'stretch' => 0, 'knit' => false],
        ]);

        $this->assertIsString($reason);
        $this->assertStringContainsString('کشش', $reason);
        $this->assertTrue($this->collar('collar_rib')->supports($this->block(), [
            'fabric' => ['name' => 'کشباف ۱×۱', 'stretch' => 40, 'knit' => true],
        ]));
    }

    /* ---------------------------------------------------------------------
     |  رفت و برگشت از فهرست سبک‌ها
     * ------------------------------------------------------------------- */

    public function test_every_collar_round_trips_through_the_registry_over_block_and_necklines(): void
    {
        $beds = [
            'بلوک بالاتنه' => $this->block(),
            'بلوک جلوباز' => $this->openedBlock(),
            'خط یقه هفت' => $this->withNeckline('neck_v', true),
            'خط یقه گرد' => $this->withNeckline('neck_round', true),
            'خط یقه چهارگوش' => $this->withNeckline('neck_square', true),
        ];

        $covered = [];

        foreach (StyleRegistry::group('collar') as $key => $style) {
            foreach ($beds as $where => $pieces) {
                $context = ['measurements' => $this->measurements(), 'params' => []];
                $support = $style->supports($pieces, $context);

                if ($support !== true) {
                    // فقط یقه‌هایی که چاک جلو می‌خواهند حق دارند روی بلوک بسته رد کنند
                    $this->assertContains($key, static::NEEDS_OPENING, "یقه «{$key}» روی {$where} بی‌دلیل رد کرد: {$support}");

                    continue;
                }

                $result = $style->apply($pieces, $context);
                $where = "{$key} روی {$where}";

                $this->assertNotEmpty($result['notes'], "{$where} — یادداشتی برنگرداند.");
                $this->assertGreaterThan(count($pieces), count($result['pieces']), "{$where} — قطعه‌ای نساخت.");

                $made = 0;

                foreach ($result['pieces'] as $piece) {
                    $this->assertPieceIsCuttable($piece, $where);

                    if (($piece['meta']['collar_style'] ?? null) === $key) {
                        $made++;
                    }
                }

                $this->assertGreaterThan(0, $made, "{$where} — هیچ قطعه‌ای به نام همین یقه ساخته نشد.");

                $meta = $result['meta']['collar'];

                $this->assertSame($key, $meta['style']);
                $this->assertLessThanOrEqual(
                    0.1,
                    abs((float) ($meta['difference'] ?? 0)),
                    "{$where} — اختلاف لبه یقه با خط یقه بیش از یک‌دهم سانتی‌متر است.",
                );

                $covered[$key] = true;
            }
        }

        $this->assertCount(count(StyleRegistry::group('collar')), $covered, 'همه یقه‌ها آزموده نشدند.');
    }

    public function test_every_collar_reports_the_finished_neckline_and_the_ease_it_used(): void
    {
        foreach (StyleRegistry::group('collar') as $key => $style) {
            $pieces = in_array($key, static::NEEDS_OPENING, true) ? $this->openedBlock() : $this->block();
            $context = ['measurements' => $this->measurements(), 'params' => []];

            $this->assertTrue($style->supports($pieces, $context));

            $notes = $style->apply($pieces, $context)['notes'];
            $text = implode(' ', $notes);

            $this->assertStringContainsString('خط یقه', $text, "یادداشت «{$key}» طول خط یقه را نگفته.");
            $this->assertStringContainsString('آزادی', $text, "یادداشت «{$key}» آزادی به‌کاررفته را نگفته.");
            $this->assertStringContainsString('سانتی‌متر', $text);

            foreach ($notes as $note) {
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', $note, "یادداشت «{$key}» فارسی نیست.");
            }
        }
    }

    /* ---------------------------------------------------------------------
     |  رفتار هر یقه
     * ------------------------------------------------------------------- */

    public function test_stand_collars_have_a_top_edge_shorter_than_their_neck_edge(): void
    {
        foreach (['collar_band' => 'collar-band', 'collar_zip_stand' => 'collar-zip-stand'] as $key => $code) {
            $result = $this->apply($key, $this->openedBlock());
            $piece = $this->pieceWithCode($result, $code);

            $this->assertLessThan(
                $this->seam($piece, 'neck'),
                $this->seam($piece, 'hem'),
                "لبه بالای «{$key}» باید کوتاه‌تر از لبه یقه باشد تا یقه به گردن بچسبد.",
            );
        }

        // یقه قیفی برعکس است: به بالا باز می‌شود
        $funnel = $this->pieceWithCode($this->apply('collar_funnel', $this->block(), ['flare' => 4]), 'collar-funnel');

        $this->assertGreaterThan($this->seam($funnel, 'neck'), $this->seam($funnel, 'hem'));
    }

    public function test_peter_pan_collar_lies_flatter_as_its_stand_shrinks(): void
    {
        $flat = $this->apply('collar_peter_pan', $this->block(), ['stand' => 0, 'width' => 6]);
        $standing = $this->apply('collar_peter_pan', $this->block(), ['stand' => 2.5, 'width' => 6]);

        $flatSpread = (float) $flat['meta']['collar']['spread'];
        $standingSpread = (float) $standing['meta']['collar']['spread'];

        $this->assertGreaterThan($standingSpread, $flatSpread, 'هرچه پایه کوتاه‌تر، لبه بیرونی باید بلندتر باشد.');
        $this->assertGreaterThan(0, $standingSpread);

        // یقه تخت روی حلقه گردن می‌خوابد: لبه بیرونی حدود π برابر پهنا بلندتر می‌شود
        $this->assertEqualsWithDelta(M_PI * 6, $flatSpread, 1.5, 'یقه بدون پایه باید کاملاً تخت درفت شود.');

        // سر نوک‌تیز هم درست درمی‌آید
        $pointed = $this->apply('collar_peter_pan', $this->block(), ['shape' => 'pointed', 'point_length' => 3]);
        $piece = $this->pieceWithCode($pointed, 'collar-peter-pan');

        $this->assertPieceIsCuttable($piece, 'یقه پیتر‌پن نوک‌تیز');
        $this->assertGreaterThan(
            Geometry::area($this->pieceWithCode($flat, 'collar-peter-pan')['outline']) * 0.5,
            Geometry::area($piece['outline']),
        );
    }

    public function test_rib_band_is_cut_shorter_than_the_neckline_so_it_pulls_the_edge_flat(): void
    {
        $pieces = $this->block();
        $neckline = $this->neckline($pieces);
        $result = $this->apply('collar_rib', $pieces, ['stretch' => 0.85, 'height' => 2]);
        $band = $this->pieceWithCode($result, 'collar-rib');

        $this->assertEqualsWithDelta($neckline * 0.85, $this->seam($band, 'neck'), 0.1);
        $this->assertLessThan(0, (float) $result['meta']['collar']['ease'], 'نوار کشی باید آزادی منفی داشته باشد.');
        $this->assertSame('length', $band['meta']['stretch_direction']);
        $this->assertStringContainsString('کشش', (string) ($band['grainline']['label'] ?? ''));
    }

    public function test_tie_collar_middle_matches_the_neckline_and_the_tails_stay_free(): void
    {
        $pieces = $this->block();
        $result = $this->apply('collar_tie', $pieces, ['tie_length' => 45, 'ease' => 0]);
        $tie = $this->pieceWithCode($result, 'collar-tie');

        $this->assertEqualsWithDelta($this->neckline($pieces), $this->seam($tie, 'neck'), 0.1);

        // دنباله‌ها روی خط یقه دوخته نمی‌شوند، پس برچسب یقه نمی‌گیرند
        $this->assertCount(1, PieceOps::edges($tie, 'neck'));
        $this->assertEqualsWithDelta(
            $this->seam($tie, 'neck') + 90,
            Geometry::width($tie['outline']),
            0.5,
            'نوار باید به اندازه میانه به‌علاوه دو دنباله بلند باشد.',
        );
    }

    public function test_notched_lapel_cuts_the_lapel_from_the_front_and_shortens_the_neckline(): void
    {
        $pieces = $this->openedBlock();
        $before = $this->neckline($pieces);
        $result = $this->apply('collar_notched', $pieces);
        $after = $this->neckline($result['pieces']);

        $this->assertLessThan($before, $after, 'برگردان بخشی از خط یقه را برمی‌دارد، پس خط یقه باید کوتاه‌تر شود.');

        $front = $this->pieceWithCode($result, 'bodice-front');

        $this->assertSame('collar_notched', $front['meta']['lapel'] ?? null);
        $this->assertGreaterThan(0, $front['meta']['gorge_length'] ?? 0);
        $this->assertContains('gorge', array_column($front['notches'], 'pair'));
        $this->assertPieceIsCuttable($front, 'تنه جلو با برگردان');

        // سر یقه به اندازه خط گلوی تنه بریده می‌شود
        $collar = $this->pieceWithCode($result, 'collar-upper');

        $this->assertEqualsWithDelta(
            (float) $front['meta']['gorge_length'],
            (float) $collar['meta']['gorge_length'],
            0.1,
            'خط گلوی تنه و سر یقه باید هم‌اندازه باشند وگرنه خرک بالا و پایین می‌افتد.',
        );

        // یقه به اندازه خط یقه باقی‌مانده درفت شده
        $this->assertEqualsWithDelta($after / 2, $this->seam($collar, 'neck'), 0.1);

        // سجاف جلو و زیره مورب هم ساخته شده‌اند
        $this->pieceWithCode($result, 'bodice-front-facing');
        $this->assertTrue((bool) $this->pieceWithCode($result, 'collar-under')['meta']['bias']);
    }

    public function test_sailor_collar_outer_edge_is_much_longer_than_its_neck_edge(): void
    {
        $result = $this->apply('collar_sailor', $this->withNeckline('neck_v'), ['back_depth' => 17]);
        $collar = $this->pieceWithCode($result, 'collar-sailor');

        $this->assertGreaterThan($this->seam($collar, 'neck') * 1.4, $this->seam($collar, 'hem'));
        $this->assertTrue((bool) $collar['on_fold'], 'یقه ملوانی روی تای مرکز پشت بریده می‌شود.');
    }

    /* ---------------------------------------------------------------------
     |  نشانه، لایه چسب و راستای پارچه
     * ------------------------------------------------------------------- */

    public function test_half_collars_carry_shoulder_and_centre_front_notches_in_the_right_places(): void
    {
        $pieces = $this->openedBlock();
        $back = 0.0;

        foreach ($pieces as $piece) {
            if (($piece['meta']['side'] ?? '') === 'back' && Geometry::edgesWithTag($piece, 'neck') !== []) {
                $back = $this->seam($piece, 'neck');
            }
        }

        foreach (['collar_band' => 'collar-band', 'collar_shirt' => 'collar-stand', 'collar_peter_pan' => 'collar-peter-pan'] as $key => $code) {
            $piece = $this->pieceWithCode($this->apply($key, $pieces), $code);
            $pairs = array_column($piece['notches'], 'pair');

            $this->assertContains('shoulder', $pairs, "«{$key}» نشانه سرشانه ندارد.");
            $this->assertContains('center_front', $pairs, "«{$key}» نشانه مرکز جلو ندارد.");
            $this->assertContains('cb', array_column($piece['markers'], 'key'), "«{$key}» خط مرکز پشت را علامت نزده.");

            // نشانه سرشانه باید دقیقاً به اندازه یقه پشت از مرکز پشت فاصله داشته باشد
            foreach ($piece['notches'] as $notch) {
                if (($notch['pair'] ?? null) !== 'shoulder') {
                    continue;
                }

                $walked = 0.0;
                $edges = PieceOps::edges($piece, 'neck');

                foreach ($edges as $edge) {
                    if ($edge < $notch['edge']) {
                        $walked += Geometry::edgeLength($piece['outline'], $edge);
                    }
                }

                $walked += Geometry::edgeLength($piece['outline'], (int) $notch['edge'])
                    * Geometry::edgeParameterOf($piece['outline'], (int) $notch['edge'], $notch);

                $this->assertEqualsWithDelta($back, $walked, 0.3, "جای نشانه سرشانه روی «{$key}» درست نیست.");
            }
        }
    }

    public function test_collars_that_a_tailor_interfaces_come_with_an_interfacing_layer(): void
    {
        $interfaced = ['collar_band', 'collar_shirt', 'collar_convertible', 'collar_peter_pan', 'collar_funnel', 'collar_zip_stand'];

        foreach ($interfaced as $key) {
            $result = $this->apply($key, $this->openedBlock());
            $layers = [];

            foreach ($result['pieces'] as $piece) {
                if (($piece['layer'] ?? 'outer') === 'interfacing') {
                    $layers[] = $piece;
                }
            }

            $this->assertNotEmpty($layers, "«{$key}» لایه چسب ندارد.");

            foreach ($layers as $layer) {
                $this->assertSame('interfacing', $layer['meta']['part']);
                $this->assertNotEmpty($layer['meta']['interfacing_for'] ?? null);
                $this->assertPieceIsCuttable($layer, "لایه چسب {$key}");
            }
        }

        // نوار کشی و پاپیون و ملوانی لایه چسب نمی‌خواهند
        foreach (['collar_rib', 'collar_tie', 'collar_sailor'] as $key) {
            foreach ($this->apply($key, $this->block())['pieces'] as $piece) {
                $this->assertNotSame('interfacing', $piece['layer'] ?? 'outer', "«{$key}» نباید لایه چسب بدهد.");
            }
        }
    }

    public function test_band_collars_sit_on_the_straight_grain_along_the_centre_back(): void
    {
        foreach (['collar_band' => 'collar-band', 'collar_shirt' => 'collar-stand'] as $key => $code) {
            $piece = $this->pieceWithCode($this->apply($key, $this->openedBlock()), $code);
            $grain = $piece['grainline'];
            $centre = null;

            foreach ($piece['markers'] as $marker) {
                if (($marker['key'] ?? '') === 'cb') {
                    $centre = $marker;
                }
            }

            $this->assertNotNull($centre);

            $grainAngle = atan2($grain['to']['y'] - $grain['from']['y'], $grain['to']['x'] - $grain['from']['x']);
            $centreAngle = atan2($centre['to']['y'] - $centre['from']['y'], $centre['to']['x'] - $centre['from']['x']);
            $difference = abs(rad2deg($grainAngle - $centreAngle));

            $this->assertLessThan(3.0, min($difference, abs($difference - 180)), "راستای پارچه «{$key}» موازی مرکز پشت نیست.");
        }
    }

    public function test_the_collar_extension_follows_the_button_stand_of_the_garment(): void
    {
        $pieces = $this->openedBlock();
        $stand = 0.0;

        foreach ($pieces as $piece) {
            $stand = max($stand, (float) ($piece['meta']['button_stand'] ?? 0));
        }

        $this->assertGreaterThan(0, $stand);

        $result = $this->apply('collar_band', $pieces, ['extension' => 4]);

        $this->assertEqualsWithDelta($stand, (float) $result['meta']['collar']['front_extension'], 0.05);
    }
}
