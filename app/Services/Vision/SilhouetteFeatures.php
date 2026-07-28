<?php

namespace App\Services\Vision;

/**
 * ویژگی‌های اندازه‌گیری‌شده از یک سیلوئت.
 *
 * اینجا هیچ حدسی درباره «نوع لباس» زده نمی‌شود؛ فقط چیزهایی که واقعاً روی شکل
 * قابل اندازه‌گیری‌اند شمرده می‌شوند. تصمیم‌گیری کار GarmentClassifier است.
 *
 * همه اندازه‌ها نسبی‌اند (تقسیم بر پهن‌ترین سطر یا بر پهنای بالای شکل) چون در یک
 * عکس هیچ خط‌کشی وجود ندارد؛ پس «سانتی‌متر» ادعا نمی‌کنیم.
 */
final class SilhouetteFeatures
{
    /** شمار پله‌های نیم‌رخ پهنا (هر ۵٪ قد). */
    public const LEVELS = 21;

    /**
     * @param  array<int, float>  $profile  پهنای هر پله نسبت به پهن‌ترین سطر (۰ تا ۱)
     * @param  array<int, float>  $gaps  سهم فاصله خالی وسط هر سطر (نشانه دو پاچه)
     * @param  array<int, string>  $notes  هشدارهای کیفیت ورودی
     */
    public function __construct(
        public readonly int $boxWidth,
        public readonly int $boxHeight,
        public readonly float $aspect,
        public readonly array $profile,
        public readonly array $gaps,
        public readonly float $upperWidth,
        public readonly float $chestWidth,
        public readonly float $waistWidth,
        public readonly float $hipWidth,
        public readonly float $hemWidth,
        public readonly float $lengthRatio,
        public readonly float $hemRatio,
        public readonly float $waistPinch,
        public readonly float $widestAt,
        public readonly float $topNarrowness,
        public readonly float $splitRatio,
        public readonly ?float $splitStart,
        public readonly float $sleeveBump,
        public readonly float $sleeveSpan,
        public readonly float $neckDepth,
        public readonly float $neckWidth,
        public readonly float $neckFullness,
        public readonly float $symmetry,
        public readonly float $fillRatio,
        public readonly float $coverage,
        public readonly array $touchedEdges,
        public readonly array $notes = [],
    ) {}

    /**
     * اندازه‌گیری همه ویژگی‌ها از روی نقاب.
     *
     * @param  array<int, string>  $notes
     */
    public static function extract(Silhouette $mask, array $notes = []): self
    {
        $bounds = $mask->bounds();

        if ($bounds === null) {
            return self::empty(array_merge($notes, ['هیچ شکلی پیدا نشد.']));
        }

        $height = max(1, $bounds['height']);
        $width = max(1, $bounds['width']);

        $extents = [];
        $filled = [];

        for ($level = 0; $level < self::LEVELS; $level++) {
            $y = $bounds['min_y'] + (int) round($level * ($height - 1) / (self::LEVELS - 1));
            $runs = $mask->runs($y);

            if ($runs === []) {
                $extents[$level] = 0.0;
                $filled[$level] = 0.0;

                continue;
            }

            $first = $runs[0][0];
            $last = $runs[count($runs) - 1][1];
            $extents[$level] = (float) ($last - $first + 1);
            $filled[$level] = (float) array_sum(array_map(fn ($run) => $run[1] - $run[0] + 1, $runs));
        }

        $widest = max(1.0, max($extents));
        $profile = array_map(fn ($value) => round($value / $widest, 4), $extents);
        $gaps = [];

        foreach ($extents as $level => $extent) {
            $gaps[$level] = $extent > 0 ? round(($extent - $filled[$level]) / $extent, 4) : 0.0;
        }

        // پهنای بالای لباس (سرشانه یا کمر) از سطرهای ۵٪ تا ۱۵٪ قد خوانده می‌شود، نه از
        // بیشترین پهنای بالا: در عکس یک تی‌شرت پهن‌شده، آستین‌های باز بیشترین پهنای
        // بالا هستند و اگر مرجع می‌شدند، لباس همیشه «کوتاه و پهن» به نظر می‌رسید.
        $upper = max(0.01, self::zoneMean($profile, 0.02, 0.16));
        $chest = self::zoneMean($profile, 0.20, 0.38);
        $waist = self::zoneMean($profile, 0.40, 0.56);
        $hip = self::zoneMean($profile, 0.58, 0.76);
        $hem = self::zoneMean($profile, 0.88, 1.0);
        $body = max(0.01, self::zoneMedian($profile, 0.38, 0.75));

        // کمر وقتی «گرفته» است که هم از بالای خودش باریک‌تر باشد و هم از پایین خودش،
        // پس مرجع مقایسه باریک‌ترینِ این دو است نه میانگینشان. اگر میانگین می‌گرفتیم،
        // آستین‌های باز یک تی‌شرت سینه را پهن نشان می‌داد و لباس الکی «قالب‌دار» می‌شد.
        $reference = max(0.01, min($chest, $hip));
        [$splitRatio, $splitStart] = self::splits($mask, $bounds, $widest);
        [$neckDepth, $neckWidth, $neckFullness] = self::neckline($mask, $bounds);
        [$sleeveBump, $sleeveSpan] = self::sleeves($profile, $body);

        return new self(
            boxWidth: $width,
            boxHeight: $height,
            aspect: round($height / $width, 4),
            profile: $profile,
            gaps: $gaps,
            upperWidth: round($upper, 4),
            chestWidth: round($chest, 4),
            waistWidth: round($waist, 4),
            hipWidth: round($hip, 4),
            hemWidth: round($hem, 4),
            lengthRatio: round($height / max(1.0, $upper * $widest), 4),
            hemRatio: round($hem / $upper, 4),
            waistPinch: round(max(-1.0, min(1.0, 1 - $waist / $reference)), 4),
            widestAt: round(self::widestPosition($profile), 4),
            topNarrowness: round($profile[0] > 0 ? $profile[0] : self::zoneMean($profile, 0.0, 0.05), 4),
            splitRatio: round($splitRatio, 4),
            splitStart: $splitStart === null ? null : round($splitStart, 4),
            sleeveBump: round($sleeveBump, 4),
            sleeveSpan: round($sleeveSpan, 4),
            neckDepth: round($neckDepth, 4),
            neckWidth: round($neckWidth, 4),
            neckFullness: round($neckFullness, 4),
            symmetry: round($mask->symmetry(), 4),
            fillRatio: round($mask->area() / max(1, $width * $height), 4),
            coverage: round($mask->coverage(), 4),
            touchedEdges: $mask->touchedEdges(),
            notes: $notes,
        );
    }

    /** ویژگی‌های خالی برای وقتی هیچ شکلی پیدا نشد. */
    public static function empty(array $notes = []): self
    {
        return new self(
            boxWidth: 0, boxHeight: 0, aspect: 1.0,
            profile: array_fill(0, self::LEVELS, 0.0),
            gaps: array_fill(0, self::LEVELS, 0.0),
            upperWidth: 0.0, chestWidth: 0.0, waistWidth: 0.0, hipWidth: 0.0, hemWidth: 0.0,
            lengthRatio: 1.0, hemRatio: 1.0, waistPinch: 0.0, widestAt: 0.0, topNarrowness: 0.0,
            splitRatio: 0.0, splitStart: null, sleeveBump: 1.0, sleeveSpan: 0.0,
            neckDepth: 0.0, neckWidth: 0.0, neckFullness: 0.0,
            symmetry: 0.0, fillRatio: 0.0, coverage: 0.0, touchedEdges: [], notes: $notes,
        );
    }

    /**
     * «چقدر شکل حرف برای گفتن دارد».
     *
     * یک مستطیل ساده هیچ نشانه‌ای ندارد؛ در آن حالت هر تشخیصی حدس است و اطمینان
     * باید پایین بیاید. این عدد بین ۰ و ۱ همین را می‌سنجد.
     */
    public function distinctiveness(): float
    {
        $signals = [
            abs($this->hemRatio - 1) / 0.30,
            abs($this->waistPinch) / 0.14,
            $this->splitRatio / 0.30,
            max(0.0, $this->sleeveBump - 1) / 0.22,
            $this->neckDepth / 0.06,
            abs($this->lengthRatio - 2.2) / 3.0,
        ];

        return round(max(0.0, min(1.0, max($signals))), 4);
    }

    /**
     * کیفیت ورودی (۰ تا ۱): قرینگی، اندازه شکل در قاب و بریده‌نبودن لبه‌ها.
     */
    public function quality(): float
    {
        $quality = 1.0;

        // شکل خیلی ریز یا تقریباً تمام قاب: جداسازی احتمالاً درست نبوده
        if ($this->coverage < 0.04) {
            $quality *= 0.65;
        } elseif ($this->coverage > 0.85) {
            $quality *= 0.7;
        }

        $quality *= 0.55 + 0.45 * max(0.0, min(1.0, ($this->symmetry - 0.5) / 0.4));

        $sideCuts = count(array_intersect($this->touchedEdges, ['start', 'end', 'top', 'bottom']));
        $quality *= max(0.6, 1 - 0.1 * $sideCuts);

        return round(max(0.15, min(1.0, $quality)), 4);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'box_width' => $this->boxWidth,
            'box_height' => $this->boxHeight,
            'aspect' => $this->aspect,
            'profile' => $this->profile,
            'gaps' => $this->gaps,
            'upper_width' => $this->upperWidth,
            'chest_width' => $this->chestWidth,
            'waist_width' => $this->waistWidth,
            'hip_width' => $this->hipWidth,
            'hem_width' => $this->hemWidth,
            'length_ratio' => $this->lengthRatio,
            'hem_ratio' => $this->hemRatio,
            'waist_pinch' => $this->waistPinch,
            'widest_at' => $this->widestAt,
            'top_narrowness' => $this->topNarrowness,
            'split_ratio' => $this->splitRatio,
            'split_start' => $this->splitStart,
            'sleeve_bump' => $this->sleeveBump,
            'sleeve_span' => $this->sleeveSpan,
            'neck_depth' => $this->neckDepth,
            'neck_width' => $this->neckWidth,
            'neck_fullness' => $this->neckFullness,
            'symmetry' => $this->symmetry,
            'fill_ratio' => $this->fillRatio,
            'coverage' => $this->coverage,
            'touched_edges' => $this->touchedEdges,
            'distinctiveness' => $this->distinctiveness(),
            'quality' => $this->quality(),
            'notes' => $this->notes,
        ];
    }

    /** بیشترین پهنا در بازه‌ای از قد (۰ تا ۱). */
    private static function zoneMax(array $profile, float $from, float $to): float
    {
        $values = self::zone($profile, $from, $to);

        return $values === [] ? 0.0 : max($values);
    }

    private static function zoneMean(array $profile, float $from, float $to): float
    {
        $values = self::zone($profile, $from, $to);

        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    private static function zoneMedian(array $profile, float $from, float $to): float
    {
        $values = self::zone($profile, $from, $to);

        if ($values === []) {
            return 0.0;
        }

        sort($values);

        return $values[intdiv(count($values), 2)];
    }

    /** @return array<int, float> */
    private static function zone(array $profile, float $from, float $to): array
    {
        $values = [];

        foreach ($profile as $level => $value) {
            $position = $level / (self::LEVELS - 1);

            if ($position >= $from - 1e-9 && $position <= $to + 1e-9) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private static function widestPosition(array $profile): float
    {
        $best = 0;

        foreach ($profile as $level => $value) {
            if ($value > $profile[$best]) {
                $best = $level;
            }
        }

        return $best / (self::LEVELS - 1);
    }

    /**
     * تشخیص دو شاخه شدن پایین شکل (پاچه شلوار).
     *
     * سطری «شکافته» است که دست‌کم دو بازه پارچه با فاصله محسوس بینشان داشته باشد.
     *
     * @return array{0: float, 1: float|null}
     */
    private static function splits(Silhouette $mask, array $bounds, float $widest): array
    {
        $minGap = max(1.0, 0.04 * $widest);
        $minRun = max(1.0, 0.03 * $widest);
        $checked = 0;
        $split = 0;
        $firstRun = 0;
        $start = null;
        $height = max(1, $bounds['height']);

        for ($y = $bounds['min_y'] + (int) round($height * 0.25); $y <= $bounds['max_y']; $y++) {
            $runs = array_values(array_filter(
                $mask->runs($y),
                fn ($run) => $run[1] - $run[0] + 1 >= $minRun,
            ));

            $checked++;
            $isSplit = false;

            if (count($runs) >= 2) {
                for ($i = 1; $i < count($runs); $i++) {
                    if ($runs[$i][0] - $runs[$i - 1][1] - 1 >= $minGap) {
                        $isSplit = true;

                        break;
                    }
                }
            }

            if ($isSplit) {
                $split++;
                $firstRun++;

                // سه سطر پشت‌سرهم یعنی شکاف واقعی است نه نویز
                if ($firstRun >= 3 && $start === null) {
                    $start = ($y - 2 - $bounds['min_y']) / $height;
                }
            } else {
                $firstRun = 0;
            }
        }

        $ratio = $checked > 0 ? $split / $checked : 0.0;

        // شکاف کم‌رمق معمولاً اثر سایه یا آستین است، نه پاچه؛ جای شروع را گزارش نمی‌کنیم
        return [$ratio, $ratio >= 0.15 ? $start : null];
    }

    /**
     * شکل لبه بالایی: گودی یقه، پهنای آن و «پُری» آن.
     *
     * پُری نسبت مساحت گودی به مستطیل دربرگیرنده‌اش است: هفتی نزدیک ۰٫۵،
     * گرد نزدیک ۰٫۸ و چهارگوش نزدیک ۱.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function neckline(Silhouette $mask, array $bounds): array
    {
        $height = max(1, $bounds['height']);
        $width = max(1, $bounds['width']);
        $tops = [];

        for ($x = $bounds['min_x']; $x <= $bounds['max_x']; $x++) {
            for ($y = $bounds['min_y']; $y <= $bounds['max_y']; $y++) {
                if ($mask->at($x, $y)) {
                    $tops[$x] = $y;

                    break;
                }
            }
        }

        if (count($tops) < 5) {
            return [0.0, 0.0, 0.0];
        }

        // خط سرشانه: صدک ۱۵ لبه بالایی. کمینه مطلق نمی‌گیریم چون یک قلاب چوب‌لباسی
        // یا نوک آستین می‌تواند از سرشانه بالاتر بیفتد و گودی یقه را الکی زیاد کند.
        $shoulderSamples = array_values($tops);
        sort($shoulderSamples);
        $shoulderY = $shoulderSamples[(int) floor(0.15 * (count($shoulderSamples) - 1))];

        // فقط ستون‌هایی که واقعاً روی «لبه بالایی» لباس‌اند (نه پهلوی یک دامن کلوش که
        // از بالا شیب تند دارد) نوار سرشانه را می‌سازند؛ یقه داخل همین نوار جست‌وجو می‌شود.
        $band = array_keys(array_filter($tops, fn ($y) => $y <= $shoulderY + 0.08 * $height));

        if (count($band) < 5) {
            return [0.0, 0.0, 0.0];
        }

        $left = min($band);
        $span = max($band) - $left;

        if ($span < 4) {
            return [0.0, 0.0, 0.0];
        }

        $centre = [];
        $bandWidth = 0;

        foreach ($tops as $x => $y) {
            if ($x < $left || $x > $left + $span) {
                continue;
            }

            $bandWidth++;
            $offset = ($x - $left) / $span;

            if ($offset > 0.30 && $offset < 0.70) {
                $centre[$x] = $y - $shoulderY;
            }
        }

        if ($centre === []) {
            return [0.0, 0.0, 0.0];
        }

        $deepest = max($centre);

        if ($deepest <= 0) {
            return [0.0, 0.0, 0.0];
        }

        $notch = array_filter($centre, fn ($depth) => $depth > 0.2 * $deepest);
        $notchWidth = count($notch) / max(1, $bandWidth);
        $fullness = $notch === [] ? 0.0 : array_sum($notch) / (count($notch) * $deepest);

        return [$deepest / $height, $notchWidth, $fullness];
    }

    /**
     * برجستگی آستین: بالای شکل نسبت به تنه چقدر پهن‌تر است و تا کجا ادامه دارد.
     *
     * @return array{0: float, 1: float}
     */
    private static function sleeves(array $profile, float $body): array
    {
        $bump = self::zoneMax($profile, 0.02, 0.32) / max(0.01, $body);
        $span = 0;
        $rows = 0;

        foreach ($profile as $level => $value) {
            $position = $level / (self::LEVELS - 1);

            if ($position > 0.45) {
                break;
            }

            $rows++;

            if ($value >= 1.12 * $body) {
                $span++;
            }
        }

        return [$bump, $rows > 0 ? $span / (self::LEVELS - 1) : 0.0];
    }
}
