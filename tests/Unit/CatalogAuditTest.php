<?php

namespace Tests\Unit;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Measurements;
use Tests\TestCase;
use Throwable;

/**
 * بازرسی سراسری کاتالوگ الگو.
 *
 * این آزمون هیچ مدلی را نام نمی‌برد: هرچه در GeneratorRegistry باشد، در سه سایز
 * ۳۴، ۴۰ و ۴۸ با پارامترهای پیش‌فرض خودش ساخته و بررسی می‌شود. پس مدلی که فردا
 * افزوده شود هم خودکار زیر همین ذره‌بین می‌رود.
 *
 * دو دسته بررسی داریم:
 *
 *   الف) درستی هندسی — الگویی که مسیرش خودش را قطع کند، برچسب لبه‌اش کم و زیاد
 *        باشد، نشانه‌اش روی لبه‌ای که ادعا می‌کند نباشد یا راستای پارچه‌اش بیرون
 *        قطعه بیفتد، روی کاغذ چاپ می‌شود ولی بریده نمی‌شود.
 *
 *   ب) درستی خیاطی — دو درزی که به هم دوخته می‌شوند باید هم‌اندازه باشند، هر
 *      آستینی باید حلقه‌ای برای نشستن داشته باشد و اندازه تمام‌شده لباس باید در
 *      محدوده معقولی از اندازه بدن به‌اضافه آزادی بماند.
 *
 * هرجا مدلی به‌درستی نمی‌تواند یک بررسی را برآورده کند، در ALLOW_LIST با دلیل
 * نوشته‌شده کنار گذاشته می‌شود؛ رد شدن بی‌صدا نداریم.
 */
class CatalogAuditTest extends TestCase
{
    /**
     * بدن‌هایی که همه مدل‌ها روی آن‌ها ساخته می‌شوند.
     *
     * سه تای اول سایز جدولی است و بقیه اندازه سفارشی؛ چون سامانه برای هر مشتری با
     * اندازه خودش الگو می‌سازد، نه با سایز آماده. بدن‌های سفارشی عمداً «سخت»
     * انتخاب شده‌اند: تنه کوتاه، سرشانه باریک نسبت به سینه، و اختلاف زیاد سینه تا کمر.
     */
    protected const SIZES = ['34', '40', '48', 'کودک', 'سینه‌درشت', 'بلندقد', 'کوتاه و درشت'];

    /** اندازه بدن‌های سفارشی آزمون (سانتی‌متر). */
    protected const BESPOKE = [
        'کودک' => ['height' => 116, 'bust' => 60, 'waist' => 56, 'hip' => 64, 'shoulder_width' => 27, 'arm_length' => 38],
        'سینه‌درشت' => ['height' => 168, 'bust' => 118, 'waist' => 70, 'hip' => 100, 'shoulder_width' => 34, 'arm_length' => 59],
        'بلندقد' => ['height' => 195, 'bust' => 84, 'waist' => 66, 'hip' => 90, 'shoulder_width' => 44, 'arm_length' => 72],
        'کوتاه و درشت' => ['height' => 148, 'bust' => 124, 'waist' => 118, 'hip' => 126, 'shoulder_width' => 40, 'arm_length' => 50],
    ];

    /** برچسب‌های مجاز لبه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'strap', 'default'];

    /**
     * رواداری «روی لبه بودن» یک نقطه (سانتی‌متر).
     *
     * سه میلی‌متر همان پهنای خط مداد روی الگوی کاغذی است؛ دهانه ساسون یا نشانه‌ای
     * که بیش از این از لبه فاصله بگیرد، دیگر روی درز نمی‌افتد.
     */
    protected const ON_EDGE = 0.3;

    /** رواداری هم‌اندازه بودن دو درزی که به هم دوخته می‌شوند (سانتی‌متر). */
    protected const SEAM_MATCH = 0.15;

    /** کوچک‌ترین مساحت پذیرفتنی برای هر گونه قطعه (سانتی‌متر مربع). */
    protected const MIN_AREA = [
        'front_bodice' => 300.0,
        'back_bodice' => 300.0,
        'skirt_front' => 300.0,
        'skirt_back' => 300.0,
        'front_leg' => 600.0,
        'back_leg' => 600.0,
        'sleeve' => 100.0,
    ];

    /** کوچک‌ترین مساحت برای قطعه‌های ریز (نوار، پاتک، مچ‌بند…). */
    protected const MIN_AREA_ANY = 4.0;

    /** بزرگ‌ترین مساحت پذیرفتنی؛ بالاتر از این یعنی محاسبه از دست رفته است. */
    protected const MAX_AREA = 30000.0;

    /**
     * استثناهای مستند.
     *
     * کلید «بررسی:کلید مدل» است و مقدار، دلیل فارسی کنار گذاشتن آن. هیچ استثنایی
     * بدون دلیل نوشته‌شده پذیرفته نیست.
     */
    protected const ALLOW_LIST = [
        'side_seam:*leg' => 'در پاچه شلوار، درز پهلو و درز داخل پا و منحنی فاق هر سه برچسب side می‌گیرند و از هم جدا نمی‌شوند؛ فاقِ پشت هم عمداً بلندتر از جلوست، پس جمع طول این لبه‌ها یک جفتِ دوخت نیست و پیاده‌کردن آن معنا ندارد.',
        'side_seam:skirt_wrap' => 'لبه رویهم‌آمدن دامن پاکتی هم برچسب side دارد ولی به پشت دوخته نمی‌شود؛ جلو عمداً بلندتر از پشت است.',
        'side_seam:skirt_tulip' => 'گلبرگ‌های دامن لاله روی هم می‌افتند و لبه رویهم‌آمدن هم side است؛ اندازه آن با درز پهلوی پشت جفت نمی‌شود.',
        'girth:*leg' => 'خط باسن روی پاچه شلوار شامل پیش‌آمدگی فاق هم هست؛ آن پارچه دور بدن نمی‌پیچد، پس عرض پاچه اندازه دور باسن نیست.',
        'girth:*panel' => 'وقتی مدل از چند پانل باریک (ترک، برش شاهزاده‌ای، لُنگه) ساخته شده باشد، خط نشانه تنها روی بعضی پانل‌ها هست و جمع آن‌ها دور کامل بدن را نمی‌دهد.',
        'waist_girth:skirt_wrap' => 'کمر دامن پاکتی عمداً از دور کمر بلندتر است چون یک دور و نیم دور بدن می‌پیچد.',
        'bust_girth:abaya' => 'عبا لباسی رهاست که از سرشانه آزاد می‌ریزد؛ آزادی سینه آن عمداً حدود ۴۰ سانتی‌متر است و در بازه لباس‌های اندام‌نما نمی‌گنجد.',
    ];

    /** قطعه‌هایی که وجودشان یعنی مدل چندپانلی است و اندازه دور از خط نشانه درنمی‌آید. */
    protected const PANEL_PARTS = ['skirt_panel', 'godet', 'front_panel', 'back_panel', 'gore', 'panel'];

    /** حافظه نتیجه ساخت، تا هر آزمون دوباره کل کاتالوگ را نسازد. */
    protected static array $catalogue = [];

    /**
     * همه مدل‌ها در یک سایز: کلید مدل ⇒ قطعه‌ها.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function catalogue(string $size): array
    {
        if (isset(static::$catalogue[$size])) {
            return static::$catalogue[$size];
        }

        $measurements = $this->body($size);
        $built = [];

        foreach (array_keys(GeneratorRegistry::all()) as $key) {
            try {
                $generator = GeneratorRegistry::make($key);
                $built[$key] = $generator->generate($measurements, [], $generator->defaultParams());
            } catch (Throwable $error) {
                $built[$key] = $error;
            }
        }

        return static::$catalogue[$size] = $built;
    }

    /**
     * اندازه بدن یک ردیف آزمون: یا از جدول سایز، یا اندازه سفارشی تکمیل‌شده.
     *
     * @return array<string, float>
     */
    protected function body(string $size): array
    {
        return isset(static::BESPOKE[$size])
            ? Measurements::complete(static::BESPOKE[$size])
            : Measurements::fromSize($size);
    }

    /**
     * ضریب کوچک‌شدن کف مساحت برای بدن‌های کوچک‌تر از بدن مرجع (سایز ۴۰).
     *
     * کف مساحت برای گرفتن قطعه «تخت‌شده» است، نه برای اندازه بدن؛ شلوارک یک کودک
     * صد و شانزده سانتی طبیعتاً از شلوارک یک بزرگسال کوچک‌تر است. پس کف را با
     * نسبت مساحت بدن کوچک می‌کنیم و برای بدن‌های بزرگ‌تر همان کف بزرگسال می‌ماند.
     */
    protected function areaScale(string $size): float
    {
        $body = $this->body($size);

        return min(1.0, ($body['bust'] / 92) * ($body['height'] / 168));
    }

    /** آیا این بررسی برای این مدل کنار گذاشته شده است؟ */
    /**
     * ضریب کشسانی اعلام‌شدهٔ یک مدل، اگر لباس کشی باشد.
     *
     * فقط وقتی برمی‌گردد که قطعه‌های پوسته خودشان meta.stretch داشته باشند؛
     * یعنی الگو صریحاً گفته «من با آزادی منفی بریده شده‌ام».
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function declaredStretch(array $pieces): ?float
    {
        foreach ($pieces as $piece) {
            // فقط قطعهٔ پوسته و فقط کلید مخصوص خودش؛ meta.stretch روی نوار
            // کشباف معنای دیگری دارد و اگر با این یکی اشتباه شود، تی‌شرت هم
            // «لباس کشی با آزادی منفی» شمرده می‌شود.
            if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                continue;
            }

            $stretch = $piece['meta']['stretch_ratio'] ?? null;

            if (is_numeric($stretch) && (float) $stretch < 0.999) {
                return (float) $stretch;
            }
        }

        return null;
    }

    protected function allowed(string $check, string $key): bool
    {
        return array_key_exists($check.':'.$key, static::ALLOW_LIST);
    }

    /**
     * گزارش ایرادها؛ اگر چیزی پیدا شود آزمون با فهرست کامل می‌افتد.
     *
     * @param  array<int, string>  $problems
     */
    protected function assertNoProblems(array $problems, string $headline): void
    {
        if ($problems === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $shown = array_slice($problems, 0, 40);
        $more = count($problems) - count($shown);

        $this->fail(
            $headline.' ('.count($problems)." مورد)\n  - ".implode("\n  - ", $shown)
            .($more > 0 ? "\n  … و {$more} مورد دیگر" : ''),
        );
    }

    /** «مدل|سایز|قطعه» برای پیام‌های خطا. */
    protected function where(string $key, string $size, array $piece): string
    {
        return $key.'|'.$size.'|'.($piece['code'] ?? '?');
    }

    /* ---------------------------------------------------------------------
     |  الف) درستی هندسی
     * ------------------------------------------------------------------- */

    public function test_every_generator_in_the_registry_builds_at_every_size(): void
    {
        $problems = [];
        $counted = 0;

        foreach (static::SIZES as $size) {
            foreach ($this->catalogue($size) as $key => $pieces) {
                $counted++;

                if ($pieces instanceof Throwable) {
                    $problems[] = "{$key}|{$size} ساخته نشد: ".get_class($pieces).' — '.$pieces->getMessage();

                    continue;
                }

                if ($pieces === []) {
                    $problems[] = "{$key}|{$size} هیچ قطعه‌ای نداد.";

                    continue;
                }

                $codes = array_column($pieces, 'code');

                if (count($codes) !== count(array_unique($codes))) {
                    $duplicates = implode('، ', array_keys(array_filter(array_count_values($codes), fn ($n) => $n > 1)));
                    $problems[] = "{$key}|{$size} کد قطعه تکراری دارد: {$duplicates}";
                }
            }
        }

        $this->assertGreaterThanOrEqual(
            3 * 40,
            $counted,
            'فهرست مدل‌ها ناگهان کوچک شده است؛ شناسایی خودکار کار نمی‌کند.',
        );
        $this->assertNoProblems($problems, 'ساخت مدل‌های کاتالوگ');
    }

    public function test_every_outline_is_a_clean_closed_polygon(): void
    {
        $problems = [];

        foreach ($this->pieces() as [$key, $size, $piece]) {
            $where = $this->where($key, $size, $piece);
            $outline = array_values($piece['outline'] ?? []);
            $count = count($outline);

            if ($count < 3) {
                $problems[] = "{$where} مسیر تنها {$count} نقطه دارد.";

                continue;
            }

            foreach ($outline as $index => $point) {
                foreach (['x', 'y', 'cx', 'cy'] as $axis) {
                    if (isset($point[$axis]) && ! is_finite((float) $point[$axis])) {
                        $problems[] = "{$where} نقطه {$index} مختصات {$axis} نامعتبر دارد.";
                    }
                }
            }

            for ($i = 0; $i < $count; $i++) {
                $a = $outline[$i];
                $b = $outline[($i + 1) % $count];
                $gap = Geometry::distance(
                    ['x' => (float) $a['x'], 'y' => (float) $a['y']],
                    ['x' => (float) $b['x'], 'y' => (float) $b['y']],
                );

                if ($gap < 1e-4) {
                    $problems[] = "{$where} نقطه {$i} و نقطه بعدی روی هم افتاده‌اند.";
                }
            }

            if (Geometry::selfIntersects($outline)) {
                $problems[] = "{$where} مسیر قطعه خودش را قطع می‌کند و بریده نمی‌شود.";
            }

            $area = Geometry::area($outline);
            $part = (string) ($piece['meta']['part'] ?? '');
            $floor = round((static::MIN_AREA[$part] ?? static::MIN_AREA_ANY) * $this->areaScale($size), 1);

            if ($area < $floor) {
                $problems[] = "{$where} مساحت {$area} سانتی‌متر مربع است؛ برای «{$part}» دست‌کم {$floor} انتظار می‌رود.";
            }

            if ($area > static::MAX_AREA) {
                $problems[] = "{$where} مساحت {$area} سانتی‌متر مربع است و باورکردنی نیست.";
            }
        }

        $this->assertNoProblems($problems, 'درستی مسیر قطعه‌ها');
    }

    public function test_every_edge_carries_exactly_one_known_tag(): void
    {
        $problems = [];

        foreach ($this->pieces() as [$key, $size, $piece]) {
            $where = $this->where($key, $size, $piece);
            $count = count($piece['outline'] ?? []);
            $tags = $piece['meta']['edges'] ?? null;

            if (! is_array($tags)) {
                $problems[] = "{$where} برچسب لبه ندارد (meta.edges).";

                continue;
            }

            if (count($tags) !== $count) {
                $problems[] = "{$where} ".count($tags)." برچسب برای {$count} لبه دارد.";
            }

            foreach ($tags as $index => $tag) {
                if (! in_array($tag, static::EDGE_TAGS, true)) {
                    $problems[] = "{$where} لبه {$index} برچسب ناشناخته «".(is_string($tag) ? $tag : gettype($tag)).'» دارد.';
                }
            }

            foreach ($piece['meta']['fold_edges'] ?? [] as $edge) {
                if (! is_int($edge) || $edge < 0 || $edge >= $count) {
                    $problems[] = "{$where} لبه تای پارچه {$edge} بیرون از مسیر است.";
                }
            }
        }

        $this->assertNoProblems($problems, 'برچسب لبه‌ها');
    }

    public function test_every_piece_has_a_grainline_inside_its_own_bounds(): void
    {
        $problems = [];

        foreach ($this->pieces() as [$key, $size, $piece]) {
            $where = $this->where($key, $size, $piece);
            $grainline = $piece['grainline'] ?? null;

            if (! isset($grainline['from']['x'], $grainline['from']['y'], $grainline['to']['x'], $grainline['to']['y'])) {
                $problems[] = "{$where} راستای پارچه ندارد.";

                continue;
            }

            [$minX, $minY, $maxX, $maxY] = Geometry::bounds($piece['outline']);

            foreach (['from', 'to'] as $end) {
                $x = (float) $grainline[$end]['x'];
                $y = (float) $grainline[$end]['y'];

                if ($x < $minX - 0.01 || $x > $maxX + 0.01 || $y < $minY - 0.01 || $y > $maxY + 0.01) {
                    $problems[] = sprintf(
                        '%s سر «%s» راستای پارچه در (%.2f، %.2f) بیرون کادر قطعه (%.2f…%.2f، %.2f…%.2f) است.',
                        $where, $end, $x, $y, $minX, $maxX, $minY, $maxY,
                    );
                }
            }

            $length = Geometry::distance(
                ['x' => (float) $grainline['from']['x'], 'y' => (float) $grainline['from']['y']],
                ['x' => (float) $grainline['to']['x'], 'y' => (float) $grainline['to']['y']],
            );

            if ($length < 1.0) {
                $problems[] = sprintf('%s راستای پارچه تنها %.2f سانتی‌متر است.', $where, $length);
            }
        }

        $this->assertNoProblems($problems, 'راستای پارچه');
    }

    public function test_darts_notches_and_markers_sit_where_they_claim_to_sit(): void
    {
        $problems = [];

        foreach ($this->pieces() as [$key, $size, $piece]) {
            $where = $this->where($key, $size, $piece);
            $outline = array_values($piece['outline'] ?? []);
            $count = count($outline);

            if ($count < 3) {
                continue;
            }

            foreach ($piece['darts'] ?? [] as $index => $dart) {
                $label = $dart['label'] ?? $index;
                $legs = array_values($dart['legs'] ?? []);

                if (count($legs) !== 2) {
                    $problems[] = "{$where} ساسون «{$label}» دو پا ندارد.";

                    continue;
                }

                if (((float) ($dart['intake'] ?? 0)) < 0) {
                    $problems[] = "{$where} ساسون «{$label}» پهنای منفی دارد.";
                }

                $edge = $dart['edge'] ?? null;

                // ساسون بادامی وسط قطعه لبه ندارد و پاهایش هم روی محیط نیست
                if ($edge !== null) {
                    if (! is_int($edge) || $edge < 0 || $edge >= $count) {
                        $problems[] = "{$where} ساسون «{$label}» به لبه {$edge} اشاره می‌کند که روی مسیر نیست.";
                    } else {
                        foreach ($legs as $side => $leg) {
                            $distance = $this->distanceToEdge($outline, $edge, $leg);

                            if ($distance > static::ON_EDGE) {
                                $problems[] = sprintf(
                                    '%s پای %d ساسون «%s» %.2f سانتی‌متر از لبه %d فاصله دارد.',
                                    $where, $side, $label, $distance, $edge,
                                );
                            }
                        }
                    }
                }

                $apex = $dart['apex'] ?? null;

                if (! isset($apex['x'], $apex['y'])) {
                    $problems[] = "{$where} ساسون «{$label}» نوک ندارد.";

                    continue;
                }

                $at = ['x' => (float) $apex['x'], 'y' => (float) $apex['y']];

                if (! Geometry::containsPoint($outline, $at) && Geometry::nearestEdge($outline, $at)['distance'] > static::ON_EDGE) {
                    $problems[] = "{$where} نوک ساسون «{$label}» بیرون قطعه افتاده است.";
                }
            }

            foreach ($piece['notches'] ?? [] as $index => $notch) {
                $label = $notch['label'] ?? $index;

                if (! isset($notch['x'], $notch['y'])) {
                    $problems[] = "{$where} نشانه «{$label}» مختصات ندارد.";

                    continue;
                }

                $edge = $notch['edge'] ?? null;

                if ($edge === null) {
                    continue;
                }

                if (! is_int($edge) || $edge < 0 || $edge >= $count) {
                    $problems[] = "{$where} نشانه «{$label}» به لبه {$edge} اشاره می‌کند ولی مسیر {$count} لبه دارد.";

                    continue;
                }

                $distance = $this->distanceToEdge($outline, $edge, $notch);

                if ($distance > static::ON_EDGE) {
                    $problems[] = sprintf(
                        '%s نشانه «%s» %.2f سانتی‌متر از لبه %d که ادعا می‌کند فاصله دارد.',
                        $where, $label, $distance, $edge,
                    );
                }
            }

            [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);

            foreach ($piece['markers'] ?? [] as $index => $marker) {
                $name = $marker['key'] ?? $index;

                foreach (['from', 'to'] as $end) {
                    if (! isset($marker[$end]['x'], $marker[$end]['y'])) {
                        $problems[] = "{$where} خط نشانه «{$name}» سر «{$end}» ندارد.";

                        continue;
                    }

                    $x = (float) $marker[$end]['x'];
                    $y = (float) $marker[$end]['y'];

                    if ($x < $minX - 0.05 || $x > $maxX + 0.05 || $y < $minY - 0.05 || $y > $maxY + 0.05) {
                        $problems[] = sprintf(
                            '%s خط نشانه «%s» سر «%s» در (%.2f، %.2f) بیرون کادر قطعه (%.2f…%.2f، %.2f…%.2f) است.',
                            $where, $name, $end, $x, $y, $minX, $maxX, $minY, $maxY,
                        );
                    }
                }
            }
        }

        $this->assertNoProblems($problems, 'جای ساسون، نشانه و خط نشانه');
    }

    public function test_cut_quantity_and_fold_edges_make_sense(): void
    {
        $problems = [];

        foreach ($this->pieces() as [$key, $size, $piece]) {
            $where = $this->where($key, $size, $piece);
            $quantity = $piece['cut_quantity'] ?? null;

            if (! is_int($quantity) || $quantity < 1) {
                $problems[] = "{$where} تعداد برش «".var_export($quantity, true).'» است.';
            }

            if (! empty($piece['on_fold']) && ($piece['meta']['fold_edges'] ?? []) === []) {
                $problems[] = "{$where} روی تای پارچه بریده می‌شود ولی هیچ لبه‌ای را تا معرفی نکرده است.";
            }

            if (empty($piece['on_fold']) && ($piece['meta']['fold_edges'] ?? []) !== []) {
                $problems[] = "{$where} لبه تا دارد ولی on_fold آن خاموش است.";
            }
        }

        $this->assertNoProblems($problems, 'تعداد برش و لبه تای پارچه');
    }

    /* ---------------------------------------------------------------------
     |  ب) درستی خیاطی
     * ------------------------------------------------------------------- */

    public function test_side_seams_that_get_sewn_together_walk_to_the_same_length(): void
    {
        $problems = [];
        $checked = 0;

        foreach (static::SIZES as $size) {
            foreach ($this->catalogue($size) as $key => $pieces) {
                if ($pieces instanceof Throwable || $this->allowed('side_seam', $key)) {
                    continue;
                }

                foreach ($this->sidePairs($pieces) as [$front, $back]) {
                    $checked++;
                    $walk = PieceOps::walk($front, 'side', $back, 'side', ['tolerance' => static::SEAM_MATCH]);

                    if (! $walk['matched']) {
                        $problems[] = sprintf(
                            '%s|%s درز پهلوی «%s» %.2f و «%s» %.2f است؛ اختلاف %.2f سانتی‌متر.',
                            $key, $size, $front['code'], $walk['a']['seam'], $back['code'], $walk['b']['seam'], $walk['difference'],
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(30, $checked, 'هیچ جفت درز پهلویی برای پیاده‌کردن پیدا نشد.');
        $this->assertNoProblems($problems, 'پیاده‌کردن درز پهلوی جلو و پشت');
    }

    public function test_a_sleeve_always_finds_an_armhole_to_go_into(): void
    {
        $problems = [];

        foreach (static::SIZES as $size) {
            foreach ($this->catalogue($size) as $key => $pieces) {
                if ($pieces instanceof Throwable) {
                    continue;
                }

                $group = GeneratorRegistry::groupOf($key);
                $bodices = $this->partsLike($pieces, ['front_bodice', 'back_bodice']);
                $sleeves = $this->partsLike($pieces, ['sleeve']);
                // یوک هم بخشی از حلقه آستین را با خود می‌برد
                $holders = array_merge($bodices, $this->partsLike($pieces, ['yoke']));

                foreach ($bodices as $bodice) {
                    // تاپ استرپلس و آفشولدر عمداً حلقه ندارند و خودشان می‌گویند؛
                    // بقیه اگر حلقه نداشته باشند یعنی چیزی خراب شده
                    if (! empty($bodice['meta']['sleeveless'])) {
                        continue;
                    }

                    if (Geometry::edgesWithTag($bodice, 'armhole') === []) {
                        $problems[] = "{$key}|{$size}|{$bodice['code']} بالاتنه است ولی حلقه آستین ندارد.";
                    }
                }

                if ($sleeves !== [] && $group !== 'sleeve' && $bodices !== []) {
                    $armhole = 0.0;

                    foreach ($holders as $holder) {
                        if (Geometry::edgesWithTag($holder, 'armhole') !== []) {
                            $armhole += PieceOps::edgeLength($holder, 'armhole');
                        }
                    }

                    $cap = 0.0;

                    foreach ($sleeves as $sleeve) {
                        $edges = Geometry::edgesWithTag($sleeve, 'armhole');

                        if ($edges !== []) {
                            $cap += PieceOps::edgeLength($sleeve, $edges);
                        }
                    }

                    if ($cap <= 0.0) {
                        $problems[] = "{$key}|{$size} آستین دارد ولی هیچ لبه‌ای از آن برچسب حلقه آستین ندارد.";

                        continue;
                    }

                    $ease = $cap - $armhole;

                    // سرآستین باید کمی از حلقه بلندتر باشد تا سر آستین فرم بگیرد،
                    // ولی بیش از یک‌چهارم حلقه یعنی درفت از دست رفته است
                    if ($ease < -0.5 || $ease > max(6.0, $armhole * 0.25)) {
                        $problems[] = sprintf(
                            '%s|%s سرآستین %.1f و حلقه آستین %.1f است؛ آزادی %.1f سانتی‌متر پذیرفتنی نیست.',
                            $key, $size, $cap, $armhole, $ease,
                        );
                    }
                }
            }
        }

        $this->assertNoProblems($problems, 'جفت شدن آستین با حلقه آستین');
    }

    public function test_the_waist_openings_of_the_upper_and_lower_parts_agree(): void
    {
        $problems = [];
        $checked = 0;

        foreach (static::SIZES as $size) {
            foreach ($this->catalogue($size) as $key => $pieces) {
                if ($pieces instanceof Throwable) {
                    continue;
                }

                $upper = $this->waistGirth($pieces, ['front_bodice', 'back_bodice', 'yoke']);
                $lower = $this->waistGirth($pieces, ['skirt_front', 'skirt_back', 'skirt_tier', 'peplum', 'front_leg', 'back_leg']);

                if ($upper <= 0.0 || $lower <= 0.0) {
                    continue;
                }

                $checked++;

                if (abs($upper - $lower) > 1.0) {
                    $problems[] = sprintf(
                        '%s|%s کمر بالاتنه %.1f و کمر پایین‌تنه %.1f است؛ این دو به هم دوخته می‌شوند.',
                        $key, $size, $upper, $lower,
                    );
                }
            }
        }

        $this->assertNoProblems($problems, 'هم‌اندازه بودن کمر بالاتنه و پایین‌تنه');
        $this->addToAssertionCount(1);
    }

    public function test_finished_girths_stay_within_a_sane_band_of_the_body(): void
    {
        $problems = [];
        $checked = 0;

        // کمترین و بیشترین آزادی پذیرفتنی برای هر ناحیه (سانتی‌متر).
        // آزادی منفی برای پارچه کشی (تی‌شرت و بالاتنه کشباف) درست است، ولی بیش از
        // ده سانتی‌متر تنگ‌تر از بدن یعنی درفت از دست رفته. کمر بازه فراخ‌تری دارد
        // چون از لباس تنگ تا لباس چادری همه در کاتالوگ هست.
        $band = ['bust' => [-10.0, 30.0], 'waist' => [-6.0, 60.0], 'hip' => [-10.0, 30.0]];

        foreach (static::SIZES as $size) {
            $body = $this->body($size);

            // لباسی که از سینه به پایین راست بریده می‌شود (تی‌شرت گشاد، سویشرت،
            // مانتو راست، بمبر) دور کمرش همان دور سینه‌اش است. پس روی بدنی که
            // فاصله سینه تا کمرش زیاد است، آزادی کمر ناچار به همان اندازه بیشتر
            // می‌شود؛ این ایراد درفت نیست، شکل بدن است. سقف بازه را با همان
            // اختلاف بالا می‌بریم و کف بازه دست‌نخورده می‌ماند.
            $drop = [
                'bust' => 0.0,
                'waist' => max(0.0, $body['bust'] - $body['waist']),
                'hip' => max(0.0, $body['bust'] - $body['hip']),
            ];

            foreach ($this->catalogue($size) as $key => $pieces) {
                if ($pieces instanceof Throwable) {
                    continue;
                }

                // لباس کشی (مایو) عمداً کوچک‌تر از بدن بریده می‌شود و در بازه
                // لباس بافته نمی‌گنجد. به‌جای کنار گذاشتنش، سنجه عوض می‌شود:
                // خودِ الگو می‌گوید با چه ضریب کشسانی بریده شده، و بررسی می‌کنیم
                // که واقعاً همان‌قدر کوچک شده باشد. این سخت‌گیرانه‌تر از بازه است.
                $stretch = $this->declaredStretch($pieces);

                foreach ($this->markerGirths($pieces) as $area => $girth) {
                    if ($this->allowed($area.'_girth', $key)) {
                        continue;
                    }

                    $checked++;

                    if ($stretch !== null) {
                        // لباس کشیِ راست‌بریده هم مثل لباس بافتهٔ راست، کمرش را
                        // از سینه می‌گیرد؛ همان روادارییِ $drop این‌جا هم لازم است
                        $expected = $body[$area] * $stretch;
                        $ceiling = ($body[$area] + $drop[$area]) * $stretch;

                        if ($girth < $expected - max(6.0, $expected * 0.12)
                            || $girth > $ceiling + max(6.0, $ceiling * 0.12)) {
                            $problems[] = sprintf(
                                '%s|%s اندازه تمام‌شده %s %.1f است؛ با ضریب کشسانی %.2f روی بدن %.1f باید میان %.1f و %.1f می‌شد.',
                                $key, $size, $area, $girth, $stretch, $body[$area], $expected, $ceiling,
                            );
                        }

                        continue;
                    }

                    $ease = $girth - $body[$area];
                    [$low, $high] = $band[$area];
                    $high += $drop[$area];

                    if ($ease < $low || $ease > $high) {
                        $problems[] = sprintf(
                            '%s|%s اندازه تمام‌شده %s %.1f است در برابر بدن %.1f؛ آزادی %.1f سانتی‌متر بیرون بازه %.1f تا %.1f است.',
                            $key, $size, $area, $girth, $body[$area], $ease, $low, $high,
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(30, $checked, 'هیچ اندازه تمام‌شده‌ای برای سنجیدن پیدا نشد.');
        $this->assertNoProblems($problems, 'اندازه تمام‌شده لباس در برابر اندازه بدن');
    }

    /* ---------------------------------------------------------------------
     |  کارایی
     * ------------------------------------------------------------------- */

    public function test_building_the_whole_catalogue_stays_fast(): void
    {
        static::$catalogue = [];

        $measurements = Measurements::fromSize('40');
        $keys = array_keys(GeneratorRegistry::all());
        $before = memory_get_usage();
        $slow = [];
        $total = 0.0;

        foreach ($keys as $key) {
            $generator = GeneratorRegistry::make($key);
            $params = $generator->defaultParams();

            $start = hrtime(true);
            $generator->generate($measurements, [], $params);
            $spent = (hrtime(true) - $start) / 1e6;

            $total += $spent;

            if ($spent > 250.0) {
                $slow[] = sprintf('%s در %.0f میلی‌ثانیه ساخته شد.', $key, $spent);
            }
        }

        $this->assertNoProblems($slow, 'مدل‌های کند');
        $this->assertLessThan(
            8000.0,
            $total,
            sprintf('ساخت %d مدل %.0f میلی‌ثانیه طول کشید.', count($keys), $total),
        );
        $this->assertLessThan(
            192 * 1024 * 1024,
            memory_get_peak_usage(true),
            'مصرف حافظه ساخت کاتالوگ از اندازه معقول گذشت.',
        );
        $this->assertLessThan(64 * 1024 * 1024, memory_get_usage() - $before);
    }

    /* ---------------------------------------------------------------------
     |  کمک‌کارها
     * ------------------------------------------------------------------- */

    /**
     * همه قطعه‌های همه مدل‌ها در همه سایزها.
     *
     * @return iterable<int, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    protected function pieces(): iterable
    {
        foreach (static::SIZES as $size) {
            foreach ($this->catalogue($size) as $key => $pieces) {
                if ($pieces instanceof Throwable) {
                    continue;
                }

                foreach ($pieces as $piece) {
                    yield [$key, $size, $piece];
                }
            }
        }
    }

    /** فاصله یک نقطه تا لبه‌ای که ادعا می‌کند رویش نشسته است. */
    protected function distanceToEdge(array $outline, int $edge, array $point): float
    {
        $at = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
        $t = Geometry::edgeParameterOf($outline, $edge, $at, 32);

        return Geometry::distance(Geometry::pointOnEdge($outline, $edge, $t), $at);
    }

    /**
     * قطعه‌هایی که meta.part آن‌ها در این فهرست است.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function partsLike(array $pieces, array $parts): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => in_array((string) ($piece['meta']['part'] ?? ''), $parts, true),
        ));
    }

    /**
     * جفت‌های جلو/پشتی که درز پهلویشان به هم دوخته می‌شود.
     *
     * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    protected function sidePairs(array $pieces): array
    {
        $pairs = [];

        // جفت پاچه شلوار عمداً نیست: ALLOW_LIST['side_seam:*leg']
        foreach ([['front_bodice', 'back_bodice'], ['skirt_front', 'skirt_back']] as [$frontPart, $backPart]) {
            $fronts = $this->partsLike($pieces, [$frontPart]);
            $backs = $this->partsLike($pieces, [$backPart]);

            if (count($fronts) !== 1 || count($backs) !== 1) {
                continue; // مدل‌های چندپانلی جفت درز پهلوی ساده ندارند
            }

            if (Geometry::edgesWithTag($fronts[0], 'side') === [] || Geometry::edgesWithTag($backs[0], 'side') === []) {
                continue;
            }

            $pairs[] = [$fronts[0], $backs[0]];
        }

        return $pairs;
    }

    /** دور کمر تمام‌شده‌ای که این دسته قطعه‌ها می‌سازند. */
    protected function waistGirth(array $pieces, array $parts): float
    {
        $total = 0.0;

        foreach ($this->partsLike($pieces, $parts) as $piece) {
            $edges = Geometry::edgesWithTag($piece, 'waist');

            if ($edges === []) {
                continue;
            }

            $length = PieceOps::seamLength($piece, $edges);

            foreach ($edges as $edge) {
                foreach ($piece['pleats'] ?? [] as $pleat) {
                    // پیلی‌هایی که تولیدکننده روی خطِ خودش ثبت کرده و شماره لبه ندارند
                    if (($pleat['edge'] ?? null) === null && isset($pleat['intake'])) {
                        $length -= ((float) $pleat['intake']) / max(1, count($edges));
                    }
                }
            }

            $total += max(0.0, $length) * (int) ($piece['cut_quantity'] ?? 1) * (empty($piece['on_fold']) ? 1 : 2);
        }

        return round($total, 2);
    }

    /**
     * اندازه تمام‌شده سینه، کمر و باسن از روی خط‌های نشانه.
     *
     * فقط وقتی شمرده می‌شود که هم جلو و هم پشت آن خط نشانه را داشته باشند، وگرنه
     * تنها نیمی از دور لباس اندازه گرفته می‌شود.
     *
     * @return array<string, float>
     */
    protected function markerGirths(array $pieces): array
    {
        $totals = [];
        $sides = [];

        // ALLOW_LIST['girth:*panel'] — مدل چندپانلی اندازه دور را از خط نشانه نمی‌دهد
        foreach ($pieces as $piece) {
            if (in_array((string) ($piece['meta']['part'] ?? ''), static::PANEL_PARTS, true)) {
                return [];
            }
        }

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            // ALLOW_LIST['girth:*leg'] — پاچه شلوار پیش‌آمدگی فاق را هم با خود دارد
            if (! in_array($part, ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back'], true)) {
                continue;
            }

            $side = str_contains($part, 'front') ? 'front' : 'back';

            foreach ($piece['markers'] ?? [] as $marker) {
                $area = (string) ($marker['key'] ?? '');

                if (! in_array($area, ['bust', 'waist', 'hip'], true) || ! isset($marker['from']['x'], $marker['to']['x'])) {
                    continue;
                }

                $width = abs(((float) $marker['to']['x']) - ((float) $marker['from']['x']));

                if ($width < 0.01) {
                    continue; // خط نشانه عمودی، اندازه دور نیست
                }

                $totals[$area] = ($totals[$area] ?? 0.0)
                    + ($width * (int) ($piece['cut_quantity'] ?? 1) * (empty($piece['on_fold']) ? 1 : 2));
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
}
