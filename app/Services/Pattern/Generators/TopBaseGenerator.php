<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Services\Pattern\Transform\StyleLineCutter;

/**
 * پایه مشترک تاپ‌ها.
 *
 * تاپ چیزی نیست جز بالاتنه‌ای که خط بالایش عوض شده: جایی سرشانه و حلقه هست ولی
 * باریک (تانک)، جایی اصلاً نیست (بندو)، جایی بند جدا دارد (کمیزول) و جایی فقط
 * یک طرف بند دارد (یک‌شانه). برای همین این کلاس بلوک را نمی‌سازد؛ همان
 * bodyPanel آزموده‌شده را می‌گیرد و خط بالایش را عوض می‌کند.
 *
 * سه ابزار دارد:
 *
 *   cutTop()    خط بالای پنل را می‌بُرد و فقط تکهٔ پایین را نگه می‌دارد. چون از
 *               همان برندهٔ سبک‌ها استفاده می‌کند، برچسب لبه‌ها، ساسون‌ها و تای
 *               پارچه خودشان درست می‌مانند.
 *   strapPiece() بند جدا، با بلندی حساب‌شده از روی همان پنل.
 *   topLine()   شکل خط بالا: صاف، هفت، قلبی یا گرد.
 *
 * قاعده‌ای که همه‌جا رعایت شده: تاپ آستین ندارد، پس لبهٔ حلقه‌اش «لبهٔ تمام‌شده»
 * است نه درز. هر تاپی meta.sleeveless را می‌گذارد و در یادداشت می‌گوید لبه‌ها با
 * سجاف یا نوار تمام می‌شوند، وگرنه کاربر منتظر آستین می‌ماند.
 */
abstract class TopBaseGenerator extends BodiceBaseGenerator
{
    public static function group(): string
    {
        return 'top';
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای مشترک هر تاپ.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function topSchema(array $extra = [], float $length = 4): array
    {
        return array_merge(
            $this->fitParam(),
            $this->bodyLengthParam(
                default: $length,
                min: -22,
                max: 45,
                label: 'بلندی از خط کمر',
                hint: 'عدد منفی یعنی کراپ؛ منفی ۱۵ تقریباً بالای ناف می‌ایستد.',
            ),
            $extra,
            [
                'ease_extra' => [
                    'label' => 'آزادی بیشتر', 'min' => -2, 'max' => 8, 'step' => 0.5,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                    'hint' => 'روی «فرم لباس» سوار می‌شود؛ برای پارچهٔ ضخیم یا مدلی که باید کمی رهاتر بیفتد.',
                ],
                'finish' => [
                    'label' => 'تمام‌کردن لبه‌ها', 'type' => 'select', 'default' => 'binding',
                    'options' => [
                        'binding' => 'نوار اریب',
                        'facing' => 'سجاف',
                        'elastic' => 'کش داخل جای کش',
                        'none' => 'خودم تصمیم می‌گیرم',
                    ],
                    'hint' => 'تاپ آستین و یقه ندارد؛ لبه‌های باز باید با یکی از این‌ها تمام شوند.',
                ],
            ],
        );
    }

    /**
     * آزادیِ فرم، بعلاوهٔ «آزادی بیشتر».
     *
     * چند تاپِ کاتالوگ روی همین یک عدد از هم جدا می‌شوند: تاپِ چین‌دار و تاپِ
     * راپ هر دو روی درفتِ کراپ سوارند و تنها فرقشان نیم و یک سانتی‌متر آزادیِ
     * اضافه است، و کرست هم همین‌طور روی بوستیه.
     *
     * تا وقتی این پارامتر در فهرست نبود، آن سه کلاس عددشان را می‌فرستادند و
     * پارامترِ ناشناخته بی‌صدا دور ریخته می‌شد — یعنی «تاپ راپ» و «تاپ چین‌دار»
     * و «کراپ‌تاپ» هر سه یک الگوی یکسان بودند با سه نام. حالا عدد کار می‌کند.
     *
     * @param  array<string, float>  $map
     */
    protected function fitGrow(array $params, array $map = ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.5]): float
    {
        return parent::fitGrow($params, $map) + (float) $this->param($params, 'ease_extra', 0);
    }

    /**
     * بلندی تنه، محدودشده به تنِ همین آدم.
     *
     * «بلندی» در این کاتالوگ عددِ سانتی‌متری از خط کمر است و می‌تواند منفی باشد
     * (کراپ). ولی منفیِ ثابت روی هر بدنی یک معنا ندارد: منفی ۱۴ روی بدن بزرگسال
     * یعنی بالای ناف، و روی بدن کودک یعنی بالای زیر بغل — یعنی قطعه‌ای که اصلاً
     * قابل دوخت نیست. پس کف بلندی از خودِ اندازه‌ها می‌آید: دم لباس دست‌کم چند
     * سانتی‌متر پایین‌تر از خط زیر بغل می‌ایستد.
     *
     * @param  array<string, float>  $g
     */
    protected function bodyLength(array $params, array $g, float $default = 0, float $clearance = 5.0): float
    {
        $length = (float) $this->param($params, 'body_length', $default);
        $floor = ((float) ($g['bust_y'] ?? 0) + $clearance) - (float) ($g['side_waist_y'] ?? 0);

        return max($length, $floor);
    }

    /** پارامتر پهنای بند. */
    protected function strapParam(float $default = 1.5, string $label = 'پهنای بند'): array
    {
        return [
            'strap_width' => [
                'label' => $label, 'min' => 0.6, 'max' => 8, 'step' => 0.25,
                'default' => $default, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /** پارامتر جای خط بالای تاپ. */
    protected function topLineParam(float $default = 8, string $label = 'گودی خط بالا از زیر بغل'): array
    {
        return [
            'top_drop' => [
                'label' => $label, 'min' => -4, 'max' => 22, 'step' => 0.5,
                'default' => $default, 'unit' => 'سانتی‌متر',
                'hint' => 'از خط زیر بغل به بالا اندازه می‌شود؛ عدد بزرگ‌تر یعنی تاپ بازتر.',
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  بریدن خط بالا
     * ------------------------------------------------------------------- */

    /**
     * بریدن خط بالای پنل و نگه‌داشتن تکهٔ پایین.
     *
     * $spec:
     *   center  ارتفاع خط روی مرکز جلو/پشت (از بالای کادر پنل)
     *   side    ارتفاع خط روی درز پهلو
     *   shape   straight | v | sweetheart | scoop
     *   apex    (برای قلبی) نسبت جای برجستگی روی پهنا
     *
     * @param  array<string, mixed>  $panel
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function cutTop(array $panel, array $spec): array
    {
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($panel['outline']);

        $center = max($minY + 0.5, min($maxY - 4, (float) $spec['center']));
        $side = max($minY + 0.5, min($maxY - 4, (float) $spec['side']));

        $path = [['x' => $minX - 1.5, 'y' => $center]];

        // خط برش از مرکز به پهلو می‌رود و باید همان‌جا هم تمام شود. اگر نقطهٔ
        // میانی‌اش از گوشهٔ بالای پنل (نوک بند یا نوک سرشانه) بیرون بزند، مسیر
        // برش برمی‌گردد و لبهٔ بالای پنل را دو بار قطع می‌کند؛ قطعه‌ای که
        // درمی‌آید مسیرش خودش را قطع کرده و بریده نمی‌شود. سقف را از خودِ پنل
        // می‌گیریم، نه از عددی حدسی.
        $edge = $this->topCornerX($panel['outline']) - 0.5;

        /*
         * سقف و کفِ عمودی هم لازم است، نه فقط سقفِ افقی.
         *
         * برجستگیِ خطِ قلبی «کمی بالاتر از دو سرِ خط» ساخته می‌شود. روی پنلِ
         * بلند این بالاتر جایی وسطِ پنل است، ولی روی پنلِ کوتاه (تاپِ کراپ روی
         * تنِ کوچک) از لبهٔ بالای پنل هم می‌زند بیرون؛ آن‌وقت مسیرِ برش لبهٔ
         * بالا را دو بار قطع می‌کند و قطعه خودش را قطع می‌کند. همان تله‌ای که
         * پیش‌تر برای نقطهٔ کنترلِ خطِ گرد پیش آمده بود، این‌بار برای خودِ نقطه.
         */
        $roof = $minY + 0.5;

        /*
         * کفِ خطِ برش «لبهٔ پایینِ پنل» نیست، خیلی بالاترش است.
         *
         * خطِ گرد و خطِ قلبی هر دو میانِ راه فرو می‌روند. روی پنلِ بلند این
         * فرورفتگی بی‌خطر است، ولی روی پنلِ کوتاه — تاپِ کراپ روی تنِ کودک، که
         * پنلش دوازده سانتی‌متر می‌شود — همان فرورفتگی به لبهٔ پایین می‌رسد و از
         * آن رد می‌شود. پس مسیر حق دارد حداکثر یک‌سومِ فاصلهٔ تا لبهٔ پایین را
         * پایین برود، نه بیشتر.
         */
        $deepest = max($center, $side);
        $floor = min($maxY - 1.0, $deepest + (($maxY - $deepest) * 0.35));

        foreach ($this->topLine((string) ($spec['shape'] ?? 'straight'), $minX, $maxX, $center, $side, $spec) as $point) {
            $point['x'] = min((float) $point['x'], $edge);
            $point['y'] = max($roof, min($floor, (float) $point['y']));

            if (isset($point['cx'])) {
                $point['cx'] = min((float) $point['cx'], $edge);
                $point['cy'] = max($roof, min($floor, (float) ($point['cy'] ?? $point['y'])));
            }

            $path[] = $point;
        }

        $path[] = ['x' => $maxX + 1.5, 'y' => $side];

        try {
            $halves = StyleLineCutter::cut($panel, $path, [
                'tag' => 'default',
                'codes' => [($panel['code'] ?? 'top').'-off', $panel['code'] ?? 'top'],
                'names' => ['دورریز بالای تاپ', $panel['name'] ?? 'تاپ'],
                'notches' => false,
                'normalize' => false,
            ]);
        } catch (\InvalidArgumentException) {
            return $panel;
        }

        usort($halves, fn ($a, $b) => Geometry::centroid($a['outline'])['y'] <=> Geometry::centroid($b['outline'])['y']);
        $lower = ($spec['keep'] ?? 'lower') === 'upper' ? $halves[0] : $halves[count($halves) - 1];

        $lower['code'] = $panel['code'] ?? 'top';
        $lower['name'] = $panel['name'] ?? 'تاپ';
        $lower['meta']['top_cut'] = true;

        // نشانه‌ها و خط‌های راهنمایی که بالای برش بودند دیگر روی قطعه نیستند
        $lower = $this->dropStrayMarks($lower);

        return Geometry::normalizePiece($lower);
    }

    /**
     * نقطه‌های میانی خط بالا.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * ایکسِ بالاترین گوشهٔ پنل — دورترین جایی که خط برشِ بالا اجازه دارد برود.
     *
     * @param  array<int, array<string, mixed>>  $outline
     */
    protected function topCornerX(array $outline): float
    {
        $x = 0.0;
        $top = INF;

        foreach ($outline as $point) {
            if ((float) $point['y'] < $top) {
                $top = (float) $point['y'];
                $x = (float) $point['x'];
            }
        }

        return $x;
    }

    protected function topLine(string $shape, float $minX, float $maxX, float $center, float $side, array $spec): array
    {
        $width = max(1.0, $maxX - $minX);

        return match ($shape) {
            // قلبی: مرکز پایین می‌افتد، روی سینه بالا می‌آید و بعد به پهلو می‌رسد.
            //
            // نقطهٔ کنترل — درست مثل خطِ گرد — باید *میان* دو سرِ همین کمان
            // بماند. اگر روی خودِ ارتفاعِ مرکز بنشیند، کمان اول از نقطهٔ شروع
            // پایین‌تر می‌رود و بعد بالا می‌آید؛ روی پنلِ کوتاه همان فرورفتگی از
            // لبهٔ پایین رد می‌شود و مسیرِ برش خودش را قطع می‌کند.
            'sweetheart' => (function () use ($minX, $width, $center, $side, $spec) {
                $peak = min($center, $side) - max(1.0, abs($center - $side) * 0.35 + 2.0);

                return [[
                    'x' => $minX + ($width * (float) ($spec['apex'] ?? 0.55)),
                    'y' => $peak,
                    'curve' => true,
                    'cx' => $minX + ($width * 0.18),
                    'cy' => $center + (($peak - $center) * 0.25),
                ]];
            })(),
            // گرد: خط با یک کمان ملایم از مرکز به پهلو می‌رود.
            //
            // نقطهٔ کنترل باید میان دو سرِ همین کمان بماند، نه یک و نیم سانتی‌متر
            // پایین‌ترِ نقطهٔ شروع: روی لباسی که مرکزش *پایین‌تر* از پهلوست
            // (لباس خواب بندی)، آن یک و نیم سانتی‌متر کمان را زیر نقطهٔ شروع
            // می‌کشد و مسیرِ برش، لبهٔ مرکزِ جلو را دو بار قطع می‌کند.
            'scoop' => [[
                'x' => $minX + ($width * 0.6),
                'y' => ($center + $side) / 2,
                'curve' => true,
                'cx' => $minX + ($width * 0.2),
                'cy' => $center + (((($center + $side) / 2) - $center) * 0.25),
            ]],
            default => [],
        };
    }

    /**
     * پاک‌سازی نشانه‌ها پس از عوض شدن مسیر قطعه.
     *
     * دو کار جدا انجام می‌شود و هر دو لازم است:
     *
     *   ۱. هرچه بیرون قطعه افتاده حذف می‌شود (خط سینه‌ای که بالای برش بود).
     *   ۲. نشانه‌هایی که مانده‌اند شمارهٔ لبه‌شان دوباره پیدا می‌شود. با بریدن،
     *      شماره‌گذاری لبه‌ها عوض می‌شود و نشانه‌ای که «روی لبهٔ ۲» بود حالا
     *      ممکن است روی لبهٔ ۸ باشد؛ اگر درست نشود، در چاپ و در جفت‌کردن درزها
     *      به جای غلط اشاره می‌کند.
     */
    protected function dropStrayMarks(array $piece): array
    {
        $outline = array_values($piece['outline'] ?? []);
        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);

        $inside = fn (?array $point, float $slack = 0.05) => $point !== null
            && isset($point['x'], $point['y'])
            && $point['y'] >= $minY - $slack && $point['y'] <= $maxY + $slack
            && $point['x'] >= $minX - $slack && $point['x'] <= $maxX + $slack;

        $piece['markers'] = array_values(array_filter(
            $piece['markers'] ?? [],
            fn (array $marker) => $inside($marker['from'] ?? null) && $inside($marker['to'] ?? null),
        ));

        $piece['darts'] = array_values(array_filter(
            $piece['darts'] ?? [],
            fn (array $dart) => $inside($dart['apex'] ?? null, 0.6) || $inside($dart['center'] ?? null, 0.6),
        ));

        $notches = [];

        foreach ($piece['notches'] ?? [] as $notch) {
            if (! $inside($notch, 0.3)) {
                continue;
            }

            $found = $this->nearestEdgeTo($outline, $notch);

            // نشانه‌ای که دیگر روی هیچ لبه‌ای ننشسته، جای واقعی‌اش را از دست داده
            if ($found === null) {
                continue;
            }

            // نشانه دقیقاً روی همان لبه می‌نشیند، نه نزدیکش: نشانه‌ای که چند
            // میلی‌متر کنار لبه باشد هنگام چرت زدن روی پارچه گمراه‌کننده است
            [$edge, $point] = $found;
            $notch['edge'] = $edge;
            $notch['x'] = round($point['x'], 2);
            $notch['y'] = round($point['y'], 2);
            $notches[] = $notch;
        }

        $piece['notches'] = $notches;

        return $piece;
    }

    /**
     * نزدیک‌ترین لبه به یک نقطه، به‌همراه جای دقیقش روی همان لبه.
     *
     * اگر از هیچ لبه‌ای نزدیک‌تر از حد نبود null برمی‌گردد.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{0: int, 1: array{x: float, y: float}}|null
     */
    protected function nearestEdgeTo(array $outline, array $point, float $tolerance = 0.8): ?array
    {
        $count = count($outline);
        $target = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
        $best = null;
        $bestPoint = null;
        $bestDistance = INF;

        for ($edge = 0; $edge < $count; $edge++) {
            for ($step = 0; $step <= 24; $step++) {
                $on = Geometry::pointOnEdge($outline, $edge, $step / 24);
                $distance = Geometry::distance($on, $target);

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $edge;
                    $bestPoint = $on;
                }
            }
        }

        return $bestDistance <= $tolerance && $best !== null
            ? [$best, ['x' => (float) $bestPoint['x'], 'y' => (float) $bestPoint['y']]]
            : null;
    }

    /* ---------------------------------------------------------------------
     |  بند
     * ------------------------------------------------------------------- */

    /**
     * بند جدا، دولا بریده‌شده.
     *
     * بلندی بند از خودِ پنل خوانده می‌شود: فاصلهٔ خط بالای تاپ تا جای سرشانه، جلو
     * و پشت، به‌علاوهٔ چند سانتی‌متر برای دوخت و تنظیم. بند چیزی است که در پرو
     * کوتاه می‌شود، پس عمداً بلندتر بریده می‌شود و در یادداشت هم گفته شده.
     */
    protected function strapPiece(float $length, float $width, array $o = []): array
    {
        $piece = $this->bandPiece(
            $o['code'] ?? 'top-strap',
            $o['name'] ?? 'بند تاپ',
            max(6.0, $length),
            max(1.0, $width * 2),
            [
                'cut' => (int) ($o['cut'] ?? 2),
                'fold_line' => true,
                'part' => 'strap',
                'meta' => array_merge([
                    'strap' => true,
                    'finished_width' => round($width, 2),
                    'girth_role' => 'trim',
                ], $o['meta'] ?? []),
            ],
        );

        $piece['meta']['edges'] = ['strap', 'side', 'strap', 'side'];

        return $piece;
    }

    /**
     * بلندی بند از روی پنل جلو و پشت.
     *
     * @param  array<string, float>  $g
     */
    protected function strapLength(array $g, float $frontTopY, float $backTopY, float $extra = 5.0): float
    {
        $shoulder = (float) ($g['shoulder_drop'] ?? 4);

        return round(max(8.0, ($frontTopY - $shoulder) + ($backTopY - $shoulder) + $extra), 1);
    }

    /* ---------------------------------------------------------------------
     |  کمک‌های کوچک
     * ------------------------------------------------------------------- */

    /** باز کردن پنل روی تای پارچه به یک قطعهٔ کامل (برای تاپ نامتقارن). */
    protected function unfoldPanel(array $piece): array
    {
        if (empty($piece['on_fold'])) {
            return $piece;
        }

        $full = PieceOps::unfold($piece);
        $full['on_fold'] = false;
        $full['cut_quantity'] = 1;
        $full['mirror'] = false;
        $full['meta']['fold_edges'] = [];
        $full['meta']['unfolded'] = true;

        return Geometry::normalizePiece($full);
    }

    /** یادداشت همیشگی تاپ: لبه‌های باز باید تمام شوند. */
    protected function finishNote(array $params, array $edges = ['حلقه', 'خط بالا']): array
    {
        $finish = (string) $this->param($params, 'finish', 'binding');
        $what = implode(' و ', $edges);

        return match ($finish) {
            'facing' => ['type' => 'info', 'text' => $what.' با سجاف تمام می‌شود؛ سجاف را از همان الگو کپی بگیرید و لایی بزنید.'],
            'elastic' => ['type' => 'info', 'text' => $what.' با جای کش تمام می‌شود؛ برای هر متر کش حدود ۱۰ درصد کوتاه‌تر از اندازهٔ لبه ببرید.'],
            'none' => ['type' => 'warning', 'text' => $what.' هنوز تمام‌نشده است؛ در الگو جای دوختی برایشان حساب نشده.'],
            default => ['type' => 'info', 'text' => $what.' با نوار اریب تمام می‌شود؛ نوار اریب روی خط منحنی بهتر از سجاف می‌خوابد.'],
        };
    }

    /**
     * یادداشت‌ها روی قطعهٔ اول می‌نشینند تا در کارت فنی و دستور دوخت دیده شوند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, array{type: string, text: string}>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function noted(array $pieces, array $notes): array
    {
        $pieces = array_values($pieces);

        if ($pieces === []) {
            return $pieces;
        }

        $pieces[0]['meta']['notes'] = array_merge(
            $pieces[0]['meta']['notes'] ?? [],
            array_map(fn (array $note) => $note['text'], $notes),
        );

        // «بی‌آستین» یعنی این قطعه اصلاً حلقه ندارد (بندو، آفشولدر)، نه اینکه
        // آستین رویش نگذاشته‌ایم. تانک حلقه دارد و باید حلقه‌اش بررسی شود.
        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                continue;
            }

            $pieces[$index]['meta']['sleeveless'] = Geometry::edgesWithTag($piece, 'armhole') === [];
        }

        return $pieces;
    }

    /** مقدار آزادی مثبت/منفی بر پایه پارچه کشی. */
    protected function knitEase(array $ease, float $negative): array
    {
        return array_merge($ease, [
            'bust' => $this->ease($ease, 'bust', 0) - $negative,
            'waist' => $this->ease($ease, 'waist', 0) - $negative,
            'hip' => $this->ease($ease, 'hip', 0) - ($negative * 0.6),
        ]);
    }
}
