<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Neckline\NeckGeometry;
use App\Services\Pattern\Transform\PieceOps;

/**
 * هندسه درفت یقه.
 *
 * یقه روی کاغذ یک «نوار کمانی» است: هر یقه‌ای که به گردن بخوابد یا بایستد، تکه‌ای
 * از یک حلقه (آنالوس) است و همه رفتارش از یک عدد درمی‌آید — شعاع آن کمان.
 *
 *   شعاع کوچک ⇒ کمان تندتر ⇒ اختلاف طول دو لبه بیشتر.
 *   شعاع بزرگ ⇒ نوار تقریباً راست ⇒ دو لبه هم‌اندازه.
 *
 * دو حالت داریم و تفاوتشان فقط این است که لبه یقه روی کمان بیرونی می‌نشیند یا
 * درونی:
 *
 *   stand — لبه یقه روی کمان بیرونی است، پس لبه بالا کوتاه‌تر درمی‌آید و یقه
 *           مثل یک مخروط سربسته دور گردن می‌ایستد (یقه آخوندی، پایه پیراهن).
 *   fall  — لبه یقه روی کمان درونی است، پس لبه بیرونی بلندتر درمی‌آید و یقه روی
 *           شانه می‌خوابد (پیتر‌پن، ملوانی، رویه یقه پیراهن، شال).
 *
 * دو راه برای گرفتن شعاع هست و هر دو زبان خیاط‌اند:
 *
 *   riseRadius()  — «سر جلوی یقه چند سانت بالا بیاید»؛ همان چیزی که در کتاب‌های
 *                   الگو برای پایه یقه پیراهن نوشته می‌شود.
 *   arcRadiusFor() — «لبه روبه‌رو چند سانت بلندتر یا کوتاه‌تر از لبه یقه شود»؛
 *                   همان «گشادی لبه بیرونی» که خواب یقه را می‌سازد.
 *
 * اندازه‌گیری و جورکردن طول هم این‌جاست: هر یقه پس از درفت، لبه یقه‌اش با
 * PieceOps روی خط یقه اندازه‌گیری‌شده «پیاده» و تا زیر یک‌دهم سانتی‌متر راست می‌شود.
 */
trait CollarGeometry
{
    use NeckGeometry;

    /** بیش از این اختلاف با خط یقه پذیرفته نیست. */
    protected const NECK_TOLERANCE = 0.1;

    /* ---------------------------------------------------------------------
     |  شعاع کمان یقه
     * ------------------------------------------------------------------- */

    /**
     * شعاعی که لبه روبه‌روی یقه را دقیقاً $delta بلندتر (یا کوتاه‌تر) می‌کند.
     *
     * برای کمانی به شعاع R و پهنای w داریم: طول لبه دیگر = طول لبه یقه × (R±w)/R،
     * پس اختلاف دو لبه w×S/R است و R = w×S/Δ.
     */
    protected function arcRadiusFor(float $arcLength, float $width, float $delta): float
    {
        $delta = abs($delta);
        $width = max(0.2, $width);

        if ($delta < 0.05) {
            return $arcLength * 200; // عملاً یک نوار راست
        }

        return max($width + 1.0, ($width * $arcLength) / $delta);
    }

    /**
     * شعاعی که سر دور کمان را $rise سانتی‌متر از سر نزدیکش بالا می‌برد.
     *
     * رابطه بسته ندارد (rise = R(1−cos(S/R)))، پس با تقسیم دودویی حل می‌شود؛
     * بیشترین بالا آمدن ممکن وقتی است که کمان نیم‌دایره شود.
     */
    protected function riseRadius(float $arcLength, float $rise): float
    {
        $arcLength = max(1.0, $arcLength);
        $limit = (2 * $arcLength) / M_PI;
        $rise = min($rise, $limit * 0.92);

        if ($rise < 0.05) {
            return $arcLength * 200;
        }

        $low = $arcLength / M_PI;   // تندترین کمان، بیشترین بالا آمدن
        $high = $arcLength * 500;   // تقریباً راست، کمترین بالا آمدن

        for ($i = 0; $i < 60; $i++) {
            $mid = ($low + $high) / 2;

            if ($mid * (1 - cos($arcLength / $mid)) > $rise) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * شعاع یقه خوابیده از روی بلندی پایه.
     *
     * گردن یک استوانه به شعاع r = دور یقه ÷ ۲π است. یقه‌ای که s سانت پایه دارد و
     * کل پهنایش w است، لبه بیرونی‌اش روی شعاع r + (w − s) می‌افتد، پس روی الگو
     * باید R = w×r/(w − s) باشد. با پایه صفر، یقه دقیقاً روی حلقه گردن می‌خوابد و
     * با پایه برابر پهنا، به نوار راست می‌رسد.
     */
    protected function standRadius(float $fullNeckline, float $width, float $stand): float
    {
        $r = max(1.0, $fullNeckline) / (2 * M_PI);
        $fall = max(0.3, $width - $stand);

        return max($width + 1.0, ($width * $r) / $fall);
    }

    /* ---------------------------------------------------------------------
     |  ساخت نوار کمانی
     * ------------------------------------------------------------------- */

    /**
     * بدنه یقه: کمان لبه یقه از مرکز پشت تا مرکز جلو و کمان لبه روبه‌رو برگشت.
     *
     * خروجی همیشه با همین ترتیب چیده می‌شود: مرکز پشتِ لبه یقه ← لبه یقه ←
     * مرکز جلوی لبه یقه ← سر جلو ← لبه بیرونی ← مرکز پشتِ لبه بیرونی ← لبه مرکز
     * پشت (که تای پارچه است). پس لبه یقه همیشه از لبه شماره صفر شروع می‌شود و
     * پیاده‌کردن با PieceOps از مرکز پشت آغاز می‌شود.
     *
     * @param  string  $mode  stand یا fall
     * @return array<string, mixed>
     */
    protected function collarArc(float $neckLength, float $width, float $radius, string $mode = 'stand'): array
    {
        $width = max(0.5, $width);
        $radius = max($width + 1.0, $radius);
        $theta = rad2deg(max(1.0, $neckLength) / $radius);
        $segments = max(2, (int) ceil($theta / 25));
        $center = ['x' => 0.0, 'y' => 0.0];

        if ($mode === 'stand') {
            $outerRadius = max(0.5, $radius - $width);
            $from = 90.0;
            $to = 90.0 - $theta;
        } else {
            $outerRadius = $radius + $width;
            $from = 90.0 + $theta;
            $to = 90.0;
        }

        $neck = $this->arcPoints($center, $radius, $from, $to, $segments);
        $outer = $this->arcPoints($center, $outerRadius, $to, $from, $segments);

        return [
            'mode' => $mode,
            'radius' => round($radius, 3),
            'outer_radius' => round($outerRadius, 3),
            'width' => round($width, 3),
            'span' => round($theta, 2),
            'cb_neck' => $this->arcStart($center, $radius, $from),
            'cf_neck' => $this->pt($neck[count($neck) - 1]),
            'cf_outer' => $this->arcStart($center, $outerRadius, $to),
            'cb_outer' => $this->pt($outer[count($outer) - 1]),
            // جهت ادامه لبه یقه در مرکز جلو؛ اضافه جای دکمه در همین راستا می‌رود
            'cf_tangent' => ['x' => sin(deg2rad($to)), 'y' => -cos(deg2rad($to))],
            'neck' => $neck,
            'outer' => $outer,
        ];
    }

    /**
     * سر جلوی یک نوار یقه: اضافه جای دکمه با گوشه راست یا گرد.
     *
     * زنجیره از مرکز جلوی لبه یقه شروع می‌شود و به مرکز جلوی لبه بیرونی می‌رسد.
     * لبه‌های اضافه جای دکمه برچسب یقه نمی‌گیرند، چون روی خط یقه دوخته نمی‌شوند.
     *
     * @return array{points: array<int, array<string, mixed>>, tags: array<int, string>}
     */
    protected function bandFrontEnd(array $arc, float $extension, string $shape = 'round'): array
    {
        $neck = $this->pt($arc['cf_neck']);
        $outer = $this->pt($arc['cf_outer']);
        $tangent = $this->unit($arc['cf_tangent']);
        $width = max(0.5, $this->length($this->vec($neck, $outer)));

        if ($extension < 0.3) {
            return ['points' => [Geometry::point($outer['x'], $outer['y'])], 'tags' => ['side']];
        }

        $a = $this->add($neck, $tangent, $extension);
        $corner = $this->add($outer, $tangent, $extension);

        if ($shape !== 'round') {
            return [
                'points' => [
                    Geometry::point($a['x'], $a['y']),
                    Geometry::point($corner['x'], $corner['y']),
                    Geometry::point($outer['x'], $outer['y']),
                ],
                'tags' => ['default', 'side', 'default'],
            ];
        }

        $radius = min($extension * 0.9, $width * 0.7);
        $b = $this->add($outer, $tangent, $extension - $radius);

        return [
            'points' => [
                Geometry::point($a['x'], $a['y']),
                Geometry::curve($b['x'], $b['y'], $corner['x'], $corner['y']),
                Geometry::point($outer['x'], $outer['y']),
            ],
            'tags' => ['default', 'side', 'default'],
        ];
    }

    /**
     * چیدن مسیر بسته یقه از روی کمان.
     *
     * $frontEnd نقطه‌های سر جلوی یقه‌اند: از مرکز جلوی لبه یقه تا مرکز جلوی لبه
     * بیرونی (نقطه پایانی باید همان cf_outer یا جای تازه‌اش باشد). اگر خالی باشد
     * سر جلو با یک خط راست بسته می‌شود. $frontTag می‌تواند یک برچسب برای همه یا
     * یک برچسب برای هر نقطه باشد.
     *
     * @param  string|array<int, string>  $frontTag
     * @return array{outline: array<int, array<string, mixed>>, edges: array<int, string>, neck_edges: int}
     */
    protected function assembleCollar(array $arc, array $frontEnd = [], string $outerTag = 'hem', string|array $frontTag = 'side'): array
    {
        $frontEnd = $frontEnd === [] ? [Geometry::point($arc['cf_outer']['x'], $arc['cf_outer']['y'])] : array_values($frontEnd);

        $frontTags = is_array($frontTag)
            ? array_pad(array_slice(array_values($frontTag), 0, count($frontEnd)), count($frontEnd), 'side')
            : array_fill(0, count($frontEnd), $frontTag);

        $outline = array_merge(
            [Geometry::point($arc['cb_neck']['x'], $arc['cb_neck']['y'])],
            $arc['neck'],
            $frontEnd,
            $arc['outer'],
        );

        $edges = array_merge(
            array_fill(0, count($arc['neck']), 'neck'),
            $frontTags,
            array_fill(0, count($arc['outer']), $outerTag),
            ['default'],
        );

        return [
            'outline' => $outline,
            'edges' => $edges,
            'neck_edges' => count($arc['neck']),
        ];
    }

    /**
     * راستای پارچه یقه: موازی خط مرکز پشت، کمی داخل قطعه.
     *
     * لبه مرکز پشت یقه شعاعی است، پس همیشه راست است و می‌شود تای پارچه؛ خیاط
     * راستای پارچه را موازی همان می‌کشد.
     */
    protected function collarGrainline(array $arc, float $inset = 1.4): array
    {
        $neck = $this->pt($arc['cb_neck']);
        $outer = $this->pt($arc['cb_outer']);
        $along = $this->unit($this->vec($neck, $outer));
        $side = $this->unit($this->vec($neck, $this->pt($arc['cf_neck'])));
        $length = max(2.0, $this->length($this->vec($neck, $outer)));

        return $this->grainlineBetween(
            $this->add($this->add($neck, $side, $inset), $along, min(0.8, $length * 0.15)),
            $this->add($this->add($neck, $side, $inset), $along, $length - min(0.8, $length * 0.15)),
        );
    }

    /* ---------------------------------------------------------------------
     |  اندازه‌گیری و جورکردن با خط یقه
     * ------------------------------------------------------------------- */

    /**
     * طول دوخته‌شده لبه یقه یک قطعه یقه.
     *
     * از PieceOps گرفته می‌شود تا چین و پیلی ثبت‌شده روی همان لبه کم شود: یقه
     * چین‌دار پارچه‌اش بلندتر است ولی روی خط یقه به اندازه خط یقه می‌نشیند.
     */
    protected function neckEdgeLength(array $piece): float
    {
        $edges = $this->edgesWithTag($piece, 'neck');

        return $edges === [] ? 0.0 : PieceOps::seamLength($piece, $edges);
    }

    /** طول دوخته‌شده هر لبه‌ای از یک قطعه. */
    protected function seamOf(array $piece, string $tag): float
    {
        $edges = $this->edgesWithTag($piece, $tag);

        return $edges === [] ? 0.0 : PieceOps::seamLength($piece, $edges);
    }

    /**
     * «متر خط یقه»: قطعه کمکی که لبه یقه‌اش دقیقاً به اندازه خط یقه اندازه‌گیری‌شده
     * است. یقه روی همین پیاده می‌شود، درست مثل خیاطی که خط یقه را روی نوار کاغذ
     * باز می‌کند و یقه را کنارش می‌گذارد.
     *
     * @return array<string, mixed>
     */
    protected function necklineRuler(float $length): array
    {
        $length = max(1.0, round($length, 3));

        return [
            'code' => 'neckline-ruler',
            'name' => 'متر خط یقه',
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($length, 0),
                Geometry::point($length, 2),
                Geometry::point(0, 2),
            ],
            'meta' => ['edges' => ['neck', 'side', 'hem', 'side']],
        ];
    }

    /**
     * پیاده‌کردن لبه یقه روی خط یقه و راست‌کردن آن تا زیر رواداری.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function trueNeck(array $piece, float $target, string $tag = 'neck'): array
    {
        $ruler = $this->necklineRuler($target);
        $walk = PieceOps::walk($piece, $tag, $ruler, 'neck', ['tolerance' => static::NECK_TOLERANCE]);

        if ($walk['matched']) {
            return [$piece, $walk];
        }

        $trued = PieceOps::trueSeam($piece, $tag, $ruler, 'neck', [
            'adjust' => 'a',
            'anchor' => 'start',
            'tolerance' => 0.02,
            'rounds' => 24,
        ]);

        $piece = $trued['a'];

        return [$piece, PieceOps::walk($piece, $tag, $ruler, 'neck', ['tolerance' => static::NECK_TOLERANCE])];
    }

    /**
     * درفت با «دستگیره طول» تا لبه یقه دقیقاً روی خط یقه بنشیند، بعد پیاده‌کردن.
     *
     * @param  callable(float): array<string, mixed>  $draw
     * @return array{0: array<string, mixed>, 1: float, 2: float} قطعه، طول نهایی، اختلاف
     */
    protected function fitToNeckline(callable $draw, float $target): array
    {
        [$piece] = $this->fitNeckEdge($draw, $target);
        [$piece, $walk] = $this->trueNeck($piece, $target);
        $length = $this->neckEdgeLength($piece);

        $piece['meta']['neck_length'] = round($length, 2);
        $piece['meta']['neckline_length'] = round($target, 2);
        $piece['meta']['neck_difference'] = round($length - $target, 3);
        $piece['meta']['walked'] = [
            'collar' => $walk['a']['seam'] ?? null,
            'neckline' => $walk['b']['seam'] ?? null,
            'difference' => $walk['difference'] ?? null,
            'matched' => $walk['matched'] ?? null,
        ];

        return [$piece, round($length, 3), round($length - $target, 3)];
    }

    /* ---------------------------------------------------------------------
     |  نشانه‌ها
     * ------------------------------------------------------------------- */

    /**
     * نشانه‌های جفت‌شدن روی لبه یقه، در فاصله‌های داده‌شده از مرکز پشت.
     *
     * @param  array<int, array{at: float, label: string, pair?: string}>  $stops
     * @return array<string, mixed>
     */
    protected function neckNotches(array $piece, array $stops, string $tag = 'neck'): array
    {
        $edges = $this->edgesWithTag($piece, $tag);

        if ($edges === []) {
            return $piece;
        }

        $total = PieceOps::edgeLength($piece, $edges);

        foreach ($stops as $stop) {
            $at = max(0.0, min($total, (float) $stop['at']));
            $on = PieceOps::pointAlong($piece, $edges, $at);

            $piece['notches'][] = $this->notch(
                (float) $on['x'],
                (float) $on['y'],
                (int) $on['edge'],
                $stop['label'],
                $stop['pair'] ?? null,
            );
        }

        return $piece;
    }

    /**
     * نشانه‌های همیشگی نیم‌یقه: سرشانه و مرکز جلو (مرکز پشت روی تا است و نشانه
     * نمی‌خواهد، ولی خط نشانه‌اش کشیده می‌شود).
     *
     * @param  array<string, mixed>  $neck
     * @return array<string, mixed>
     */
    protected function halfCollarNotches(array $piece, array $neck, float $target): array
    {
        $shoulder = max(0.0, min($target - 0.5, $neck['back']));

        return $this->neckNotches($piece, [
            ['at' => $shoulder, 'label' => 'درز سرشانه', 'pair' => 'shoulder'],
            ['at' => $target, 'label' => 'مرکز جلو', 'pair' => 'center_front'],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  خط خواب و لایه چسب
     * ------------------------------------------------------------------- */

    /**
     * خط خواب یقه: خطی موازی لبه یقه، به فاصله بلندی پایه.
     *
     * روی یقه یک‌تکه همین خط جای تا خوردن یقه است و روی یقه دوتکه، درز پایه و رویه.
     *
     * @return array<string, mixed>
     */
    protected function rollLineMarker(array $arc, float $stand, string $label = 'خط خواب یقه'): array
    {
        $sign = ($arc['mode'] ?? 'stand') === 'stand' ? -1.0 : 1.0;
        $radius = (float) $arc['radius'] + ($sign * $stand);
        $center = ['x' => 0.0, 'y' => 0.0];
        $span = (float) $arc['span'];
        [$from, $to] = ($arc['mode'] ?? 'stand') === 'stand' ? [90.0, 90.0 - $span] : [90.0 + $span, 90.0];

        $start = $this->arcStart($center, $radius, $from);
        $end = $this->arcStart($center, $radius, $to);

        return $this->marker('roll_line', $label, $start['x'], $start['y'], $end['x'], $end['y']);
    }

    /**
     * لایه چسب یقه.
     *
     * خیاط لایه چسب یقه را یک‌دهم تا دو‌دهم کوچک‌تر از خود یقه می‌برد تا در درز
     * جمع نشود و لبه یقه کلفت نیفتد.
     *
     * @return array<string, mixed>
     */
    protected function collarInterfacing(array $piece, ?string $name = null, float $trim = 0.2): array
    {
        $copy = $this->interfacingOf($piece, $name, $trim);
        $copy['cut_quantity'] = max(1, (int) ($piece['cut_quantity'] ?? 1) - 1);
        $copy['notches'] = [];
        $copy['meta']['interfacing_trim'] = $trim;

        return $copy;
    }

    /* ---------------------------------------------------------------------
     |  نوار راست
     * ------------------------------------------------------------------- */

    /**
     * پاک کردن مسیر موازیِ درون‌رفته.
     *
     * وقتی یک مسیر را برای ساختن سجاف به داخل می‌بریم، سر گوشه‌های فرورفته (مثل
     * خرک یقه انگلیسی) نقطه‌های تازه روی هم می‌افتند و مسیر گره می‌خورد. خیاط در
     * این حالت گوشه را نمی‌کشد و خط سجاف را روان رد می‌کند؛ همین کار این‌جا با
     * دور ریختن نقطه‌هایی که رو به عقب برمی‌گردند انجام می‌شود.
     *
     * @param  array<int, array{x: float, y: float}>  $path  مسیر بیرونی
     * @param  array<int, array{x: float, y: float}>  $inner  مسیر موازی خام
     * @return array<int, array{x: float, y: float}>
     */
    protected function cleanOffset(array $path, array $inner): array
    {
        $count = count($inner);
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            if ($out === []) {
                $out[] = $inner[$i];

                continue;
            }

            $direction = $this->unit($this->vec(
                $path[max(0, $i - 1)],
                $path[min(count($path) - 1, $i + 1)],
            ));

            if ($this->dot($direction, $this->vec($out[count($out) - 1], $inner[$i])) <= 0.02) {
                continue; // این نقطه رو به عقب برمی‌گردد و مسیر را گره می‌زند
            }

            $out[] = $inner[$i];
        }

        return $out;
    }

    /**
     * نوار راست دولا (نوار یقه کشی، پاپیون، نوار جمع‌کننده).
     *
     * لبه صفر برچسب یقه می‌گیرد و بقیه بسته به کاربرد.
     *
     * @return array{outline: array<int, array<string, mixed>>, edges: array<int, string>}
     */
    protected function strip(float $length, float $height): array
    {
        return [
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point(max(1.0, $length), 0),
                Geometry::point(max(1.0, $length), max(0.5, $height)),
                Geometry::point(0, max(0.5, $height)),
            ],
            'edges' => ['neck', 'side', 'hem', 'side'],
        ];
    }
}
