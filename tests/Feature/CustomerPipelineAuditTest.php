<?php

namespace Tests\Feature;

use App\Models\GarmentType;
use App\Models\PatternTemplate;
use App\Services\Pattern\DxfExporter;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\PatternComposer;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SvgRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

/**
 * زنجیرهٔ کاملِ کارِ مشتری، روی بدن‌های بیرون از عادت.
 *
 * ممیزی کاتالوگ خروجیِ *درفت* را می‌سنجد و بس. ولی چیزی که به دست خیاط
 * می‌رسد از چند دستِ دیگر هم رد می‌شود: جای درز روی هر لبه گذاشته می‌شود،
 * نقشه کشیده می‌شود، و برای برشِ ماشینی DXF درمی‌آید. هر کدام از این‌ها
 * می‌تواند سرِ یک بدنِ نامعمول بشکند بی‌آنکه خودِ درفت خطایی داده باشد.
 *
 * پس این‌جا از هر دستهٔ کاتالوگ نمونه‌ای برداشته می‌شود و همان راهی که یک
 * سفارشِ واقعی می‌رود، تا آخر رفته می‌شود.
 */
class CustomerPipelineAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * بدن‌هایی که عمداً بیرون از جدولِ سایزند.
     *
     * @var array<string, array<string, float|int>>
     */
    protected const BODIES = [
        'خردسال ۹۲' => ['height' => 92, 'bust' => 52, 'waist' => 50, 'hip' => 54, 'shoulder_width' => 22, 'arm_length' => 30],
        'ریزنقش ۱۴۵' => ['height' => 145, 'bust' => 76, 'waist' => 60, 'hip' => 84, 'shoulder_width' => 33, 'arm_length' => 50],
        'خیلی‌درشت' => ['height' => 175, 'bust' => 150, 'waist' => 140, 'hip' => 155, 'shoulder_width' => 47, 'arm_length' => 62],
        'سیبی' => ['height' => 160, 'bust' => 100, 'waist' => 110, 'hip' => 102, 'shoulder_width' => 38, 'arm_length' => 56],
        'گلابی' => ['height' => 162, 'bust' => 82, 'waist' => 70, 'hip' => 116, 'shoulder_width' => 35, 'arm_length' => 57],
        'قدبلندِ درشت' => ['height' => 190, 'bust' => 122, 'waist' => 112, 'hip' => 124, 'shoulder_width' => 48, 'arm_length' => 70],
    ];

    /** یک از هر چهل مدلِ هر دسته، دست‌کم شش تا. */
    protected const PER_GROUP = 40;

    protected const MIN_PER_GROUP = 6;

    /**
     * نمونه‌ای پراکنده از کل کاتالوگ.
     *
     * @return array<int, string>
     */
    protected function sample(): array
    {
        $byGroup = [];

        foreach (array_keys(GeneratorRegistry::options()) as $key) {
            $byGroup[GeneratorRegistry::groupOf($key)][] = $key;
        }

        $sample = [];

        foreach ($byGroup as $keys) {
            $take = max(static::MIN_PER_GROUP, (int) ceil(count($keys) / static::PER_GROUP));
            $step = max(1, intdiv(count($keys), $take));

            for ($i = 0; $i < count($keys); $i += $step) {
                $sample[] = $keys[$i];
            }
        }

        return array_values(array_unique($sample));
    }

    public function test_a_pattern_survives_the_whole_customer_path_on_unusual_bodies(): void
    {
        $sample = $this->sample();
        $this->assertGreaterThan(200, count($sample), 'نمونه باید همهٔ دسته‌ها را پوشش بدهد.');

        $builder = app(PatternBuilder::class);
        $renderer = app(SvgRenderer::class);
        $seams = app(SeamAllowanceService::class);
        $type = GarmentType::factory()->create();

        $problems = [];
        $built = 0;

        foreach ($sample as $key) {
            $template = PatternTemplate::factory()
                ->generator($key)
                ->create(['garment_type_id' => $type->id]);

            foreach (static::BODIES as $bodyName => $raw) {
                $built++;
                $where = "{$key} روی «{$bodyName}»";

                try {
                    $pieces = $builder->buildFromTemplate($template, $raw);
                } catch (Throwable $error) {
                    $problems[] = "{$where}: ساخت نشد — ".get_class($error).': '.$error->getMessage();

                    continue;
                }

                if ($pieces === []) {
                    $problems[] = "{$where}: هیچ قطعه‌ای نداد.";

                    continue;
                }

                foreach ($pieces as $piece) {
                    $problems = array_merge($problems, $this->checkPiece($where, $piece, $seams));
                }

                $models = PatternComposer::toModels($pieces);

                try {
                    $svg = $renderer->renderPieces($models, ['width' => 900, 'labels' => true, 'seam_allowance' => true]);

                    if (! str_contains($svg, '<svg') || strlen($svg) < 200) {
                        $problems[] = "{$where}: نقشهٔ SVG تهی درآمد.";
                    }

                    if (preg_match('/\b(NAN|-?INF)\b/i', $svg)) {
                        $problems[] = "{$where}: عدد نامعتبر در نقشهٔ SVG.";
                    }
                } catch (Throwable $error) {
                    $problems[] = "{$where}: نقشه کشیده نشد — ".$error->getMessage();
                }
            }
        }

        $this->report($problems, "{$built} ساختِ کامل روی ".count(static::BODIES).' بدن');
        $this->assertGreaterThan(1000, $built);
    }

    /**
     * همان راه، ولی این بار الگو واقعاً ذخیره می‌شود و تا فایلِ برش می‌رود.
     *
     * ذخیره‌کردن گران است، پس نمونه کوچک‌تر است ولی راه بلندتر: قطعه‌ها در
     * پایگاه داده می‌نشینند، جای درز رویشان اعمال می‌شود، خطِ برشِ هر قطعه
     * درمی‌آید و برای دستگاهِ برش یک DXF ساخته می‌شود. اگر جایی از این زنجیره
     * سرِ یک بدنِ نامعمول بشکند، لباس بریده نمی‌شود.
     */
    public function test_a_saved_pattern_reaches_a_cutting_file_on_unusual_bodies(): void
    {
        $this->actingAsWorkshopUser();

        $builder = app(PatternBuilder::class);
        $seams = app(SeamAllowanceService::class);
        $dxf = app(DxfExporter::class);
        $type = GarmentType::factory()->create();

        // یکی از هر دسته بس است؛ اینجا زنجیره را می‌سنجیم نه کاتالوگ را
        $seen = [];
        $sample = [];

        foreach ($this->sample() as $key) {
            $group = GeneratorRegistry::groupOf($key);

            if (($seen[$group] ?? 0) < 2) {
                $seen[$group] = ($seen[$group] ?? 0) + 1;
                $sample[] = $key;
            }
        }

        $problems = [];
        $made = 0;

        foreach ($sample as $key) {
            $template = PatternTemplate::factory()
                ->generator($key)
                ->create(['garment_type_id' => $type->id]);

            foreach (static::BODIES as $bodyName => $raw) {
                $made++;
                $where = "{$key} روی «{$bodyName}»";

                try {
                    $pattern = $builder->createPattern($template, ['name' => $key], ['measurements' => $raw]);
                    $seams->apply($pattern);
                    $pattern->refresh()->load('pieces');
                } catch (Throwable $error) {
                    $problems[] = "{$where}: الگو ذخیره نشد — ".get_class($error).': '.$error->getMessage();

                    continue;
                }

                if ($pattern->pieces->isEmpty()) {
                    $problems[] = "{$where}: الگوی ذخیره‌شده هیچ قطعه‌ای ندارد.";

                    continue;
                }

                foreach ($pattern->pieces as $piece) {
                    try {
                        $line = $seams->cuttingLine($piece);

                        if (count($line) < 3) {
                            $problems[] = "{$where} / {$piece->code}: خط برش درنیامد.";
                        }
                    } catch (Throwable $error) {
                        $problems[] = "{$where} / {$piece->code}: خط برش شکست — ".$error->getMessage();
                    }
                }

                try {
                    $file = $dxf->export($pattern);

                    if (! str_contains($file, 'SECTION') || ! str_contains($file, 'EOF')) {
                        $problems[] = "{$where}: فایل DXF ناقص است.";
                    }

                    if (preg_match('/\b(NAN|-?INF)\b/i', $file)) {
                        $problems[] = "{$where}: عدد نامعتبر در فایل DXF.";
                    }
                } catch (Throwable $error) {
                    $problems[] = "{$where}: DXF ساخته نشد — ".$error->getMessage();
                }
            }
        }

        $this->report($problems, "{$made} الگوی ذخیره‌شده تا فایل برش");
        $this->assertGreaterThan(100, $made);
    }

    /**
     * بررسی یک قطعه: مسیر، جای درز، و خطِ برش.
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, string>
     */
    protected function checkPiece(string $where, array $piece, SeamAllowanceService $seams): array
    {
        $problems = [];
        $code = (string) ($piece['code'] ?? '?');
        $outline = $piece['outline'] ?? [];

        if (count($outline) < 3) {
            return ["{$where} / {$code}: مسیر کمتر از سه نقطه دارد."];
        }

        foreach ($outline as $point) {
            $x = (float) ($point[0] ?? $point['x'] ?? NAN);
            $y = (float) ($point[1] ?? $point['y'] ?? NAN);

            if (! is_finite($x) || ! is_finite($y)) {
                $problems[] = "{$where} / {$code}: نقطه‌ای با مختصات نامعتبر.";

                break;
            }
        }

        // جای درز باید مسیر را *بزرگ‌تر* کند، نه بشکند: قطعه‌ای که با یک
        // سانتی‌متر جای درز از هم می‌پاشد، سرِ میز برش به درد نمی‌خورد
        try {
            $grown = $seams->offsetOutline($outline, 1.0);

            if (count($grown) < 3) {
                $problems[] = "{$where} / {$code}: جای درز مسیر را از بین برد.";
            }
        } catch (Throwable $error) {
            $problems[] = "{$where} / {$code}: جای درز شکست — ".$error->getMessage();
        }

        return $problems;
    }

    /** @param  array<int, string>  $problems */
    protected function report(array $problems, string $headline): void
    {
        if ($problems === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $shown = array_slice($problems, 0, 40);
        $more = count($problems) - count($shown);

        $this->fail($headline.' — '.count($problems)." مشکل:\n  - "
            .implode("\n  - ", $shown)
            .($more > 0 ? "\n  … و {$more} مورد دیگر" : ''));
    }
}
