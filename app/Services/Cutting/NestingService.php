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
 *
 * تطبیق راه‌راه و چهارخانه (بخش «تطبیق طرح» پایین همین کلاس) روی همین چیدمان سوار
 * می‌شود: از «دوخت مجازی» الگو خوانده می‌شود کدام لبه به کدام لبه می‌رود، روی هر
 * درز یک «نقطه تطبیق» (اول نشانه‌های جفت، بعد خط کمر/باسن/سینه، در نهایت سر لبه)
 * پیدا می‌شود و بعد برای هر قطعه یک «فاز» بر حسب تکرار طرح حل می‌شود تا آن نقطه‌ها
 * روی یک راه از طرح بیفتند. در پایان، فاصله واقعی هر درز اندازه‌گیری و گزارش
 * می‌شود؛ درزی که نشده است با اختلاف میلی‌متری‌اش گزارش می‌شود، نه پنهان.
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
     *     options: array<string, mixed>, pattern_match: array<string, mixed>
     * }
     */
    public function nest(Pattern $pattern, ?Fabric $fabric, array $options = []): array
    {
        $resolved = $this->resolveOptions($fabric, $options);
        $usable = $this->usableWidth($resolved);
        $instances = $this->instances($pattern, $resolved);
        $plan = $this->matchPlan($pattern, $instances, $resolved);

        $run = $this->pack($instances, $resolved, $usable, $plan);
        $match = $this->matchReport($plan, $instances, $resolved, $usable, $run);

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
            'warnings' => $this->warnings($pattern, $fabric, $resolved, $usable, $instances, $run, $match),
            'options' => $resolved,
            'pattern_match' => $match,
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
                    'pattern_match' => [
                        'active' => $result['pattern_match']['active'],
                        'matched' => $result['pattern_match']['matched'],
                        'total' => $result['pattern_match']['total'],
                        'extra_length_cm' => $result['pattern_match']['extra_length_cm'],
                    ],
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
        $repeat = max(0.0, (float) ($options['pattern_repeat_cm'] ?? $fabric?->pattern_repeat_cm ?? 0));
        $surface = (string) ($options['surface_pattern'] ?? $fabric?->surface_pattern ?? 'stripe');

        return [
            'fabric_width_cm' => max(20.0, (float) $width),
            'folded' => $this->boolOption($options, 'folded', true),
            'respect_nap' => $this->boolOption($options, 'respect_nap', $fabric?->isDirectional() ?? false),
            'match_stripes' => $this->boolOption($options, 'match_stripes', $fabric?->needsPatternMatching() ?? false),
            'gap_cm' => max(0.0, (float) ($options['gap_cm'] ?? 1.0)),
            'include_allowance' => $this->boolOption($options, 'include_allowance', true),
            'allow_rotation' => $this->boolOption($options, 'allow_rotation', true),
            'pattern_repeat_cm' => $repeat,
            // چهارخانه در عرض هم تکرار دارد؛ اگر جدا داده نشود همان تکرار طولی است
            'pattern_repeat_x_cm' => $surface === 'plaid'
                ? max(0.0, (float) ($options['pattern_repeat_x_cm'] ?? $repeat))
                : 0.0,
            'surface_pattern' => $surface,
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

    /**
     * تکرار طرح روی دو محور پارچه.
     *
     * راه‌راه در طول پارچه تکرار می‌شود (محور y) و چهارخانه در طول و عرض هر دو.
     *
     * @return array{y: float, x: float}
     */
    protected function patternRepeat(array $options): array
    {
        if (! ($options['match_stripes'] ?? false)) {
            return ['y' => 0.0, 'x' => 0.0];
        }

        return [
            'y' => max(0.0, (float) ($options['pattern_repeat_cm'] ?? 0)),
            'x' => max(0.0, (float) ($options['pattern_repeat_x_cm'] ?? 0)),
        ];
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
                    // فاصله لبه تای قطعه از گوشه کادرش: روی پارچه، این لبه دقیقاً روی
                    // تای پارچه می‌نشیند، هرچند کادر محافظه‌کارانه کمی جای دوخت هم دارد
                    'fold_x' => $onFold ? $allowance : 0.0,
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
     * @param  array<string, mixed>  $plan  نقشه تطبیق طرح (خروجی matchPlan)
     * @return array{placements: array<int, array<string, mixed>>, required_length_cm: float, unplaced: array<int, array<string, mixed>>, forced_rotation: array<int, string>, stripe_shift_cm: float, spots: array<int, array<string, mixed>>, free_x: array<int, string>}
     */
    protected function pack(array $instances, array $options, float $usable, array $plan): array
    {
        $boxes = [];
        $unplaced = [];
        $forced = [];

        foreach ($instances as $index => $instance) {
            $box = $this->box($instance, $options, $usable, (bool) ($plan['active'] ?? false));

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

        $winner = $this->search($boxes, $options, $usable, $plan);

        $placements = [];
        $shift = 0.0;
        $length = 0.0;
        $freeX = [];

        // رکوردها به ترتیب اصلی قطعه‌های الگو ساخته می‌شوند، نه به ترتیب چیدن
        foreach (array_keys($boxes) as $index) {
            $spot = $winner['spots'][$index] ?? null;

            if ($spot === null) {
                $unplaced[] = $instances[$index];

                continue;
            }

            $shift += (float) $spot['offset'];
            $length = max($length, (float) $spot['y'] + (float) $spot['orientation']['h']);

            if (! empty($spot['free_x'])) {
                $freeX[] = $instances[$index]['code'];
            }

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
            'spots' => $winner['spots'],
            'free_x' => array_values(array_unique($freeX)),
        ];
    }

    /**
     * داده سبک یک نمونه برای جست‌وجو: جهت‌هایی که در عرض مفید جا می‌شوند.
     *
     * خروجی null یعنی قطعه به هیچ روی در این عرض جا نمی‌شود.
     *
     * @return array{orientations: array<int, array{rotation: int, w: float, h: float}>, on_fold: bool, w: float, h: float, forced: bool}|null
     */
    protected function box(array $instance, array $options, float $usable, bool $matching = false): ?array
    {
        $fitting = array_values(array_filter(
            $this->orientations($instance, $options, $matching),
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
    protected function search(array $boxes, array $options, float $usable, array $plan): array
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
                $run = $this->layout($boxes, $sequence, $options, $usable, $plan, $mode);
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
                $plan,
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
        array $plan,
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

                    $trial = $this->layout($boxes, $candidate, $options, $usable, $plan, $mode);
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
        array $plan,
        string $mode,
    ): array {
        $gap = (float) $options['gap_cm'];
        $limit = $usable + $gap;
        $skyline = [['x' => 0.0, 'w' => $limit, 'y' => 0.0]];

        $repeat = (float) ($plan['repeat_y'] ?? 0.0);
        $repeatX = (float) ($plan['repeat_x'] ?? 0.0);

        $spots = [];
        $unplaced = [];
        $length = 0.0;

        foreach ($sequence as $index) {
            $box = $boxes[$index];
            $best = null;

            // فاز این قطعه بر حسب تکرار طرح؛ ۰ یعنی «روی خط طرح بنشیند»
            $residueY = (float) ($plan['residue_y'][$index] ?? 0.0);
            $residueX = $plan['residue_x'][$index] ?? null;
            $grid = ($repeatX > 0 && $residueX !== null && ! $box['on_fold'])
                ? ['repeat' => $repeatX, 'residue' => (float) $residueX]
                : null;

            foreach ($box['orientations'] as $orientation) {
                $footprint = (float) $orientation['w'] + $gap;
                $freeX = false;
                $spot = $this->findSpot($skyline, $footprint, $limit, $box['on_fold'], $mode, $grid);

                if ($spot === null && $grid !== null) {
                    // در عرض پارچه جایی با فاز درست نماند؛ قطعه چیده می‌شود و
                    // نشدن تطبیق عرضی در گزارش می‌آید
                    $spot = $this->findSpot($skyline, $footprint, $limit, $box['on_fold'], $mode);
                    $freeX = $spot !== null;
                }

                if ($spot === null) {
                    continue;
                }

                $y = (float) $spot['y'];
                $offset = 0.0;

                if ($repeat > 0) {
                    // تطبیق طرح: قطعه تا نزدیک‌ترین فاز خودش پایین می‌رود تا نقطه‌های
                    // تطبیق دو سر درز روی یک راه از طرح بیفتند
                    $snapped = $this->snapUp($y, $repeat, $residueY);
                    $offset = round($snapped - $y, 2);
                    $y = $snapped;
                }

                if ($best === null || $y < $best['y'] - 1e-9) {
                    $best = [
                        'x' => (float) $spot['x'],
                        'y' => $y,
                        'orientation' => $orientation,
                        'offset' => $offset,
                        'free_x' => $freeX,
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
    protected function orientations(array $instance, array $options, bool $matching = false): array
    {
        $w = (float) $instance['w'];
        $h = (float) $instance['h'];

        if ($matching) {
            // روی پارچه راه‌راه یا چهارخانه همه قطعه‌ها یک‌جهت بریده می‌شوند؛ چرخاندن
            // یا سر‌به‌پا کردن قطعه، طرح را در درزها به هم می‌ریزد
            return [['rotation' => 0, 'w' => $w, 'h' => $h]];
        }

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
        $matrix = $this->matrixFor($instance, $orientation, $x, $y);

        return 'matrix('.implode(' ', array_map(fn ($v) => $this->num($v, 4), $matrix)).')';
    }

    /**
     * همان ماتریس، به شکل عددی: [a, b, c, d, e, f].
     *
     * @return array<int, float>
     */
    protected function matrixFor(array $instance, array $orientation, float $x, float $y): array
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

        return $this->multiply([1, 0, 0, 1, $x, $y], $matrix);
    }

    /**
     * بردن یک نقطه از مختصات خود قطعه به مختصات پارچه.
     *
     * @return array{x: float, y: float}
     */
    protected function mapPoint(array $instance, array $orientation, float $x, float $y, array $point): array
    {
        [$a, $b, $c, $d, $e, $f] = $this->matrixFor($instance, $orientation, $x, $y);
        $px = (float) ($point['x'] ?? 0);
        $py = (float) ($point['y'] ?? 0);

        return [
            'x' => ($a * $px) + ($c * $py) + $e,
            'y' => ($b * $px) + ($d * $py) + $f,
        ];
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
        array $match = [],
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
            foreach ($this->matchWarnings($options, $run, $match) as $warning) {
                $warnings[] = $warning;
            }
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
        ?array $grid = null,
    ): ?array {
        $best = null;

        foreach ($skyline as $index => $node) {
            // قطعه روی تا فقط به لبه تا (x = ۰) می‌چسبد
            if ($atFold && $index > 0) {
                break;
            }

            if ($grid === null) {
                $x = (float) $node['x'];
                $y = $this->levelAt($skyline, $index, $footprint, $limit);
            } else {
                // چهارخانه: قطعه فقط روی فازهای مجاز عرض می‌نشیند
                $x = $this->snapUp((float) $node['x'], (float) $grid['repeat'], (float) $grid['residue']);
                $y = $this->levelFor($skyline, $x, $footprint, $limit);
            }

            if ($y === null) {
                continue;
            }

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

    /**
     * کوچک‌ترین مقداری که هم از $value کمتر نیست و هم روی فاز خواسته‌شده می‌نشیند.
     *
     * فاز یعنی «مانده تقسیم بر تکرار طرح»؛ با فاز صفر همان گرد کردن به بالا روی
     * تکرار طرح است.
     */
    protected function snapUp(float $value, float $repeat, float $residue = 0.0): float
    {
        if ($repeat <= 0) {
            return $value;
        }

        $residue = fmod(fmod($residue, $repeat) + $repeat, $repeat);
        $steps = ceil((($value - $residue) / $repeat) - 1e-9);

        return round($residue + ($steps * $repeat), 6);
    }

    /** ارتفاع اشغال‌شده در بازه‌ای از عرض که از هر جای دلخواه شروع می‌شود. */
    protected function levelFor(array $skyline, float $x, float $footprint, float $limit): ?float
    {
        if ($x < -1e-9 || $x + $footprint > $limit + 1e-9) {
            return null;
        }

        $end = $x + $footprint;
        $y = 0.0;

        foreach ($skyline as $node) {
            $from = max((float) $node['x'], $x);
            $to = min((float) $node['x'] + (float) $node['w'], $end);

            if ($to <= $from + 1e-9) {
                continue;
            }

            $y = max($y, (float) $node['y']);
        }

        return $y;
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

    // =================================================================
    // تطبیق راه‌راه و چهارخانه
    // =================================================================

    /** مبنایی که نقطه تطبیق هر درز از آن آمده است. */
    public const MATCH_BASIS = [
        'notch' => 'نشانه‌های جفت روی دو لبه',
        'marker' => 'خط راهنمای مشترک (کمر، باسن یا سینه)',
        'edge' => 'سر لبه‌های دوخته‌شده',
    ];

    /** کدام درز مهم‌تر است؛ وقتی همه درزها با هم جور نمی‌شوند، اول این‌ها می‌مانند. */
    protected const SEAM_PRIORITY = [
        'side' => 1,
        'waist' => 3,
        'default' => 4,
        'hem' => 5,
        'armhole' => 6,
        'shoulder' => 7,
        'neck' => 8,
    ];

    /** فاصله‌ای که در آن دو نقطه «روی یک راه از طرح» شمرده می‌شوند (سانتی‌متر). */
    protected const MATCH_TOLERANCE = 0.05;

    /**
     * نقشه تطبیق طرح: کدام درزها، با کدام نقطه، و هر قطعه روی کدام فاز بنشیند.
     *
     * @param  array<int, array<string, mixed>>  $instances
     * @return array<string, mixed>
     */
    protected function matchPlan(Pattern $pattern, array $instances, array $options): array
    {
        $repeat = $this->patternRepeat($options);

        $plan = [
            'active' => false,
            'repeat_y' => $repeat['y'],
            'repeat_x' => $repeat['x'],
            'seams' => [],
            'first' => [],
            'residue_y' => [],
            'residue_x' => [],
        ];

        if ($instances === [] || ($repeat['y'] <= 0 && $repeat['x'] <= 0)) {
            return $plan;
        }

        $plan['active'] = true;

        // برای هر قطعه، نمونه اول نماینده آن در گراف درزهاست
        $first = [];

        foreach ($instances as $index => $instance) {
            $first[(string) $instance['code']] ??= $index;
        }

        $plan['first'] = $first;

        $pieces = [];

        foreach ($pattern->pieces as $piece) {
            $pieces[(string) $piece->code] = $piece;
        }

        $seams = $this->matchSeams($pattern, $pieces, $first);
        $plan['seams'] = $seams;

        $order = array_keys($seams);
        usort($order, fn (int $a, int $b) => [$seams[$a]['priority'], $seams[$a]['order']]
            <=> [$seams[$b]['priority'], $seams[$b]['order']]);

        $codes = array_keys($first);

        $solved = [
            'y' => $this->solveAxis($seams, $order, $codes, $instances, $first, $repeat['y'], 'y', []),
            'x' => $this->solveAxis(
                $seams,
                $order,
                $codes,
                $instances,
                $first,
                $repeat['x'],
                'x',
                $this->centreAnchors($pieces, $instances, $first, $repeat['x']),
            ),
        ];

        foreach ($instances as $index => $instance) {
            $code = (string) $instance['code'];

            if ($repeat['y'] > 0) {
                $plan['residue_y'][$index] = (float) ($solved['y']['phase'][$code] ?? 0.0);
            }

            if ($repeat['x'] > 0) {
                $phase = (float) ($solved['x']['phase'][$code] ?? 0.0);

                // قطعه قرینه برعکس کشیده می‌شود، پس گوشه مرجعش سر دیگر کادر است
                $plan['residue_x'][$index] = $instance['mirrored']
                    ? $this->wrap($phase - (float) $instance['w'], $repeat['x'])
                    : $phase;
            }
        }

        foreach ($order as $index) {
            $plan['seams'][$index]['linked'] = [
                'y' => (bool) ($solved['y']['linked'][$index] ?? false),
                'x' => (bool) ($solved['x']['linked'][$index] ?? false),
            ];
        }

        return $plan;
    }

    /** نقشه‌ای که هیچ تطبیقی نمی‌خواهد؛ برای سنجش «بدون تطبیق چقدر می‌شد». */
    protected function idlePlan(): array
    {
        return [
            'active' => false,
            'repeat_y' => 0.0,
            'repeat_x' => 0.0,
            'seams' => [],
            'first' => [],
            'residue_y' => [],
            'residue_x' => [],
        ];
    }

    /**
     * درزهای الگو به‌همراه نقطه تطبیق دو سرشان.
     *
     * @param  array<string, PatternPiece>  $pieces
     * @param  array<string, int>  $first
     * @return array<int, array<string, mixed>>
     */
    protected function matchSeams(Pattern $pattern, array $pieces, array $first): array
    {
        $seams = [];

        foreach ($this->sewingRelations($pattern) as $order => $relation) {
            $fromCode = $relation['from_piece'];
            $toCode = $relation['to_piece'];

            if ($fromCode === $toCode || ! isset($first[$fromCode], $first[$toCode])) {
                continue;
            }

            $from = $pieces[$fromCode] ?? null;
            $to = $pieces[$toCode] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $points = $this->seamPoints($from, $relation['from_edge'], $to, $relation['to_edge']);

            if ($points === null) {
                continue;
            }

            $seams[] = [
                'order' => $order,
                'label' => $relation['label'],
                'from' => $fromCode,
                'to' => $toCode,
                'from_name' => (string) $from->name,
                'to_name' => (string) $to->name,
                'from_edge' => $relation['from_edge'],
                'to_edge' => $relation['to_edge'],
                'from_point' => $points['from'],
                'to_point' => $points['to'],
                'basis' => $points['basis'],
                'basis_label' => static::MATCH_BASIS[$points['basis']] ?? $points['basis'],
                'priority' => $this->seamPriority($from, $to, $relation['from_edge']),
            ];
        }

        return $seams;
    }

    /**
     * فهرست یکدست درزها از «دوخت مجازی» الگو.
     *
     * دو شکل نوشتاری در سامانه هست: تودرتو (from.piece / from.edge) و تخت
     * (from_piece / from_edge)؛ هر دو خوانده می‌شود. اگر الگو هنوز درزی ذخیره
     * نکرده باشد، پیشنهاد سازنده روابط دوخت به کار می‌رود.
     *
     * @return array<int, array{from_piece: string, from_edge: int, to_piece: string, to_edge: int, label: string}>
     */
    public function sewingRelations(Pattern $pattern): array
    {
        $raw = array_values(array_filter((array) ($pattern->sewing_relations ?? []), 'is_array'));

        if ($raw === []) {
            $builder = 'App\Services\Pattern\SewingRelationBuilder';

            if (class_exists($builder)) {
                try {
                    $raw = array_values(array_filter((array) $builder::suggest($pattern), 'is_array'));
                } catch (Throwable) {
                    $raw = [];
                }
            }
        }

        $out = [];

        foreach ($raw as $relation) {
            $from = $this->relationSide($relation, 'from');
            $to = $this->relationSide($relation, 'to');

            if ($from === null || $to === null) {
                continue;
            }

            $out[] = [
                'from_piece' => $from['piece'],
                'from_edge' => $from['edge'],
                'to_piece' => $to['piece'],
                'to_edge' => $to['edge'],
                'label' => (string) ($relation['label'] ?? $relation['title'] ?? 'درز'),
            ];
        }

        return $out;
    }

    /** یک سر رابطه دوخت، با پذیرش هر دو شکل نوشتاری. */
    protected function relationSide(array $relation, string $side): ?array
    {
        $piece = data_get($relation, $side.'.piece')
            ?? data_get($relation, $side.'_piece')
            ?? data_get($relation, $side.'.code')
            ?? (is_string($relation[$side] ?? null) ? $relation[$side] : null);

        $edge = data_get($relation, $side.'.edge') ?? data_get($relation, $side.'_edge');

        if (! is_string($piece) || $piece === '' || ! is_numeric($edge)) {
            return null;
        }

        return ['piece' => $piece, 'edge' => (int) $edge];
    }

    /**
     * نقطه تطبیق دو لبه‌ای که به هم دوخته می‌شوند.
     *
     * به ترتیب اعتماد: نشانه‌های هم‌نام روی دو لبه، بعد خط راهنمای مشترک (کمر،
     * باسن، سینه) که به دو لبه می‌رسد، و در نهایت سر لبه‌ها.
     *
     * @return array{from: array{x: float, y: float}, to: array{x: float, y: float}, basis: string}|null
     */
    protected function seamPoints(PatternPiece $from, int $fromEdge, PatternPiece $to, int $toEdge): ?array
    {
        $fromNotches = $this->edgeNotches($from, $fromEdge);
        $toNotches = $this->edgeNotches($to, $toEdge);

        $shared = array_values(array_intersect(array_keys($fromNotches), array_keys($toNotches)));
        sort($shared);

        if ($shared !== [] && $shared[0] !== '') {
            return [
                'from' => $fromNotches[$shared[0]],
                'to' => $toNotches[$shared[0]],
                'basis' => 'notch',
            ];
        }

        foreach (['waist', 'hip', 'bust'] as $key) {
            $a = $this->markerPointOnEdge($from, $fromEdge, $key);
            $b = $this->markerPointOnEdge($to, $toEdge, $key);

            if ($a !== null && $b !== null) {
                return ['from' => $a, 'to' => $b, 'basis' => 'marker'];
            }
        }

        $a = $this->edgeStart($from, $fromEdge);
        $b = $this->edgeStart($to, $toEdge);

        if ($a === null || $b === null) {
            return null;
        }

        return ['from' => $a, 'to' => $b, 'basis' => 'edge'];
    }

    /**
     * نشانه‌های یک لبه، کلید شده با نام جفتشان.
     *
     * @return array<string, array{x: float, y: float}>
     */
    protected function edgeNotches(PatternPiece $piece, int $edge): array
    {
        $out = [];

        foreach ((array) ($piece->notches ?? []) as $notch) {
            if (! is_array($notch) || (int) ($notch['edge'] ?? -1) !== $edge) {
                continue;
            }

            $key = (string) ($notch['pair'] ?? '');

            if ($key === '' || isset($out[$key])) {
                continue;
            }

            $out[$key] = ['x' => (float) ($notch['x'] ?? 0), 'y' => (float) ($notch['y'] ?? 0)];
        }

        ksort($out);

        return $out;
    }

    /**
     * سر خط راهنمای کمر/باسن/سینه، اگر روی این لبه بنشیند.
     *
     * @return array{x: float, y: float}|null
     */
    protected function markerPointOnEdge(PatternPiece $piece, int $edge, string $key): ?array
    {
        $segment = $this->edgeSegment($piece, $edge);

        if ($segment === null) {
            return null;
        }

        foreach ((array) ($piece->markers ?? []) as $marker) {
            if (! is_array($marker) || (string) ($marker['key'] ?? '') !== $key) {
                continue;
            }

            $best = null;

            foreach (['from', 'to'] as $end) {
                $point = $marker[$end] ?? null;

                if (! is_array($point) || ! isset($point['x'], $point['y'])) {
                    continue;
                }

                $candidate = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
                $distance = $this->pointToSegment($candidate, $segment[0], $segment[1]);

                if ($distance <= 1.5 && ($best === null || $distance < $best['distance'])) {
                    $best = ['distance' => $distance, 'point' => $candidate];
                }
            }

            if ($best !== null) {
                return $best['point'];
            }
        }

        return null;
    }

    /**
     * نقطه آغاز یک لبه در مختصات خود قطعه.
     *
     * @return array{x: float, y: float}|null
     */
    protected function edgeStart(PatternPiece $piece, int $edge): ?array
    {
        $segment = $this->edgeSegment($piece, $edge);

        return $segment === null ? null : $segment[0];
    }

    /**
     * دو سر یک لبه.
     *
     * @return array{0: array{x: float, y: float}, 1: array{x: float, y: float}}|null
     */
    protected function edgeSegment(PatternPiece $piece, int $edge): ?array
    {
        $points = $piece->points();
        $count = count($points);

        if ($count < 2 || $edge < 0 || $edge >= $count) {
            return null;
        }

        return [$points[$edge], $points[($edge + 1) % $count]];
    }

    /** فاصله یک نقطه تا یک پاره‌خط. */
    protected function pointToSegment(array $point, array $a, array $b): float
    {
        $dx = (float) $b['x'] - (float) $a['x'];
        $dy = (float) $b['y'] - (float) $a['y'];
        $length = ($dx * $dx) + ($dy * $dy);

        $t = $length < 1e-9
            ? 0.0
            : max(0.0, min(1.0, ((((float) $point['x'] - (float) $a['x']) * $dx)
                + (((float) $point['y'] - (float) $a['y']) * $dy)) / $length));

        return sqrt(
            ((((float) $a['x'] + ($t * $dx)) - (float) $point['x']) ** 2)
            + ((((float) $a['y'] + ($t * $dy)) - (float) $point['y']) ** 2)
        );
    }

    /** اهمیت یک درز برای تطبیق طرح. */
    protected function seamPriority(PatternPiece $from, PatternPiece $to, int $fromEdge): int
    {
        foreach ([$from, $to] as $piece) {
            if (str_contains((string) ($piece->meta['part'] ?? ''), 'pocket')) {
                return 2; // جیب روی تنه، آشکارترین جای ناهماهنگی طرح
            }
        }

        $tag = (string) (($from->meta['edges'] ?? [])[$fromEdge] ?? 'default');

        return static::SEAM_PRIORITY[$tag] ?? 4;
    }

    /**
     * قطعه‌های مرکز جلو و مرکز پشت روی خط طرح می‌نشینند تا دو نیمه لباس قرینه شود.
     *
     * مبدأ شبکه طرح، لبه تای پارچه (x = ۰) گرفته می‌شود؛ پس قطعه «روی تا» به خودی
     * خود روی خط طرح است و قطعه‌های مرکزی دیگر هم به همان خط بسته می‌شوند.
     *
     * @param  array<string, PatternPiece>  $pieces
     * @param  array<int, array<string, mixed>>  $instances
     * @param  array<string, int>  $first
     * @return array<string, float>
     */
    protected function centreAnchors(array $pieces, array $instances, array $first, float $repeat): array
    {
        $anchors = ['fixed' => [], 'preferred' => []];

        if ($repeat <= 0) {
            return $anchors;
        }

        foreach ($first as $code => $index) {
            $piece = $pieces[$code] ?? null;

            if ($piece === null) {
                continue;
            }

            $instance = $instances[$index];
            $mirror = $instance['mirrored'] ? (float) $instance['w'] : 0.0;

            if ($piece->on_fold) {
                // قطعه روی تا به لبه تا میخ شده است؛ همان لبه، مبدأ شبکه طرح است
                $anchors['fixed'][$code] = $this->wrap($mirror - (float) $instance['fold_x'], $repeat);

                continue;
            }

            foreach ((array) ($piece->markers ?? []) as $marker) {
                if (! is_array($marker) || ! in_array((string) ($marker['key'] ?? ''), ['cf', 'cb'], true)) {
                    continue;
                }

                $x = data_get($marker, 'from.x');

                if (is_numeric($x)) {
                    // خط مرکز جلو/پشت روی خط طرح بنشیند تا دو نیمه لباس قرینه شود
                    $distance = (float) $x - (float) $instance['origin_x'];
                    $anchors['preferred'][$code] = $this->wrap(
                        $instance['mirrored'] ? $distance : -$distance,
                        $repeat,
                    );
                }

                break;
            }
        }

        return $anchors;
    }

    /**
     * حل فاز قطعه‌ها روی یک محور با پویش گراف درزها.
     *
     * از مهم‌ترین درز شروع می‌شود، به همسایه‌ها فاز می‌دهد و همین‌طور جلو می‌رود
     * (BFS روی گراف درز). درزی که هر دو سرش از پیش فاز گرفته‌اند دیگر جای انتخاب
     * ندارد؛ اگر فازها با هم نخواند، آن درز تطبیق نمی‌شود و در گزارش می‌آید.
     *
     * @param  array<int, array<string, mixed>>  $seams
     * @param  array<int, int>  $order
     * @param  array<int, string>  $codes
     * @param  array<int, array<string, mixed>>  $instances
     * @param  array<string, int>  $first
     * @param  array<string, float>  $anchors
     * @return array{phase: array<string, float>, linked: array<int, bool>}
     */
    protected function solveAxis(
        array $seams,
        array $order,
        array $codes,
        array $instances,
        array $first,
        float $repeat,
        string $axis,
        array $anchors,
    ): array {
        if ($repeat <= 0) {
            return ['phase' => [], 'linked' => []];
        }

        $fixed = $anchors['fixed'] ?? [];
        $preferred = $anchors['preferred'] ?? [];

        // فاز هر قطعه یعنی «گوشه مرجع کادرش روی پارچه کجای طرح می‌افتد». قطعه قرینه
        // برعکس بریده می‌شود، پس در عرض، فاصله از گوشه مرجع علامت منفی می‌گیرد.
        $offset = function (string $code, array $point) use ($instances, $first, $axis): float {
            $instance = $instances[$first[$code]];
            $distance = (float) $point[$axis] - (float) $instance['origin_'.$axis];

            return ($axis === 'x' && $instance['mirrored']) ? -$distance : $distance;
        };

        $phase = [];

        foreach ($fixed as $code => $value) {
            if (isset($first[$code])) {
                $phase[$code] = $this->wrap((float) $value, $repeat);
            }
        }

        $linked = [];

        while (true) {
            $changed = false;

            foreach ($order as $index) {
                if (isset($linked[$index])) {
                    continue;
                }

                $seam = $seams[$index];
                [$a, $b] = [$seam['from'], $seam['to']];
                $ua = $offset($a, $seam['from_point']);
                $ub = $offset($b, $seam['to_point']);
                $hasA = isset($phase[$a]);
                $hasB = isset($phase[$b]);

                if ($hasA === $hasB) {
                    continue;
                }

                if ($hasA) {
                    $phase[$b] = $this->wrap($phase[$a] + $ua - $ub, $repeat);
                } else {
                    $phase[$a] = $this->wrap($phase[$b] + $ub - $ua, $repeat);
                }

                $linked[$index] = true;
                $changed = true;
            }

            if ($changed) {
                continue;
            }

            // شاخه تازه: مهم‌ترین درزی که هنوز هیچ سرش فاز ندارد ریشه می‌شود. ریشه
            // اگر خط مرکز داشته باشد روی خط طرح می‌نشیند، وگرنه روی فاز صفر
            $seeded = false;

            foreach ($order as $index) {
                $seam = $seams[$index];

                if (! isset($phase[$seam['from']]) && ! isset($phase[$seam['to']])) {
                    $root = isset($preferred[$seam['to']]) && ! isset($preferred[$seam['from']])
                        ? $seam['to']
                        : $seam['from'];

                    $phase[$root] = (float) ($preferred[$root] ?? 0.0);
                    $seeded = true;

                    break;
                }
            }

            if (! $seeded) {
                break;
            }
        }

        // قطعه‌ای که به هیچ درزی وصل نیست، خودش روی خط طرح می‌نشیند
        foreach ($codes as $code) {
            $phase[$code] ??= (float) ($preferred[$code] ?? 0.0);
        }

        return ['phase' => $phase, 'linked' => $linked];
    }

    /**
     * گزارش صادقانه تطبیق: هر درز چقدر جور شد و تطبیق چقدر پارچه برد.
     *
     * اندازه‌ها از روی چیدمان نهایی گرفته می‌شوند، نه از روی نیت الگوریتم؛ پس اگر
     * قطعه‌ای ناچار سر جای دیگری نشسته باشد، همان اختلاف واقعی گزارش می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $instances
     * @return array<string, mixed>
     */
    protected function matchReport(array $plan, array $instances, array $options, float $usable, array $run): array
    {
        $repeatY = (float) $plan['repeat_y'];
        $repeatX = (float) $plan['repeat_x'];

        $report = [
            'enabled' => (bool) ($options['match_stripes'] ?? false),
            'active' => (bool) $plan['active'],
            'surface' => (string) ($options['surface_pattern'] ?? 'stripe'),
            'repeat_cm' => round($repeatY, 2),
            'repeat_x_cm' => round($repeatX, 2),
            'axes' => array_values(array_filter([$repeatY > 0 ? 'y' : null, $repeatX > 0 ? 'x' : null])),
            'seams' => [],
            'matched' => 0,
            'unmatched' => 0,
            'total' => 0,
            'extra_length_cm' => 0.0,
            'baseline_length_cm' => round((float) $run['required_length_cm'], 1),
        ];

        if (! $plan['active']) {
            return $report;
        }

        foreach ($plan['seams'] as $seam) {
            $row = $this->measureSeam($seam, $plan, $instances, $run['spots'], $repeatY, $repeatX);
            $report['seams'][] = $row;
            $report[$row['matched'] ? 'matched' : 'unmatched']++;
        }

        $report['total'] = count($report['seams']);

        // هزینه پارچه‌ای تطبیق: همان قطعه‌ها، همان تنظیمات، بدون تطبیق
        $baseline = $this->pack($instances, $options, $usable, $this->idlePlan());
        $report['baseline_length_cm'] = round((float) $baseline['required_length_cm'], 1);
        $report['extra_length_cm'] = round(
            max(0.0, (float) $run['required_length_cm'] - (float) $baseline['required_length_cm']),
            1,
        );

        return $report;
    }

    /**
     * اندازه‌گیری یک درز روی چیدمان نهایی.
     *
     * @param  array<int, array<string, mixed>>  $instances
     * @param  array<int, array<string, mixed>>  $spots
     * @return array<string, mixed>
     */
    protected function measureSeam(
        array $seam,
        array $plan,
        array $instances,
        array $spots,
        float $repeatY,
        float $repeatX,
    ): array {
        $row = [
            'label' => $seam['label'],
            'from' => $seam['from'],
            'to' => $seam['to'],
            'from_name' => $seam['from_name'],
            'to_name' => $seam['to_name'],
            'basis' => $seam['basis'],
            'basis_label' => $seam['basis_label'],
            'matched' => false,
            'offset_mm' => ['y' => null, 'x' => null],
            'note' => '',
        ];

        $fromIndex = $plan['first'][$seam['from']] ?? null;
        $toIndex = $plan['first'][$seam['to']] ?? null;
        $fromSpot = $fromIndex === null ? null : ($spots[$fromIndex] ?? null);
        $toSpot = $toIndex === null ? null : ($spots[$toIndex] ?? null);

        if ($fromSpot === null || $toSpot === null) {
            $row['note'] = 'یکی از دو قطعه این درز در چیدمان جا نشد.';

            return $row;
        }

        $a = $this->mapPoint(
            $instances[$fromIndex],
            $fromSpot['orientation'],
            (float) $fromSpot['x'],
            (float) $fromSpot['y'],
            $seam['from_point'],
        );

        $b = $this->mapPoint(
            $instances[$toIndex],
            $toSpot['orientation'],
            (float) $toSpot['x'],
            (float) $toSpot['y'],
            $seam['to_point'],
        );

        $matched = true;

        if ($repeatY > 0) {
            $gap = $this->circular($a['y'] - $b['y'], $repeatY);
            $row['offset_mm']['y'] = round($gap * 10, 1);
            $matched = $matched && $gap <= static::MATCH_TOLERANCE;
        }

        if ($repeatX > 0) {
            // لبه تای قطعه «روی تا» دقیقاً روی تای پارچه می‌نشیند، نه روی گوشه کادرش
            $gap = $this->circular(
                ($a['x'] - (float) $instances[$fromIndex]['fold_x'])
                - ($b['x'] - (float) $instances[$toIndex]['fold_x']),
                $repeatX,
            );
            $row['offset_mm']['x'] = round($gap * 10, 1);
            $matched = $matched && $gap <= static::MATCH_TOLERANCE;
        }

        $row['matched'] = $matched;

        if (! $matched) {
            $row['note'] = ! ($seam['linked']['y'] ?? false) && ! ($seam['linked']['x'] ?? false)
                ? 'این درز حلقه‌ای در نقشه دوخت می‌بندد؛ با تطبیق درزهای مهم‌تر هم‌زمان نمی‌شود.'
                : 'قطعه برای جا شدن در عرض پارچه از فاز طرح بیرون آمد.';
        }

        return $row;
    }

    /**
     * هشدارهای فارسی تطبیق طرح؛ هم آنچه شد و هم آنچه نشد.
     *
     * @return array<int, string>
     */
    protected function matchWarnings(array $options, array $run, array $match): array
    {
        $repeat = $this->patternRepeat($options);

        if ($repeat['y'] <= 0 && $repeat['x'] <= 0) {
            return ['اندازه تکرار طرح این پارچه ثبت نشده است؛ برای تطبیق دقیق راه‌راه یا چهارخانه، «تکرار طرح» را در مشخصات پارچه وارد کنید.'];
        }

        $plaid = ($match['surface'] ?? '') === 'plaid' && $repeat['x'] > 0;

        $warnings = [sprintf(
            'برای اینکه راه‌راه/چهارخانه در درزها روی هم بیفتد، قطعه‌ها روی تکرار %s طرح%s و همه یک‌جهت چیده شدند؛ %s پارچه بیشتر از چیدمان بدون تطبیق لازم شد.',
            Format::cm($repeat['y'] ?: $repeat['x']),
            $plaid ? ' در طول و عرض' : ' در طول پارچه',
            Format::cm((float) ($match['extra_length_cm'] ?? $run['stripe_shift_cm'])),
        )];

        $total = (int) ($match['total'] ?? 0);

        if ($total > 0) {
            $warnings[] = sprintf(
                'از %s درز اصلی این الگو، %s درز روی طرح جور شد.',
                Jalali::digits((string) $total),
                Jalali::digits((string) (int) ($match['matched'] ?? 0)),
            );
        }

        $failed = array_values(array_filter(
            $match['seams'] ?? [],
            fn (array $seam) => ! $seam['matched'],
        ));

        foreach (array_slice($failed, 0, 4) as $seam) {
            $gaps = array_filter([
                $seam['offset_mm']['y'] ?? null ? 'در طول '.Jalali::digits($this->num($seam['offset_mm']['y'], 1)).' میلی‌متر' : null,
                $seam['offset_mm']['x'] ?? null ? 'در عرض '.Jalali::digits($this->num($seam['offset_mm']['x'], 1)).' میلی‌متر' : null,
            ]);

            $warnings[] = sprintf(
                'درز «%s» میان «%s» و «%s» جور نشد؛ %s اختلاف دارد%s',
                $seam['label'],
                $seam['from_name'],
                $seam['to_name'],
                $gaps === [] ? 'اختلاف' : implode(' و ', $gaps),
                $seam['note'] === '' ? '.' : '؛ '.$seam['note'],
            );
        }

        if (($match['unmatched'] ?? 0) > 4) {
            $warnings[] = 'درزهای جورنشده دیگری هم هست؛ فهرست کامل در جدول تطبیق طرح آمده است.';
        }

        return $warnings;
    }

    /** مانده مثبت یک عدد بر تکرار طرح. */
    protected function wrap(float $value, float $repeat): float
    {
        if ($repeat <= 0) {
            return 0.0;
        }

        $wrapped = fmod(fmod($value, $repeat) + $repeat, $repeat);

        return abs($wrapped - $repeat) < 1e-9 ? 0.0 : round($wrapped, 6);
    }

    /** کوتاه‌ترین فاصله دو فاز روی حلقه تکرار طرح. */
    protected function circular(float $delta, float $repeat): float
    {
        if ($repeat <= 0) {
            return 0.0;
        }

        $wrapped = $this->wrap($delta, $repeat);

        return round(min($wrapped, $repeat - $wrapped), 6);
    }
}
