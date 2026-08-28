<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایه متعلقات: کیف، کلاه، شال، دستکش، کمربند و مانند آن.
 *
 * این خانواده یک تفاوتِ بنیادی با بقیهٔ کاتالوگ دارد و همین تفاوت است که وجودِ
 * یک پایهٔ جدا را لازم می‌کند: **این‌ها از بلوکِ بدن درفت نمی‌شوند.** کیف دور
 * سینه ندارد و شال سرشانه. اندازه‌شان از کاربرد می‌آید، نه از متری که دور تن
 * گرفته شده. تنها استثناها جایی است که واقعاً به تن می‌خورند — کلاه با دور سر و
 * کمربند با دور کمر — و همان‌جا هم فقط *یک* اندازه لازم است، نه یک بلوک.
 *
 * پس به‌جای پنل و ساسون، این پایه یک واژگانِ هندسیِ کوچک دارد و هر متعلق از
 * ترکیبِ همان‌ها ساخته می‌شود:
 *
 *   rect     مستطیل (تنهٔ کیف، شالِ چهارگوش، بندِ کمر)
 *   taper    ذوزنقه (تنهٔ کیفِ جمع‌شونده، نوکِ کراوات)
 *   disc     ربعِ دایره روی دو تا (تاجِ کلاه، شالِ گرد)
 *   ring     ربعِ حلقه (لبهٔ کلاه)
 *   gore     ترکِ کلاه (یک هشتم یا یک ششمِ تاج)
 *   tri      مثلث (شالِ سه‌گوش، دستمالِ گردن)
 *   tube     مستطیلی که از دو سر به هم می‌رسد (شالِ گردنِ حلقه‌ای، ساق‌پوش)
 *
 * لبه‌ها همه برچسبِ default یا side می‌گیرند: این قطعه‌ها یقه و حلقهٔ آستین
 * ندارند و اگر برچسبِ بدن بخورند، دوخت‌یابِ سامانه آن‌ها را به لباس می‌چسباند.
 */
abstract class AccessoryBaseGenerator extends BaseGenerator
{
    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'accessory';
    }

    /** دستهٔ متعلق، برای فهرست و فیلتر. */
    protected const KINDS = [
        'bag' => 'کیف',
        'hat' => 'کلاه',
        'scarf' => 'شال و روسری',
        'glove' => 'دستکش',
        'belt' => 'کمربند',
        'tie' => 'کراوات و پاپیون',
        'warmer' => 'ساق‌پوش و مچ‌پوش',
        'other' => 'دیگر',
    ];

    /**
     * شخصیتِ این متعلق.
     *
     * کلیدها: prefix، title، kind، parts (فهرست قطعه‌ها)، extra، notes.
     *
     * هر عضوِ parts یک آرایه است با کلیدهای: form (rect|taper|disc|ring|gore|
     * tri|tube)، name، cut، fold، اندازه‌های همان فرم، و notes.
     *
     * @return array<string, mixed>
     */
    abstract protected function accessory(): array;

    public function label(): string
    {
        return (string) ($this->accessory()['title'] ?? 'متعلق');
    }

    public function paramsSchema(): array
    {
        $a = $this->accessory();

        return array_merge([
            'seam_allowance' => [
                'label' => 'اضافه درز', 'min' => 0.5, 'max' => 2, 'step' => 0.25,
                'default' => 1.0, 'unit' => 'سانتی‌متر',
            ],
            'scale' => [
                'label' => 'بزرگ‌نمایی اندازه', 'min' => 0.7, 'max' => 1.4, 'step' => 0.05,
                'default' => 1.0,
                'hint' => 'همهٔ اندازه‌های این متعلق با همین ضریب بزرگ یا کوچک می‌شوند.',
            ],
        ], (array) ($a['extra'] ?? []));
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $a = $this->accessory();
        $prefix = (string) ($a['prefix'] ?? static::key()).'-';
        $scale = max(0.5, (float) $this->param($params, 'scale', 1.0));
        $pieces = [];

        foreach ((array) ($a['parts'] ?? []) as $index => $part) {
            $spec = $this->resolvePart($part, $measurements, $params, $scale);
            $built = $this->buildPart($spec, $prefix.($spec['code'] ?? ('part-'.$index)));

            if ($built !== null) {
                $pieces[] = $built;
            }
        }

        if ($pieces === []) {
            return [];
        }

        foreach ($pieces as $i => $piece) {
            $pieces[$i]['meta']['accessory'] = [
                'model' => (string) ($a['prefix'] ?? static::key()),
                'kind' => (string) ($a['kind'] ?? 'other'),
            ];
        }

        $pieces[0]['meta']['notes'] = array_merge(
            $pieces[0]['meta']['notes'] ?? [],
            (array) ($a['notes'] ?? []),
        );

        return $this->finish($pieces);
    }

    /**
     * اندازه‌های یک قطعه پس از بزرگ‌نمایی و خواندنِ اندازهٔ بدن.
     *
     * هر عددی می‌تواند رشته باشد؛ آن‌وقت نامِ یک اندازهٔ بدن است (مثل head یا
     * waist) و از اندازه‌ها خوانده می‌شود. همین یک قاعده کافی است تا کلاه با دور
     * سر و کمربند با دور کمر ساخته شود، بی آنکه بقیهٔ متعلقات به بدن گره بخورند.
     *
     * @param  array<string, mixed>  $part
     * @return array<string, mixed>
     */
    protected function resolvePart(array $part, array $measurements, array $params, float $scale): array
    {
        foreach ($part as $key => $value) {
            if (in_array($key, ['form', 'name', 'code', 'notes', 'part'], true)) {
                continue;
            }

            if (is_string($value)) {
                $part[$key] = $this->bodyValue($value, $measurements, $params);

                continue;
            }

            if (is_float($value) || is_int($value)) {
                $part[$key] = in_array($key, ['cut', 'panels'], true)
                    ? (int) $value
                    : ((float) $value) * $scale;
            }
        }

        return $part;
    }

    /**
     * یک اندازهٔ بدن یا پارامتر، به سانتی‌متر.
     *
     * قالب: «head»، «waist»، «head/6»، «waist+4».
     */
    protected function bodyValue(string $expression, array $measurements, array $params): float
    {
        if (preg_match('/^([a-z_]+)\s*([+\-*\/])\s*([0-9.]+)$/', $expression, $hit) === 1) {
            $base = $this->sourceValue($hit[1], $measurements, $params);
            $operand = (float) $hit[3];

            return match ($hit[2]) {
                '+' => $base + $operand,
                '-' => $base - $operand,
                '*' => $base * $operand,
                '/' => $operand > 0 ? $base / $operand : $base,
                default => $base,
            };
        }

        return $this->sourceValue($expression, $measurements, $params);
    }

    /** اندازهٔ خام: از پارامترها، از اندازه‌های بدن، یا تخمینِ دور سر. */
    protected function sourceValue(string $key, array $measurements, array $params): float
    {
        if ($this->param($params, $key) !== null) {
            return (float) $this->param($params, $key, 0);
        }

        if ($key === 'head') {
            // دور سر در اندازه‌های سامانه نیست؛ از قد تخمین زده می‌شود و همان
            // جدولی که لباس کودک استفاده می‌کند این‌جا هم به کار می‌آید
            return $this->headFromHeight($this->m($measurements, 'height', 168));
        }

        return $this->m($measurements, $key, 0);
    }

    /**
     * دور سر بر پایه قد (سانتی‌متر).
     *
     * دور سر با قد خطی بالا نمی‌رود؛ خیلی زودتر می‌ایستد. برای همین جدول است، نه
     * ضریب.
     */
    protected function headFromHeight(float $height): float
    {
        $table = [
            [60, 41.0], [80, 47.0], [104, 51.0], [128, 53.5], [152, 55.5], [168, 56.5], [195, 58.0],
        ];

        $last = count($table) - 1;

        if ($height <= $table[0][0]) {
            return $table[0][1];
        }

        if ($height >= $table[$last][0]) {
            return $table[$last][1];
        }

        for ($i = 0; $i < $last; $i++) {
            [$h0, $g0] = $table[$i];
            [$h1, $g1] = $table[$i + 1];

            if ($height > $h1) {
                continue;
            }

            return round($g0 + (($g1 - $g0) * (($height - $h0) / ($h1 - $h0))), 1);
        }

        return $table[$last][1];
    }

    /**
     * ساختِ یک قطعه از روی فرمِ هندسی‌اش.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    protected function buildPart(array $spec, string $code): ?array
    {
        $form = (string) ($spec['form'] ?? 'rect');
        $name = (string) ($spec['name'] ?? 'قطعه');
        $cut = max(1, (int) ($spec['cut'] ?? 1));
        $fold = (bool) ($spec['fold'] ?? false);

        [$outline, $edges, $folds] = match ($form) {
            'rect' => $this->rectOutline((float) ($spec['w'] ?? 20), (float) ($spec['h'] ?? 20), $fold),
            'tube' => $this->rectOutline((float) ($spec['w'] ?? 60), (float) ($spec['h'] ?? 25), $fold),
            'taper' => $this->taperOutline(
                (float) ($spec['top'] ?? 20),
                (float) ($spec['bottom'] ?? 14),
                (float) ($spec['h'] ?? 22),
            ),
            'disc' => $this->discOutline((float) ($spec['r'] ?? 10)),
            'ring' => $this->ringOutline((float) ($spec['r'] ?? 10), (float) ($spec['width'] ?? 6)),
            'gore' => $this->goreOutline(
                (float) ($spec['girth'] ?? 57),
                (float) ($spec['h'] ?? 20),
                max(3, (int) ($spec['panels'] ?? 6)),
            ),
            'tri' => $this->triOutline((float) ($spec['w'] ?? 100), (float) ($spec['h'] ?? 50)),
            default => [[], [], []],
        };

        if (count($outline) < 3) {
            return null;
        }

        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => $cut,
            'on_fold' => $fold && $folds !== [],
            'outline' => $outline,
            'grainline' => $this->grainline(
                $minX + (($maxX - $minX) * 0.5),
                $minY + 1.0,
                $maxY - 1.0,
            ),
            'meta' => [
                'part' => (string) ($spec['part'] ?? 'accessory'),
                'edges' => $edges,
                'fold_edges' => $fold ? $folds : [],
                'girth_role' => 'trim',
                'form' => $form,
                'notes' => (array) ($spec['notes'] ?? []),
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  فرم‌های هندسی
     * ------------------------------------------------------------------- */

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, int>} */
    protected function rectOutline(float $w, float $h, bool $fold): array
    {
        $w = max(1.0, $fold ? $w / 2 : $w);
        $h = max(1.0, $h);

        return [
            [
                Geometry::point(0, 0),
                Geometry::point($w, 0),
                Geometry::point($w, $h),
                Geometry::point(0, $h),
            ],
            ['default', 'side', 'default', 'default'],
            [3],
        ];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, int>} */
    protected function taperOutline(float $top, float $bottom, float $h): array
    {
        $top = max(1.0, $top);
        $bottom = max(1.0, $bottom);
        $inset = ($top - $bottom) / 2;

        return [
            [
                Geometry::point(0, 0),
                Geometry::point($top, 0),
                Geometry::point($top - $inset, $h),
                Geometry::point($inset, $h),
            ],
            ['default', 'side', 'default', 'side'],
            [],
        ];
    }

    /**
     * نیم‌دایره روی دو تا — تاجِ کلاهِ گرد یا شالِ گرد.
     *
     * روی تا بریده می‌شود، پس آنچه درمی‌آید دایرهٔ کامل است. خطِ تا همان قطر
     * است. کمان با خط‌های کوتاه ساخته می‌شود، نه با یک منحنیِ بلند: کمانی که با
     * یک نقطهٔ کنترل کشیده شود روی شعاعِ بزرگ از دایره فاصله می‌گیرد و قطعه
     * دیگر گرد نیست.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, int>}
     */
    protected function discOutline(float $r): array
    {
        $r = max(2.0, $r);

        // از (۰،۰) تا (۰، ۲r) با برآمدگی به راست؛ لبهٔ بسته‌شونده همان خطِ تاست
        $outline = $this->arc(0.0, $r, $r, 0.0, M_PI, 24);
        $edges = array_fill(0, count($outline), 'default');

        return [$outline, $edges, [count($outline) - 1]];
    }

    /**
     * نیم‌حلقه — لبهٔ کلاه، در دو نیمه بریده می‌شود.
     *
     * روی تا نمی‌رود: نیم‌حلقه دو لبهٔ صافِ جدا دارد (دو سرِ حلقه) و «تا» فقط
     * یک لبه می‌پذیرد. پس دو نیمه بریده و در همان دو سر به هم دوخته می‌شوند —
     * همان کاری که با لبهٔ کلاه در واقعیت می‌کنند.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, int>}
     */
    protected function ringOutline(float $r, float $width): array
    {
        $r = max(2.0, $r);
        $outer = $r + max(1.0, $width);

        // مرکزِ حلقه روی (outer, outer) تا همهٔ نقطه‌ها مثبت بمانند
        $outline = $this->arc($outer, $outer, $r, 0.0, M_PI, 20);

        foreach ($this->arc($outer, $outer, $outer, M_PI, 0.0, 20) as $point) {
            $outline[] = $point;
        }

        $edges = array_fill(0, count($outline), 'default');

        return [$outline, $edges, []];
    }

    /**
     * نقطه‌های یک کمان، به‌صورت خط‌های کوتاه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function arc(float $cx, float $cy, float $r, float $from, float $to, int $steps): array
    {
        $points = [];
        $steps = max(4, $steps);

        for ($i = 0; $i <= $steps; $i++) {
            $angle = $from + (($to - $from) * ($i / $steps));
            $points[] = Geometry::point(
                $cx + ($r * sin($angle)),
                $cy - ($r * cos($angle)),
            );
        }

        return $points;
    }

    /**
     * ترکِ کلاه: یک برشِ نوک‌تیز از تاج.
     *
     * پهنای پای ترک، دورِ سر تقسیم بر تعدادِ ترک است. نوکِ ترک روی نقطه جمع
     * نمی‌شود بلکه چند میلی‌متر پهنا نگه می‌دارد، وگرنه شش نوکِ تیز روی یک نقطه
     * جمع می‌شوند و همان‌جا پاره می‌شود.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, int>}
     */
    protected function goreOutline(float $girth, float $h, int $panels): array
    {
        $base = max(3.0, $girth / max(3, $panels));
        $h = max(4.0, $h);
        $tip = 0.6;

        return [
            [
                Geometry::point(0, $h),
                Geometry::curve($base / 2, 0, $base * 0.08, $h * 0.42),
                Geometry::point(($base / 2) + $tip, 0),
                Geometry::curve($base, $h, $base * 0.92, $h * 0.42),
            ],
            ['side', 'default', 'side', 'default'],
            [],
        ];
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, int>} */
    protected function triOutline(float $w, float $h): array
    {
        $w = max(2.0, $w);
        $h = max(2.0, $h);

        return [
            [
                Geometry::point(0, 0),
                Geometry::point($w, 0),
                Geometry::point($w / 2, $h),
            ],
            ['default', 'default', 'default'],
            [],
        ];
    }
}
