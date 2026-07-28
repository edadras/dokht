<?php

namespace App\Services\Cutting;

use App\Models\Fabric;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Support\Format;
use App\Support\Jalali;
use ReflectionMethod;
use Throwable;

/**
 * چیدمان قطعات الگو روی پارچه و محاسبه مصرف.
 *
 * پارچه با «طول» در جهت y و «عرض» در جهت x باز می‌شود. اگر پارچه از عرض تا شده
 * باشد، فقط نیمی از عرض قابل استفاده است و لبه تا در x = ۰ قرار می‌گیرد؛ پس قطعه‌های
 * «روی تا» همیشه به x = ۰ چسبیده‌اند.
 *
 * چیدمان با الگوریتم «خط آسمان» (skyline) انجام می‌شود: به جای ردیف‌های ساده،
 * ارتفاع اشغال‌شده در هر نقطه از عرض نگه داشته می‌شود تا قطعه‌های کوچک در گودی‌های
 * بین قطعه‌های بزرگ جا بیفتند و دورریز کمتر شود.
 *
 * روی این پایه، یک جست‌وجوی «بهترین از میان چند راهبرد» نشسته است: چند ترتیب
 * قطعی از قطعه‌ها (بلندی، عرض، مساحت، بلندترین ضلع، محیط، و «روی تا اول») در دو
 * معیار انتخاب جا (پایین‌ترین نقطه، یا کم‌ترین فضای مرده) چیده می‌شوند و بعد با
 * جابه‌جا کردن قطعه‌ها در صف بهبود موضعی می‌گیرند. کوتاه‌ترین طول پارچه برنده است.
 * هیچ‌جا تصادف در کار نیست، پس یک الگوی یکسان همیشه یک چیدمان یکسان می‌دهد.
 */
class NestingService
{
    /** عرض‌های رایج پارچه در بازار (سانتی‌متر). */
    public const COMMON_WIDTHS = [90, 110, 140, 150];

    /**
     * چیدمان کامل قطعه‌های یک الگو روی پارچه.
     *
     * @return array{
     *     placements: array<int, array<string, mixed>>,
     *     fabric_width_cm: float, usable_width_cm: float, required_length_cm: float,
     *     required_meters: float, used_area_cm2: float, waste_percent: float,
     *     efficiency: float, unplaced: array<int, string>, warnings: array<int, string>,
     *     options: array<string, mixed>
     * }
     */
    public function nest(Pattern $pattern, ?Fabric $fabric, array $options = []): array
    {
        $resolved = $this->resolveOptions($fabric, $options);
        $usable = $this->usableWidth($resolved);
        $instances = $this->instances($pattern, $resolved);

        $run = $this->pack($instances, $resolved, $usable, $this->patternRepeat($fabric, $resolved));

        $length = $run['required_length_cm'];
        $used = round(array_sum(array_column($run['placements'], 'area_cm2')), 1);
        $fabricArea = $usable * $length;
        $efficiency = $fabricArea > 0 ? min(100, round($used / $fabricArea * 100, 2)) : 0.0;

        return [
            'placements' => $run['placements'],
            'fabric_width_cm' => round($resolved['fabric_width_cm'], 1),
            'usable_width_cm' => round($usable, 1),
            'required_length_cm' => round($length, 1),
            'required_meters' => round($length / 100, 2),
            'used_area_cm2' => $used,
            'waste_percent' => round(max(0, 100 - $efficiency), 2),
            'efficiency' => $efficiency,
            'unplaced' => array_values(array_unique(array_column($run['unplaced'], 'code'))),
            'warnings' => $this->warnings($pattern, $fabric, $resolved, $usable, $instances, $run),
            'options' => $resolved,
        ];
    }

    /**
     * دو تا سه چیدمان جایگزین برای مقایسه مصرف.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alternatives(Pattern $pattern, ?Fabric $fabric, array $options = []): array
    {
        $base = $this->resolveOptions($fabric, $options);

        $variants = [
            [
                'key' => 'base',
                'label' => $base['folded'] ? 'پارچه تاشده (پیشنهاد ما)' : 'پارچه باز (پیشنهاد ما)',
                'description' => 'همان تنظیماتی که همین حالا انتخاب شده است.',
                'options' => $base,
            ],
            [
                'key' => $base['folded'] ? 'open' : 'folded',
                'label' => $base['folded'] ? 'پارچه باز (تمام عرض)' : 'پارچه تاشده از عرض',
                'description' => $base['folded']
                    ? 'پارچه تا نمی‌شود و تمام عرض برای چیدمان آزاد است؛ قطعه‌های قرینه جدا بریده می‌شوند.'
                    : 'پارچه از عرض تا می‌شود؛ قطعه‌های قرینه با یک بار برش دو تا در می‌آیند.',
                'options' => ['folded' => ! $base['folded']] + $base,
            ],
        ];

        if ($base['allow_rotation'] && ! $base['respect_nap']) {
            $variants[] = [
                'key' => 'no_rotation',
                'label' => 'بدون چرخش قطعه‌ها',
                'description' => 'همه قطعه‌ها در جهت راستای پارچه می‌مانند؛ امن‌تر ولی معمولاً پرمصرف‌تر.',
                'options' => ['allow_rotation' => false] + $base,
            ];
        } else {
            $variants[] = [
                'key' => 'rotation',
                'label' => 'با اجازه چرخش قطعه‌ها',
                'description' => 'قطعه‌های بدون محدودیت راستا می‌توانند ۹۰ درجه بچرخند تا جای کمتری بگیرند.',
                'options' => ['allow_rotation' => true, 'respect_nap' => false] + $base,
            ];
        }

        return collect($variants)
            ->map(function (array $variant) use ($pattern, $fabric) {
                $result = $this->nest($pattern, $fabric, $variant['options']);

                return [
                    'key' => $variant['key'],
                    'label' => $variant['label'],
                    'description' => $variant['description'],
                    'options' => $result['options'],
                    'fabric_width_cm' => $result['fabric_width_cm'],
                    'usable_width_cm' => $result['usable_width_cm'],
                    'required_length_cm' => $result['required_length_cm'],
                    'required_meters' => $result['required_meters'],
                    'waste_percent' => $result['waste_percent'],
                    'efficiency' => $result['efficiency'],
                    'placement_count' => count($result['placements']),
                    'warnings' => $result['warnings'],
                ];
            })
            ->all();
    }

    /**
     * مصرف پارچه در عرض‌های رایج بازار تا کاربر بداند کدام عرض به‌صرفه‌تر است.
     *
     * @param  array<int, int|float>  $widths
     * @return array<int, array<string, mixed>>
     */
    public function estimateForWidths(
        Pattern $pattern,
        array $widths = self::COMMON_WIDTHS,
        ?Fabric $fabric = null,
        array $options = [],
    ): array {
        $widths = array_values(array_filter(array_map('floatval', $widths ?: self::COMMON_WIDTHS)));

        return collect($widths)
            ->map(function (float $width) use ($pattern, $fabric, $options) {
                $result = $this->nest($pattern, $fabric, ['fabric_width_cm' => $width] + $options);

                return [
                    'width_cm' => $result['fabric_width_cm'],
                    'usable_width_cm' => $result['usable_width_cm'],
                    'required_length_cm' => $result['required_length_cm'],
                    'required_meters' => $result['required_meters'],
                    'waste_percent' => $result['waste_percent'],
                    'efficiency' => $result['efficiency'],
                    'fits' => $result['unplaced'] === [],
                    'cost' => $fabric?->price_per_meter
                        ? round($result['required_meters'] * $fabric->price_per_meter)
                        : null,
                ];
            })
            ->all();
    }

    /** گزینه‌های چیدمان با پیش‌فرض‌های هوشمند بر پایه مشخصات پارچه. */
    public function resolveOptions(?Fabric $fabric, array $options = []): array
    {
        $width = $options['fabric_width_cm'] ?? $fabric?->effectiveWidth() ?? 140;

        return [
            'fabric_width_cm' => max(20.0, (float) $width),
            'folded' => $this->boolOption($options, 'folded', true),
            'respect_nap' => $this->boolOption($options, 'respect_nap', $fabric?->isDirectional() ?? false),
            'match_stripes' => $this->boolOption($options, 'match_stripes', $fabric?->needsPatternMatching() ?? false),
            'gap_cm' => max(0.0, (float) ($options['gap_cm'] ?? 1.0)),
            'include_allowance' => $this->boolOption($options, 'include_allowance', true),
            'allow_rotation' => $this->boolOption($options, 'allow_rotation', true),
            'pattern_repeat_cm' => max(0.0, (float) ($options['pattern_repeat_cm'] ?? $fabric?->pattern_repeat_cm ?? 0)),
        ];
    }

    protected function boolOption(array $options, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $options) || $options[$key] === null || $options[$key] === '') {
            return $default;
        }

        return (bool) filter_var($options[$key], FILTER_VALIDATE_BOOLEAN);
    }

    /** عرض قابل استفاده: پارچه تاشده تنها نیمی از عرض را در اختیار می‌گذارد. */
    public function usableWidth(array $options): float
    {
        $width = (float) $options['fabric_width_cm'];

        return round(($options['folded'] ?? true) ? $width / 2 : $width, 2);
    }

    protected function patternRepeat(?Fabric $fabric, array $options): float
    {
        if (! ($options['match_stripes'] ?? false)) {
            return 0.0;
        }

        return max(0.0, (float) ($options['pattern_repeat_cm'] ?? $fabric?->pattern_repeat_cm ?? 0));
    }

    /**
     * هر قطعه به نمونه‌های واقعی برش باز می‌شود (تعداد برش، قرینه، روی تا).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function instances(Pattern $pattern, array $options): array
    {
        $out = [];

        foreach ($pattern->pieces as $piece) {
            $allowance = $options['include_allowance'] ? $this->allowanceFor($piece, $pattern) : 0.0;
            [$minX, $minY, $maxX, $maxY] = $this->pieceBounds($piece);

            $width = round(($maxX - $minX) + 2 * $allowance, 2);
            $height = round(($maxY - $minY) + 2 * $allowance, 2);

            if ($width <= 0 || $height <= 0) {
                continue;
            }

            $quantity = max(1, (int) $piece->cut_quantity);
            $onFold = (bool) $piece->on_fold;

            // روی پارچه تاشده، یک بار بریدن قطعه قرینه دو قطعه می‌دهد
            $pairs = $piece->mirror && ($options['folded'] ?? true) && ! $onFold;
            $count = $pairs ? (int) ceil($quantity / 2) : $quantity;

            for ($i = 1; $i <= $count; $i++) {
                $out[] = [
                    'piece' => $piece,
                    'piece_id' => $piece->id,
                    'code' => (string) $piece->code,
                    'name' => (string) $piece->name,
                    'layer' => (string) $piece->layer,
                    'cut_index' => $i,
                    'cut_total' => $count,
                    'cut_quantity' => $quantity,
                    'allowance_cm' => $allowance,
                    'origin_x' => round($minX - $allowance, 2),
                    'origin_y' => round($minY - $allowance, 2),
                    'w' => $width,
                    'h' => $height,
                    'area_cm2' => $piece->area(),
                    'on_fold' => $onFold,
                    'mirrored' => $pairs || ($piece->mirror && $i % 2 === 0),
                    'grain_locked' => ! empty($piece->grainline),
                ];
            }
        }

        // بزرگ‌ترها اول: مرتب‌سازی بر پایه بلندی و بعد مساحت
        usort($out, function (array $a, array $b) {
            return [$b['h'], $b['w'] * $b['h']] <=> [$a['h'], $a['w'] * $a['h']];
        });

        return $out;
    }

    /**
     * کادر دربرگیرنده قطعه، با در نظر گرفتن نقطه‌های راهنمای منحنی.
     *
     * منحنی درجه دو از بدنه محدب نقطه‌ها و نقطه راهنمای خود بیرون نمی‌زند، پس این
     * کادر کمی محافظه‌کارانه ولی مطمئن است و قطعه‌های گرد روی هم نمی‌افتند.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function pieceBounds(PatternPiece $piece): array
    {
        $xs = [];
        $ys = [];

        foreach ((array) ($piece->outline ?? []) as $point) {
            if (! is_array($point) || ! isset($point['x'], $point['y'])) {
                continue;
            }

            $xs[] = (float) $point['x'];
            $ys[] = (float) $point['y'];

            if (! empty($point['curve']) && isset($point['cx'], $point['cy'])) {
                $xs[] = (float) $point['cx'];
                $ys[] = (float) $point['cy'];
            }
        }

        if ($xs === []) {
            return array_map('floatval', $piece->bounds());
        }

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    /** جای دوخت مؤثر قطعه: بیشترین مقدار لبه‌ها یا پیش‌فرض الگو. */
    public function allowanceFor(PatternPiece $piece, Pattern $pattern): float
    {
        $edges = array_filter((array) ($piece->edge_allowances ?? []), 'is_numeric');
        $default = $pattern->seamAllowance();

        return round(max($edges ? max($edges) : 0.0, $default), 2);
    }

    /**
     * چیدمان نمونه‌ها: بهترین نتیجه از میان چند راهبرد قطعی انتخاب می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $instances
     * @return array{placements: array<int, array<string, mixed>>, required_length_cm: float, unplaced: array<int, array<string, mixed>>, forced_rotation: array<int, string>, stripe_shift_cm: float}
     */
    protected function pack(array $instances, array $options, float $usable, float $repeat = 0.0): array
    {
        $boxes = [];
        $unplaced = [];
        $forced = [];

        foreach ($instances as $index => $instance) {
            $box = $this->box($instance, $options, $usable);

            if ($box === null) {
                // قطعه در هیچ جهتی در عرض مفید جا نمی‌شود
                $unplaced[] = $instance;

                continue;
            }

            if ($box['forced']) {
                $forced[] = $instance['code'];
            }

            $boxes[$index] = $box;
        }

        $winner = $this->search($boxes, $options, $usable, $repeat);

        $placements = [];
        $shift = 0.0;
        $length = 0.0;

        // رکوردها به ترتیب اصلی قطعه‌های الگو ساخته می‌شوند، نه به ترتیب چیدن
        foreach (array_keys($boxes) as $index) {
            $spot = $winner['spots'][$index] ?? null;

            if ($spot === null) {
                $unplaced[] = $instances[$index];

                continue;
            }

            $shift += (float) $spot['offset'];
            $length = max($length, (float) $spot['y'] + (float) $spot['orientation']['h']);

            $placements[] = $this->placement(
                $instances[$index],
                $spot['orientation'],
                (float) $spot['x'],
                (float) $spot['y'],
                (float) $spot['offset'],
            );
        }

        return [
            'placements' => $placements,
            'required_length_cm' => round($length, 1),
            'unplaced' => $unplaced,
            'forced_rotation' => array_values(array_unique($forced)),
            'stripe_shift_cm' => round($shift, 1),
        ];
    }

    /**
     * داده سبک یک نمونه برای جست‌وجو: جهت‌هایی که در عرض مفید جا می‌شوند.
     *
     * خروجی null یعنی قطعه به هیچ روی در این عرض جا نمی‌شود.
     *
     * @return array{orientations: array<int, array{rotation: int, w: float, h: float}>, on_fold: bool, w: float, h: float, forced: bool}|null
     */
    protected function box(array $instance, array $options, float $usable): ?array
    {
        $fitting = array_values(array_filter(
            $this->orientations($instance, $options),
            fn (array $orientation) => $orientation['w'] <= $usable + 1e-9,
        ));

        $forced = false;

        if ($fitting === [] && ! $options['respect_nap'] && $instance['h'] <= $usable + 1e-9) {
            // قطعه فقط با چرخش جا می‌شود؛ می‌چرخانیم و هشدار می‌دهیم
            $fitting = [['rotation' => 90, 'w' => (float) $instance['h'], 'h' => (float) $instance['w']]];
            $forced = true;
        }

        if ($fitting === []) {
            return null;
        }

        return [
            'orientations' => $fitting,
            'on_fold' => (bool) $instance['on_fold'],
            'w' => (float) $instance['w'],
            'h' => (float) $instance['h'],
            'forced' => $forced,
        ];
    }

    /**
     * جست‌وجوی بهترین چیدمان: چند ترتیب شروع × دو معیار انتخاب جا، و بعد بهبود موضعی.
     *
     * برای اینکه صفحه‌های سنگین کند نشوند، شمار چیدمان‌های آزمایشی سقف دارد؛ سقف
     * فقط به تعداد قطعه‌ها بستگی دارد تا نتیجه قطعی بماند.
     *
     * @param  array<int, array<string, mixed>>  $boxes
     * @return array{length: float, spots: array<int, array<string, mixed>>, unplaced: array<int, int>}
     */
    protected function search(array $boxes, array $options, float $usable, float $repeat): array
    {
        if ($boxes === []) {
            return ['length' => 0.0, 'spots' => [], 'unplaced' => []];
        }

        $budget = (int) max(240, min(2400, intdiv(24000, max(10, count($boxes)))));
        $spent = 0;
        $best = null;
        $starts = [];

        foreach ($this->sequences($boxes) as $sequence) {
            foreach (['level', 'waste'] as $mode) {
                $run = $this->layout($boxes, $sequence, $options, $usable, $repeat, $mode);
                $spent++;
                $starts[] = ['sequence' => $sequence, 'mode' => $mode, 'run' => $run];

                if ($this->shorter($run, $best)) {
                    $best = $run;
                }
            }
        }

        foreach ($starts as $start) {
            if ($spent >= $budget) {
                break;
            }

            $run = $this->improve(
                $boxes,
                $start['sequence'],
                $start['run'],
                $options,
                $usable,
                $repeat,
                $start['mode'],
                $spent,
                $budget,
            );

            if ($this->shorter($run, $best)) {
                $best = $run;
            }
        }

        return $best ?? ['length' => 0.0, 'spots' => [], 'unplaced' => array_keys($boxes)];
    }

    /**
     * بهبود موضعی: هر قطعه را در همه جای‌های صف امتحان می‌کند و اگر پارچه کوتاه‌تر
     * شد، جابه‌جایی را می‌پذیرد. اولین بهبود پذیرفته و پویش از سر گرفته می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $boxes
     * @param  array<int, int>  $sequence
     * @param  array{length: float, spots: array<int, array<string, mixed>>, unplaced: array<int, int>}  $run
     * @return array{length: float, spots: array<int, array<string, mixed>>, unplaced: array<int, int>}
     */
    protected function improve(
        array $boxes,
        array $sequence,
        array $run,
        array $options,
        float $usable,
        float $repeat,
        string $mode,
        int &$spent,
        int $budget,
    ): array {
        $count = count($sequence);

        for ($round = 0; $round < $count && $spent < $budget; $round++) {
            $moved = false;

            for ($from = 0; $from < $count && ! $moved && $spent < $budget; $from++) {
                for ($to = 0; $to < $count && ! $moved && $spent < $budget; $to++) {
                    if ($from === $to) {
                        continue;
                    }

                    // قطعه از جای خودش برداشته و در جای دیگری از صف گذاشته می‌شود
                    $candidate = $sequence;
                    $picked = array_splice($candidate, $from, 1);
                    array_splice($candidate, $to, 0, $picked);

                    $trial = $this->layout($boxes, $candidate, $options, $usable, $repeat, $mode);
                    $spent++;

                    if ($this->shorter($trial, $run)) {
                        [$sequence, $run, $moved] = [$candidate, $trial, true];
                    }
                }
            }

            if (! $moved) {
                break;
            }
        }

        return $run;
    }

    /** آیا چیدمان تازه از چیدمان مرجع بهتر است؟ اول قطعه‌های جانشده، بعد طول پارچه. */
    protected function shorter(?array $run, ?array $reference): bool
    {
        if ($run === null) {
            return false;
        }

        if ($reference === null) {
            return true;
        }

        $left = count($run['unplaced']);
        $right = count($reference['unplaced']);

        if ($left !== $right) {
            return $left < $right;
        }

        return $run['length'] < $reference['length'] - 1e-9;
    }

    /**
     * ترتیب‌های شروع جست‌وجو؛ همه بر پایه اندازه قطعه‌ها و کاملاً قطعی.
     *
     * @param  array<int, array<string, mixed>>  $boxes
     * @return array<int, array<int, int>>
     */
    protected function sequences(array $boxes): array
    {
        $metrics = [
            'height' => fn (array $box) => [$box['h'], $box['w']],
            'width' => fn (array $box) => [$box['w'], $box['h']],
            'area' => fn (array $box) => [$box['w'] * $box['h'], $box['h']],
            'longest' => fn (array $box) => [max($box['w'], $box['h']), $box['w'] * $box['h']],
            'girth' => fn (array $box) => [$box['w'] + $box['h'], $box['h']],
        ];

        $sequences = [];

        foreach ($metrics as $metric) {
            $sequences[] = $this->sequenceBy($boxes, $metric);
        }

        // قطعه‌های «روی تا» اول: ستون لبه تا زود بسته می‌شود و بقیه عرض آزاد می‌ماند
        foreach (['height', 'width', 'area'] as $name) {
            $metric = $metrics[$name];
            $sequences[] = $this->sequenceBy(
                $boxes,
                fn (array $box) => array_merge([$box['on_fold'] ? 1 : 0], $metric($box)),
            );
        }

        return $sequences;
    }

    /**
     * مرتب‌سازی نزولی نمونه‌ها بر پایه یک سنجه؛ در تساوی، ترتیب اصلی حفظ می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $boxes
     * @return array<int, int>
     */
    protected function sequenceBy(array $boxes, callable $metric): array
    {
        $keys = array_keys($boxes);

        usort($keys, fn (int $a, int $b) => [$metric($boxes[$b]), -$b] <=> [$metric($boxes[$a]), -$a]);

        return $keys;
    }

    /**
     * یک بار چیدن قطعه‌ها به ترتیب داده‌شده روی خط آسمان.
     *
     * @param  array<int, array<string, mixed>>  $boxes
     * @param  array<int, int>  $sequence
     * @return array{length: float, spots: array<int, array<string, mixed>>, unplaced: array<int, int>}
     */
    protected function layout(
        array $boxes,
        array $sequence,
        array $options,
        float $usable,
        float $repeat,
        string $mode,
    ): array {
        $gap = (float) $options['gap_cm'];
        $limit = $usable + $gap;
        $skyline = [['x' => 0.0, 'w' => $limit, 'y' => 0.0]];

        $spots = [];
        $unplaced = [];
        $length = 0.0;

        foreach ($sequence as $index) {
            $box = $boxes[$index];
            $best = null;

            foreach ($box['orientations'] as $orientation) {
                $spot = $this->findSpot($skyline, (float) $orientation['w'] + $gap, $limit, $box['on_fold'], $mode);

                if ($spot === null) {
                    continue;
                }

                $y = (float) $spot['y'];
                $offset = 0.0;

                if ($repeat > 0) {
                    // تطبیق طرح: بالای هر قطعه روی تکرار طرح می‌نشیند تا راه‌راه در درزها یکی شود
                    $snapped = ceil(($y / $repeat) - 1e-9) * $repeat;
                    $offset = round($snapped - $y, 2);
                    $y = $snapped;
                }

                if ($best === null || $y < $best['y'] - 1e-9) {
                    $best = [
                        'x' => (float) $spot['x'],
                        'y' => $y,
                        'orientation' => $orientation,
                        'offset' => $offset,
                    ];
                }
            }

            if ($best === null) {
                $unplaced[] = $index;

                continue;
            }

            $spots[$index] = $best;

            $skyline = $this->insert(
                $skyline,
                (float) $best['x'],
                (float) $best['orientation']['w'] + $gap,
                (float) $best['y'] + (float) $best['orientation']['h'] + $gap,
            );

            $length = max($length, (float) $best['y'] + (float) $best['orientation']['h']);
        }

        return ['length' => $length, 'spots' => $spots, 'unplaced' => $unplaced];
    }

    /**
     * جهت‌های مجاز یک نمونه، به ترتیب اولویت.
     *
     * @return array<int, array{rotation: int, w: float, h: float}>
     */
    protected function orientations(array $instance, array $options): array
    {
        $w = (float) $instance['w'];
        $h = (float) $instance['h'];

        $rotations = [0];

        if (! $options['respect_nap'] && ! $instance['on_fold']) {
            // چیدمان سر‌به‌پا: نمونه‌های زوج برعکس می‌شوند تا فضای بین قطعه‌ها پر شود
            $rotations = $instance['cut_index'] % 2 === 0 ? [180, 0] : [0, 180];
        }

        if (
            $options['allow_rotation']
            && ! $options['respect_nap']
            && ! $instance['on_fold']
            && ! $instance['grain_locked']
        ) {
            $rotations[] = 90;
        }

        return collect($rotations)
            ->unique()
            ->map(fn (int $rotation) => [
                'rotation' => $rotation,
                'w' => $rotation === 90 ? $h : $w,
                'h' => $rotation === 90 ? $w : $h,
            ])
            ->values()
            ->all();
    }

    /** ساخت رکورد یک قطعه چیده‌شده. */
    protected function placement(array $instance, array $orientation, float $x, float $y, float $offset): array
    {
        /** @var PatternPiece $piece */
        $piece = $instance['piece'];

        return [
            'piece_id' => $instance['piece_id'],
            'code' => $instance['code'],
            'name' => $instance['name'],
            'layer' => $instance['layer'],
            'x' => round($x, 2),
            'y' => round($y, 2),
            'width' => round((float) $orientation['w'], 2),
            'height' => round((float) $orientation['h'], 2),
            'rotation' => (int) $orientation['rotation'],
            'mirrored' => (bool) $instance['mirrored'],
            'on_fold' => (bool) $instance['on_fold'],
            'cut_index' => $instance['cut_index'],
            'cut_total' => $instance['cut_total'],
            'allowance_cm' => $instance['allowance_cm'],
            'area_cm2' => round((float) $instance['area_cm2'], 2),
            'y_offset_cm' => round($offset, 2),
            'origin_x' => $instance['origin_x'],
            'origin_y' => $instance['origin_y'],
            'path' => $this->piecePath($piece, $instance['allowance_cm'] > 0, $instance['allowance_cm']),
            'sew_path' => $instance['allowance_cm'] > 0 ? $this->piecePath($piece, false) : null,
            'grainline' => $this->grainline($piece),
            'transform' => $this->transform($instance, $orientation, $x, $y),
        ];
    }

    /** ماتریس تبدیل مختصات قطعه به مختصات پارچه (چرخش، قرینه، جابه‌جایی). */
    protected function transform(array $instance, array $orientation, float $x, float $y): string
    {
        $w = (float) $instance['w'];
        $h = (float) $instance['h'];

        // بردن مبدأ قطعه به گوشه بالا-چپ کادرش
        $matrix = [1, 0, 0, 1, -$instance['origin_x'], -$instance['origin_y']];

        if ($instance['mirrored']) {
            $matrix = $this->multiply([-1, 0, 0, 1, $w, 0], $matrix);
        }

        $matrix = match ((int) $orientation['rotation']) {
            90 => $this->multiply([0, 1, -1, 0, $h, 0], $matrix),
            180 => $this->multiply([-1, 0, 0, -1, $w, $h], $matrix),
            default => $matrix,
        };

        $matrix = $this->multiply([1, 0, 0, 1, $x, $y], $matrix);

        return 'matrix('.implode(' ', array_map(fn ($v) => $this->num($v, 4), $matrix)).')';
    }

    /** ضرب دو ماتریس آفین [a,b,c,d,e,f]؛ نتیجه: اول n بعد m. */
    protected function multiply(array $m, array $n): array
    {
        [$a1, $b1, $c1, $d1, $e1, $f1] = $m;
        [$a2, $b2, $c2, $d2, $e2, $f2] = $n;

        return [
            $a1 * $a2 + $c1 * $b2,
            $b1 * $a2 + $d1 * $b2,
            $a1 * $c2 + $c1 * $d2,
            $b1 * $c2 + $d1 * $d2,
            $a1 * $e2 + $c1 * $f2 + $e1,
            $b1 * $e2 + $d1 * $f2 + $f1,
        ];
    }

    /** خط راستای پارچه در مختصات خود قطعه. */
    protected function grainline(PatternPiece $piece): ?array
    {
        $grain = $piece->grainline ?? null;

        if (! is_array($grain) || ! isset($grain['from'], $grain['to'])) {
            return null;
        }

        return [
            'x1' => round((float) ($grain['from']['x'] ?? 0), 2),
            'y1' => round((float) ($grain['from']['y'] ?? 0), 2),
            'x2' => round((float) ($grain['to']['x'] ?? 0), 2),
            'y2' => round((float) ($grain['to']['y'] ?? 0), 2),
        ];
    }

    /**
     * مسیر SVG یک قطعه؛ در صورت وجود، از رسام الگو استفاده می‌شود.
     */
    public function piecePath(PatternPiece $piece, bool $withAllowance, ?float $allowance = null): string
    {
        $allowance ??= $this->pieceAllowance($piece);
        $renderer = 'App\Services\Pattern\SvgRenderer';

        if (class_exists($renderer)) {
            try {
                $method = new ReflectionMethod($renderer, 'piecePath');
                $path = $method->invoke(
                    $method->isStatic() ? null : app($renderer),
                    $piece,
                    ['seam_allowance' => $withAllowance, 'default_allowance' => $allowance],
                );

                if (is_string($path) && trim($path) !== '') {
                    return $path;
                }
            } catch (Throwable) {
                // رسام در دسترس نبود؛ خودمان می‌کشیم
            }
        }

        $outline = (array) ($piece->outline ?? []);

        if ($withAllowance) {
            $outline = $this->offsetOutline($outline, $allowance) ?: $outline;
        }

        return $this->outlinePath($outline);
    }

    protected function pieceAllowance(PatternPiece $piece): float
    {
        $edges = array_filter((array) ($piece->edge_allowances ?? []), 'is_numeric');

        return round($edges ? (float) max($edges) : (float) ($piece->pattern?->seamAllowance() ?? 1.0), 2);
    }

    /** بزرگ‌کردن خط دور قطعه به اندازه جای دوخت (خط برش). */
    protected function offsetOutline(array $outline, float $allowance): array
    {
        if ($allowance <= 0) {
            return $outline;
        }

        $service = 'App\Services\Pattern\SeamAllowanceService';

        if (class_exists($service)) {
            try {
                $method = new ReflectionMethod($service, 'offsetOutline');
                $result = $method->invoke($method->isStatic() ? null : app($service), $outline, $allowance);

                if (is_array($result) && $result !== []) {
                    return $result;
                }
            } catch (Throwable) {
                // خودمان حساب می‌کنیم
            }
        }

        return $this->miterOffset($outline, $allowance);
    }

    /**
     * جابه‌جا کردن هر نقطه روی نیم‌ساز عمودهای دو لبه مجاور (اتصال میتر).
     *
     * برای چندضلعی‌های ساده الگو دقیق است و گوشه‌ها را تیز نگه می‌دارد.
     */
    protected function miterOffset(array $outline, float $allowance): array
    {
        $points = array_values(array_filter(
            $outline,
            fn ($point) => is_array($point) && isset($point['x'], $point['y']),
        ));

        $count = count($points);

        if ($count < 3) {
            return $outline;
        }

        // جهت چرخش چندضلعی تعیین می‌کند کدام عمود «بیرون» است
        $area = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];
            $area += ((float) $a['x'] * (float) $b['y']) - ((float) $b['x'] * (float) $a['y']);
        }

        $sign = $area >= 0 ? 1 : -1;
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $previous = $points[($i - 1 + $count) % $count];
            $current = $points[$i];
            $next = $points[($i + 1) % $count];

            [$n1x, $n1y] = $this->edgeNormal($previous, $current, $sign);
            [$n2x, $n2y] = $this->edgeNormal($current, $next, $sign);

            $bx = $n1x + $n2x;
            $by = $n1y + $n2y;
            $length = sqrt(($bx * $bx) + ($by * $by));

            if ($length < 1e-6) {
                [$bx, $by, $length] = [$n1x, $n1y, 1.0];
            }

            $bx /= max($length, 1e-9);
            $by /= max($length, 1e-9);

            // حد میتر تا گوشه‌های تیز بی‌اندازه بلند نشوند
            $cos = max(0.35, ($bx * $n1x) + ($by * $n1y));
            $shift = $allowance / $cos;

            $point = [
                'x' => round((float) $current['x'] + $bx * $shift, 3),
                'y' => round((float) $current['y'] + $by * $shift, 3),
            ];

            if (! empty($current['curve']) && isset($current['cx'], $current['cy'])) {
                $point['curve'] = true;
                $point['cx'] = round((float) $current['cx'] + $bx * $shift, 3);
                $point['cy'] = round((float) $current['cy'] + $by * $shift, 3);
            }

            $out[] = $point;
        }

        return $out;
    }

    /** عمود بیرونی یک لبه. */
    protected function edgeNormal(array $from, array $to, int $sign): array
    {
        $dx = (float) $to['x'] - (float) $from['x'];
        $dy = (float) $to['y'] - (float) $from['y'];
        $length = sqrt(($dx * $dx) + ($dy * $dy));

        if ($length < 1e-9) {
            return [0.0, 0.0];
        }

        return [$sign * ($dy / $length), $sign * (-$dx / $length)];
    }

    /** ساخت مسیر بسته از نقاط خط دور؛ نقطه با curve به‌صورت منحنی درجه دو کشیده می‌شود. */
    public function outlinePath(array $outline): string
    {
        $points = array_values(array_filter($outline, 'is_array'));

        if (count($points) < 2) {
            return '';
        }

        $path = 'M '.$this->num($points[0]['x'] ?? 0).' '.$this->num($points[0]['y'] ?? 0);

        foreach (array_slice($points, 1) as $point) {
            $x = $this->num($point['x'] ?? 0);
            $y = $this->num($point['y'] ?? 0);

            $path .= ! empty($point['curve']) && isset($point['cx'], $point['cy'])
                ? ' Q '.$this->num($point['cx']).' '.$this->num($point['cy']).' '.$x.' '.$y
                : ' L '.$x.' '.$y;
        }

        return $path.' Z';
    }

    protected function num(mixed $value, int $decimals = 2): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }

    /**
     * هشدارهای فارسی چیدمان.
     *
     * @return array<int, string>
     */
    protected function warnings(
        Pattern $pattern,
        ?Fabric $fabric,
        array $options,
        float $usable,
        array $instances,
        array $run,
    ): array {
        $warnings = [];

        if ($instances === []) {
            return ['این الگو هیچ قطعه‌ای ندارد؛ اول قطعه‌های الگو را بسازید.'];
        }

        $widest = collect($instances)->sortByDesc('w')->first();

        if ($widest && $widest['w'] > $usable + 1e-9) {
            $warnings[] = sprintf(
                'عرض مفید پارچه %s است، ولی قطعه «%s» به عرض %s جا نمی‌شود؛ پارچه پهن‌تر بگیرید یا تای پارچه را باز کنید.',
                Format::cm($usable),
                $widest['name'],
                Format::cm($widest['w']),
            );
        }

        foreach ($run['unplaced'] as $instance) {
            $warnings[] = sprintf(
                'قطعه «%s» با اندازه %s×%s سانتی‌متر در این پارچه جا نشد و در چیدمان آورده نشده است.',
                $instance['name'],
                Jalali::digits($this->num($instance['w'])),
                Jalali::digits($this->num($instance['h'])),
            );
        }

        foreach ($run['forced_rotation'] as $code) {
            $name = collect($instances)->firstWhere('code', $code)['name'] ?? $code;
            $warnings[] = sprintf(
                'قطعه «%s» برای جا شدن در عرض پارچه ۹۰ درجه چرخانده شد؛ راستای پارچه را روی قطعه دوباره بررسی کنید.',
                $name,
            );
        }

        if ($options['respect_nap']) {
            $warnings[] = 'این پارچه خواب‌دار یا طرح‌دار جهت‌دار است؛ همه قطعه‌ها یک‌جهت چیده شدند، پس مصرف پارچه بیشتر از حالت معمول است.';
        }

        if ($options['match_stripes']) {
            $repeat = $this->patternRepeat($fabric, $options);

            $warnings[] = $repeat > 0
                ? sprintf(
                    'برای اینکه راه‌راه/چهارخانه در درزها روی هم بیفتد، قطعه‌ها روی تکرار %s طرح تنظیم شدند؛ حدود %s پارچه بیشتر لازم است.',
                    Format::cm($repeat),
                    Format::cm($run['stripe_shift_cm']),
                )
                : 'اندازه تکرار طرح این پارچه ثبت نشده است؛ برای تطبیق دقیق راه‌راه یا چهارخانه، «تکرار طرح» را در مشخصات پارچه وارد کنید.';
        }

        if (! $options['include_allowance']) {
            $warnings[] = 'جای دوخت در این چیدمان حساب نشده است؛ هنگام برش خودتان جای دوخت را اضافه کنید.';
        }

        $meters = $run['required_length_cm'] / 100;

        if ($fabric && $fabric->stock_meters !== null && $fabric->stock_meters > 0 && $fabric->stock_meters < $meters) {
            $warnings[] = sprintf(
                'موجودی این پارچه %s متر است، ولی برای این کار %s متر لازم دارید.',
                Jalali::digits($this->num($fabric->stock_meters)),
                Jalali::digits($this->num($meters)),
            );
        }

        $shrinkage = (float) ($fabric?->profile()->get('shrinkage') ?? 0);

        if ($shrinkage >= 3) {
            $warnings[] = sprintf(
                'این پارچه حدود %s آب می‌رود؛ حدود %s پارچه بیشتر بگیرید یا پیش از برش پارچه را بشویید.',
                Format::percent($shrinkage),
                Format::cm(round($run['required_length_cm'] * $shrinkage / 100, 1)),
            );
        }

        return array_values(array_unique($warnings));
    }

    /**
     * پیدا کردن بهترین جای یک قطعه روی خط آسمان.
     *
     * در معیار «level» پایین‌ترین نقطه برنده است و فضای مرده تنها داور تساوی است؛
     * در معیار «waste» اول کم‌ترین فضای مرده زیر قطعه اهمیت دارد، یعنی قطعه ترجیح
     * می‌دهد در گودی کنار یک قطعه بلندتر بیفتد تا اینکه ردیف تازه‌ای باز کند.
     */
    protected function findSpot(
        array $skyline,
        float $footprint,
        float $limit,
        bool $atFold,
        string $mode = 'level',
    ): ?array {
        $best = null;

        foreach ($skyline as $index => $node) {
            // قطعه روی تا فقط به لبه تا (x = ۰) می‌چسبد
            if ($atFold && $index > 0) {
                break;
            }

            $y = $this->levelAt($skyline, $index, $footprint, $limit);

            if ($y === null) {
                continue;
            }

            $x = (float) $node['x'];
            $dead = $this->deadSpace($skyline, $x, $footprint, $y);

            $rank = $mode === 'waste'
                ? [round($dead, 4), round($y, 4), $x]
                : [round($y, 4), round($dead, 4), $x];

            if ($best === null || $rank < $best['rank']) {
                $best = ['rank' => $rank, 'x' => $x, 'y' => $y];
            }
        }

        return $best;
    }

    /** فضای مرده‌ای که با نشستن قطعه در این جا، زیر آن بی‌استفاده می‌ماند. */
    protected function deadSpace(array $skyline, float $x, float $footprint, float $level): float
    {
        $end = $x + $footprint;
        $dead = 0.0;

        foreach ($skyline as $node) {
            $from = max((float) $node['x'], $x);
            $to = min((float) $node['x'] + (float) $node['w'], $end);

            if ($to <= $from + 1e-9) {
                continue;
            }

            $dead += ($level - (float) $node['y']) * ($to - $from);
        }

        return $dead;
    }

    /** ارتفاع اشغال‌شده در بازه‌ای از عرض، از گره داده‌شده به بعد. */
    protected function levelAt(array $skyline, int $index, float $footprint, float $limit): ?float
    {
        if (! isset($skyline[$index])) {
            return null;
        }

        if ($skyline[$index]['x'] + $footprint > $limit + 1e-9) {
            return null;
        }

        $remaining = $footprint;
        $y = 0.0;

        for ($i = $index; $remaining > 1e-9; $i++) {
            if (! isset($skyline[$i])) {
                return null;
            }

            $y = max($y, (float) $skyline[$i]['y']);
            $remaining -= (float) $skyline[$i]['w'];
        }

        return $y;
    }

    /** بالا آوردن خط آسمان در بازه قطعه تازه چیده‌شده. */
    protected function insert(array $skyline, float $x, float $width, float $top): array
    {
        $end = $x + $width;
        $out = [];

        foreach ($skyline as $node) {
            $nodeEnd = $node['x'] + $node['w'];

            if ($nodeEnd <= $x + 1e-9 || $node['x'] >= $end - 1e-9) {
                $out[] = $node;

                continue;
            }

            if ($node['x'] < $x - 1e-9) {
                $out[] = ['x' => (float) $node['x'], 'w' => $x - $node['x'], 'y' => (float) $node['y']];
            }

            if ($nodeEnd > $end + 1e-9) {
                $out[] = ['x' => $end, 'w' => $nodeEnd - $end, 'y' => (float) $node['y']];
            }
        }

        $out[] = ['x' => $x, 'w' => $width, 'y' => $top];

        usort($out, fn (array $a, array $b) => $a['x'] <=> $b['x']);

        $merged = [];

        foreach ($out as $node) {
            $last = count($merged) - 1;

            if ($last >= 0 && abs($merged[$last]['y'] - $node['y']) < 1e-9) {
                $merged[$last]['w'] += $node['w'];

                continue;
            }

            $merged[] = $node;
        }

        return array_values($merged);
    }
}
