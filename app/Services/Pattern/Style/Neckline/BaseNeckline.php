<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleModifier;
use App\Support\Format;

/**
 * پایه همه سبک‌های «خط یقه».
 *
 * کار مشترک همه خط‌یقه‌ها یکی است و همین‌جا انجام می‌شود:
 *
 *   ۱. لبه یقه قطعه‌های تنه پیدا می‌شود و «نقطه‌های تکیه» درمی‌آید: مرکز جلو/پشت،
 *      سرگردنِ سرشانه، نوک سرشانه، جهت سرشانه و عمود بر آن.
 *   ۲. سبک تنها «مسیر تازه خط یقه» را می‌سازد؛ بقیه قطعه دست‌نخورده می‌ماند.
 *   ۳. مسیر تازه جای لبه یقه (و در صورت لزوم سرشانه) می‌نشیند، برچسب لبه‌ها دوباره
 *      نوشته می‌شود، ساسون و نشانه‌ها دوباره به لبه‌ها بسته می‌شوند و نشانه سرشانه
 *      وسط سرشانه تازه می‌رود.
 *   ۴. زاویه رسیدن خط یقه به سرشانه در جلو و پشت مکمل هم گرفته می‌شود
 *      (جمعشان ۱۸۰ درجه) تا خط یقه پس از دوختن سرشانه گوشه نداشته باشد.
 *   ۵. اگر خواسته شده باشد، سجاف یا نوار مورب ساخته می‌شود و طول خط یقه گزارش.
 *
 * پس یک سبک تازه فقط باید frontPath() (و در صورت نیاز backPath()) را بنویسد.
 */
abstract class BaseNeckline implements StyleModifier
{
    use NeckGeometry;

    /** بازه مجاز زاویه رسیدن خط یقه به سرشانه؛ بیرون از این بازه خط یقه گوشه می‌دهد. */
    protected const MIN_SHOULDER_ANGLE = 55.0;

    protected const MAX_SHOULDER_ANGLE = 125.0;

    public static function group(): string
    {
        return 'neckline';
    }

    /** کدام طرف «تعیین‌کننده» است و طرف دیگر با آن جور می‌شود. */
    protected function leadSide(): string
    {
        return 'front';
    }

    /**
     * مسیر تازه خط یقه جلو.
     *
     * @param  array<string, mixed>  $a  نقطه‌های تکیه
     * @param  array<string, mixed>  $p  پارامترها
     * @return array<string, mixed> کلیدها: points, tags, alpha, consume_shoulder, armhole_t, notes
     */
    abstract protected function frontPath(array $a, array $p, ?float $partnerAngle): array;

    /**
     * مسیر تازه خط یقه پشت؛ پیش‌فرض یک منحنی ساده که با جلو جور می‌شود.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $p
     */
    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        $depth = (float) ($p['back_depth'] ?? min(3.0, ((float) ($p['depth'] ?? 0)) * 0.18));
        $snp = $this->movedSnp($a, (float) ($p['width'] ?? 0));
        $alpha = $this->complement($partnerAngle);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + $depth);

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, $alpha, $snp)],
            'tags' => ['neck'],
            'alpha' => $alpha,
        ];
    }

    /** جهت خروج از پایان یک منحنی، برای ادامه نرم مسیر. */
    protected function continueDir(array $curvePoint): array
    {
        return $this->unit($this->vec(
            ['x' => (float) $curvePoint['cx'], 'y' => (float) $curvePoint['cy']],
            $curvePoint,
        ));
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    protected function depthField(float $default, float $min = 0, float $max = 30, string $label = 'گودی یقه'): array
    {
        return [
            'label' => $label, 'min' => $min, 'max' => $max, 'step' => 0.5, 'default' => $default,
            'unit' => 'سانتی‌متر', 'hint' => 'از خط یقه بلوک به پایین اندازه گرفته می‌شود.',
        ];
    }

    protected function widthField(float $default, float $min = 0, float $max = 14): array
    {
        return [
            'label' => 'گشادی یقه روی سرشانه', 'min' => $min, 'max' => $max, 'step' => 0.5, 'default' => $default,
            'unit' => 'سانتی‌متر', 'hint' => 'سرگردن روی خط سرشانه به همین اندازه به بیرون می‌رود.',
        ];
    }

    protected function backDepthField(float $default, float $max = 30): array
    {
        return [
            'label' => 'گودی یقه پشت', 'min' => 0, 'max' => $max, 'step' => 0.5, 'default' => $default,
            'unit' => 'سانتی‌متر',
        ];
    }

    /** گزینه‌های تمام‌کردن لبه یقه؛ در همه سبک‌ها هست. */
    protected function finishFields(float $facingWidth = 5.0): array
    {
        return [
            'finish' => [
                'label' => 'تمام‌کردن لبه یقه', 'type' => 'select', 'default' => 'facing',
                'options' => ['none' => 'بدون قطعه', 'facing' => 'سجاف', 'binding' => 'نوار مورب'],
            ],
            'finish_width' => [
                'label' => 'پهنای سجاف یا نوار', 'min' => 1.5, 'max' => 10, 'step' => 0.5,
                'default' => $facingWidth, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /**
     * پارامترهای پاک‌شده: مقدار کاربر یا پیش‌فرض، در بازه مجاز.
     *
     * @return array<string, mixed>
     */
    protected function params(array $context): array
    {
        $given = is_array($context['params'] ?? null) ? $context['params'] : [];
        $out = [];

        foreach ($this->paramsSchema() as $key => $field) {
            $value = $given[$key] ?? $field['default'];
            $type = $field['type'] ?? 'number';

            if ($type === 'toggle') {
                $out[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $field['default'];

                continue;
            }

            if ($type === 'select') {
                $out[$key] = array_key_exists((string) $value, $field['options'] ?? []) ? (string) $value : $field['default'];

                continue;
            }

            $number = is_numeric($value) ? (float) $value : (float) $field['default'];

            if (isset($field['min'])) {
                $number = max((float) $field['min'], $number);
            }

            if (isset($field['max'])) {
                $number = min((float) $field['max'], $number);
            }

            $out[$key] = $number;
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  پذیرش
     * ------------------------------------------------------------------- */

    public function supports(array $pieces, array $context): true|string
    {
        $body = array_filter($pieces, fn (array $piece) => $this->isBodyPiece($piece));

        if ($body === []) {
            return 'این لباس خط یقه ندارد؛ سبک خط یقه فقط روی بالاتنه معنا دارد.';
        }

        foreach ($body as $piece) {
            if ($this->anchors($piece) === null) {
                return 'خط یقه «'.$piece['name'].'» با سرشانه دنبال نمی‌شود؛ این سبک روی چنین قطعه‌ای درفت نمی‌شود.';
            }
        }

        return $this->supportsNeckline($pieces, $context);
    }

    /** بررسی ویژه هر سبک؛ پیش‌فرض پذیرش. */
    protected function supportsNeckline(array $pieces, array $context): true|string
    {
        return true;
    }

    /* ---------------------------------------------------------------------
     |  اجرا
     * ------------------------------------------------------------------- */

    public function apply(array $pieces, array $context): array
    {
        $p = $this->params($context);
        $before = $this->necklineLengths($pieces);
        $notes = [];
        $extra = [];
        $partnerAngle = null;
        $angles = [];
        $drops = [];
        $noShoulder = false;

        $order = $this->pieceOrder($pieces);

        foreach ($order as $index) {
            $piece = $pieces[$index];
            $anchors = $this->anchors($piece);

            if ($anchors === null) {
                continue;
            }

            $side = $anchors['side'];
            $path = $side === 'front'
                ? $this->frontPath($anchors, $p, $partnerAngle)
                : $this->backPath($anchors, $p, $partnerAngle);

            if ($side === $this->leadSide()) {
                $partnerAngle = $path['alpha'] ?? null;
            }

            $angles[$side] = $path['alpha'] ?? null;
            $noShoulder = $noShoulder || ! empty($path['consume_shoulder']);

            $shaped = $this->shape($piece, $anchors, $path, $p);
            $drops[$side] = round($shaped['meta']['neckline_drop'] ?? 0, 2);
            $pieces[$index] = $shaped;

            foreach ($path['notes'] ?? [] as $note) {
                $notes[] = $note;
            }

            foreach ($path['pieces'] ?? [] as $piece2) {
                $extra[] = $piece2;
            }
        }

        $after = $this->necklineLengths($pieces);
        $sum = ($angles['front'] ?? null) !== null && ($angles['back'] ?? null) !== null
            ? round($angles['front'] + $angles['back'], 2)
            : null;

        // سجاف یا نوار مورب
        $finish = $p['finish'] ?? 'none';

        if ($finish === 'facing') {
            foreach ($order as $index) {
                $facing = $this->facingPiece($pieces[$index], (float) ($p['finish_width'] ?? 5));

                if ($facing !== null) {
                    $extra[] = $facing;
                }
            }

            $notes[] = 'سجاف خط یقه به پهنای '.Format::cm((float) $p['finish_width'])
                .' ساخته شد؛ سجاف از خط یقه به داخل قطعه فاصله دارد و کپی خط یقه نیست.';
        } elseif ($finish === 'binding') {
            $extra[] = $this->bindingPiece($after['half'], (float) ($p['finish_width'] ?? 3));
            $notes[] = 'نوار مورب خط یقه ساخته شد؛ نوار ۳٪ کوتاه‌تر از خط یقه بریده می‌شود تا لبه جمع بنشیند.';
        }

        $notes[] = $this->label().': خط یقه از '.Format::cm($before['half']).' به '.Format::cm($after['half'])
            .' رسید (نیم‌یقه: جلو '.Format::cm($after['front']).' و پشت '.Format::cm($after['back'])
            .')؛ دور کامل یقه '.Format::cm($after['full']).'.';

        if ($noShoulder) {
            $notes[] = 'این خط یقه درز سرشانه را برمی‌دارد، پس زاویه سرشانه بررسی نمی‌شود؛ لبه بالا باید با کش یا نوار محکم شود تا باز نایستد.';
        } elseif ($sum !== null) {
            $notes[] = abs($sum - 180) <= 2.0
                ? 'خط یقه جلو و پشت در سرگردن مکمل هم درفت شد (جمع زاویه '.$sum.' درجه)، پس پس از دوختن سرشانه گوشه ندارد.'
                : 'زاویه خط یقه در سرگردن جمعاً '.$sum.' درجه شد؛ پس از دوختن سرشانه خط یقه را با خط‌کش منحنی صاف کنید.';
        }

        return [
            'pieces' => array_merge(array_values($pieces), $extra),
            'notes' => $notes,
            'meta' => [
                'neckline' => [
                    'style' => static::key(),
                    'before' => $before,
                    'after' => $after,
                    'front_drop' => $drops['front'] ?? 0.0,
                    'back_drop' => $drops['back'] ?? 0.0,
                    'shoulder_angles' => [
                        'front' => $angles['front'] ?? null,
                        'back' => $angles['back'] ?? null,
                        'sum' => $sum,
                        'smooth' => $noShoulder ? null : ($sum !== null && abs($sum - 180) <= 2.0),
                    ],
                    'shoulder_seam' => ! $noShoulder,
                    'finish' => $finish,
                ],
            ],
        ];
    }

    /** ترتیب پردازش: طرف تعیین‌کننده اول. */
    protected function pieceOrder(array $pieces): array
    {
        $lead = [];
        $rest = [];

        foreach ($pieces as $index => $piece) {
            if (! $this->isBodyPiece($piece)) {
                continue;
            }

            if ($this->sideOf($piece) === $this->leadSide()) {
                $lead[] = $index;
            } else {
                $rest[] = $index;
            }
        }

        return array_merge($lead, $rest);
    }

    /* ---------------------------------------------------------------------
     |  نقطه‌های تکیه
     * ------------------------------------------------------------------- */

    /**
     * نقطه‌های تکیه خط یقه یک قطعه.
     *
     * @return array<string, mixed>|null
     */
    protected function anchors(array $piece): ?array
    {
        $edges = $this->edgesWithTag($piece, 'neck');
        $outline = array_values($piece['outline'] ?? []);
        $count = count($outline);

        if ($edges === [] || $count < 5) {
            return null;
        }

        $neck = $edges[0];
        $tags = $this->edgeTags($piece);

        // لبه پس از یقه باید سرشانه باشد وگرنه نمی‌دانیم مسیر را کجا ببندیم
        if (($tags[($neck + 1) % $count] ?? null) !== 'shoulder') {
            return null;
        }

        if ($neck + 3 > $count) {
            return null; // برای بریدن بدون پیچیدن دور قطعه جا نیست
        }

        $cf = $this->pt($outline[$neck]);
        $snp = $this->pt($outline[($neck + 1) % $count]);
        $tip = $this->pt($outline[($neck + 2) % $count]);
        $shoulder = $this->unit($this->vec($snp, $tip));
        $bounds = Geometry::bounds($outline);

        return [
            'piece' => $piece,
            'side' => $this->sideOf($piece),
            'neck_edge' => $neck,
            'shoulder_edge' => ($neck + 1) % $count,
            'armhole_edge' => ($neck + 2) % $count,
            'cf' => $cf,
            'snp' => $snp,
            'tip' => $tip,
            'shoulder' => $shoulder,
            'shoulder_length' => round($this->length($this->vec($snp, $tip)), 2),
            'center_x' => $cf['x'],
            'bottom_y' => $bounds[3],
            'width' => round($bounds[2] - $bounds[0], 2),
            'neck_width' => round(abs($snp['x'] - $cf['x']), 2),
            'neck_depth' => round($cf['y'] - $snp['y'], 2),
        ];
    }

    /** سرگردن تازه پس از گشادکردن یقه روی خط سرشانه. */
    protected function movedSnp(array $a, float $width): array
    {
        $width = max(-$a['neck_width'] + 1.0, min($a['shoulder_length'] - 2.5, $width));

        return Geometry::point(
            $a['snp']['x'] + ($a['shoulder']['x'] * $width),
            $a['snp']['y'] + ($a['shoulder']['y'] * $width),
        );
    }

    /** زاویه مکمل برای طرف دوم؛ در بازه مجاز نگه داشته می‌شود. */
    protected function complement(?float $angle): float
    {
        return $this->clampAngle(180.0 - ($angle ?? 90.0));
    }

    protected function clampAngle(float $angle): float
    {
        return max(static::MIN_SHOULDER_ANGLE, min(static::MAX_SHOULDER_ANGLE, $angle));
    }

    /**
     * جهتی که خط یقه از سرگردن به سمت داخل یقه می‌رود، با زاویه خواسته‌شده نسبت به
     * سرشانه.
     */
    protected function exitDirection(array $a, float $alpha, ?array $snp = null): array
    {
        $snp ??= $a['snp'];
        $toCenter = $this->vec($snp, ['x' => $a['center_x'], 'y' => $a['cf']['y']]);
        $plus = $this->rotateVector($a['shoulder'], $alpha);
        $minus = $this->rotateVector($a['shoulder'], -$alpha);

        return $this->dot($plus, $toCenter) >= $this->dot($minus, $toCenter) ? $plus : $minus;
    }

    /**
     * منحنی رسیدن به سرگردن: نقطه کنترل، برخورد مماس آغاز و مماس پایان است، پس هم
     * زاویه شروع (عمود بر خط مرکز) و هم زاویه رسیدن به سرشانه رعایت می‌شود.
     *
     * @param  array{x: float, y: float}  $from
     * @param  array{x: float, y: float}  $direction  جهت خروج از نقطه آغاز
     */
    protected function arrive(array $from, array $direction, array $a, float $alpha, ?array $snp = null): array
    {
        $snp ??= $a['snp'];
        $exit = $this->exitDirection($a, $alpha, $snp);
        $chord = $this->length($this->vec($from, $snp));
        $control = $this->intersection($this->pt($from), $direction, $this->pt($snp), $exit);

        // اگر دو مماس تقریباً موازی باشند برخوردشان خیلی دور می‌افتد و منحنی باد
        // می‌کند؛ در آن حالت نقطه کنترل را از میانگین نیم‌وتر روی دو مماس می‌گیریم.
        $wedge = $this->angleBetween($direction, ['x' => -$exit['x'], 'y' => -$exit['y']]);
        $far = $control === null ? INF : max(
            $this->length($this->vec($from, $control)),
            $this->length($this->vec($snp, $control)),
        );

        if ($control === null || $chord < 0.01 || $wedge < 30.0 || $far > $chord * 1.6) {
            $c1 = $this->add($this->pt($from), $direction, $chord * 0.5);
            $c2 = $this->add($this->pt($snp), $exit, $chord * 0.5);
            $control = ['x' => ($c1['x'] + $c2['x']) / 2, 'y' => ($c1['y'] + $c2['y']) / 2];
        }

        // اگر نقطه کنترل روی خود وتر بیفتد، لبه در عمل راست است و منحنی لازم ندارد
        $chordDir = $this->unit($this->vec($from, $snp));
        $normal = ['x' => -$chordDir['y'], 'y' => $chordDir['x']];

        if (abs($this->dot($this->vec($from, $control), $normal)) < 0.02) {
            return Geometry::point((float) $snp['x'], (float) $snp['y']);
        }

        return Geometry::curve((float) $snp['x'], (float) $snp['y'], $control['x'], $control['y']);
    }

    /** زاویه یک مسیر مستقیم نسبت به سرشانه (برای یقه هفت و مربع). */
    protected function angleOfChord(array $a, array $from, ?array $snp = null): float
    {
        $snp ??= $a['snp'];

        return $this->angleBetween($this->vec($snp, $from), $a['shoulder']);
    }

    /* ---------------------------------------------------------------------
     |  نشاندن مسیر روی قطعه
     * ------------------------------------------------------------------- */

    /**
     * مسیر تازه را روی قطعه می‌نشاند و همه چیز را مرتب می‌کند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function shape(array $piece, array $a, array $path, array $p): array
    {
        $oldCf = $a['cf'];
        $oldBottom = Geometry::bounds($piece['outline'])[3];

        $piece = $this->reshapeTop($piece, $a['neck_edge'], $path['points'], $path['tags'], [
            'consume_shoulder' => ! empty($path['consume_shoulder']),
            'armhole_t' => $path['armhole_t'] ?? null,
            'consume_before' => (int) ($path['consume_before'] ?? 0),
        ]);

        foreach ($path['markers'] ?? [] as $marker) {
            $piece['markers'][] = $marker;
        }

        foreach ($path['drills'] ?? [] as $drill) {
            $piece['drills'][] = $drill;
        }

        $piece['meta']['fold_edges'] = $this->foldEdgesOnCenter($piece);
        $piece = $this->refreshShoulderAnchors($piece);
        $piece = Geometry::normalizePiece($piece);

        $newCf = $this->pt(array_values($piece['outline'])[$a['neck_edge']] ?? $oldCf);
        $newBottom = Geometry::bounds($piece['outline'])[3];

        // اندازه‌گیری جابه‌جایی مرکز جلو نسبت به لبه پایین، تا از جابه‌جایی مبدأ مستقل باشد
        $drop = round(($oldBottom - $oldCf['y']) - ($newBottom - $newCf['y']), 3);

        $piece = $this->afterShape($piece, $a, $p, $path);

        if (! empty($path['bias'])) {
            $bounds = Geometry::bounds($piece['outline']);
            $span = min($bounds[2] - $bounds[0], $bounds[3] - $bounds[1]) * 0.5;
            $piece['grainline'] = $this->grainlineBetween(
                ['x' => $bounds[0] + 1.0, 'y' => $bounds[1] + 1.0 + $span],
                ['x' => $bounds[0] + 1.0 + $span, 'y' => $bounds[1] + 1.0],
                'مورب ۴۵ درجه',
            );
        }

        $piece['meta']['neck_length'] = round($this->neckLengthOf($piece), 2);
        $piece['meta']['neckline_style'] = static::key();
        $piece['meta']['neckline_drop'] = $drop;
        $piece['meta']['neckline_angle'] = $path['alpha'] ?? null;

        foreach ($path['meta'] ?? [] as $key => $value) {
            $piece['meta'][$key] = $value;
        }

        return $piece;
    }

    /**
     * قلاب پس از نشاندن مسیر؛ سبک‌هایی مثل یقه یک‌طرفه این‌جا قطعه را باز می‌کنند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function afterShape(array $piece, array $a, array $p, array $path): array
    {
        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  سجاف و نوار
     * ------------------------------------------------------------------- */

    /**
     * سجاف خط یقه: قطعه‌ای واقعی که لبه بیرونی‌اش خط یقه و لبه داخلی‌اش به اندازه
     * پهنای سجاف از آن فاصله دارد.
     *
     * @return array<string, mixed>|null
     */
    protected function facingPiece(array $piece, float $width): ?array
    {
        $edges = $this->edgesWithTag($piece, 'neck');

        if ($edges === [] || $width < 1.0) {
            return null;
        }

        $outline = array_values($piece['outline']);
        $path = [];

        foreach ($edges as $edge) {
            $steps = Geometry::isCurve($outline[($edge + 1) % count($outline)]) ? 6 : 2;

            for ($s = 0; $s <= $steps; $s++) {
                $on = Geometry::pointOnEdge($outline, $edge, $s / $steps);

                if ($path === [] || Geometry::distance($path[count($path) - 1], $on) > 0.05) {
                    $path[] = ['x' => $on['x'], 'y' => $on['y']];
                }
            }
        }

        if (count($path) < 3) {
            return null;
        }

        $centerX = Geometry::bounds($outline)[0];
        $hole = ['x' => $centerX, 'y' => $path[count($path) - 1]['y'] - 1.0];
        $inner = $this->offsetPath($path, $width, $hole);
        $shoulder = $this->edgeWithTag($piece, 'shoulder');

        $points = [];
        $tags = [];

        // هر نقطه با برچسب لبه‌ای که به آن می‌رسد افزوده می‌شود؛ نقطه‌های چسبیده حذف
        $push = function (array $point, string $tag) use (&$points, &$tags) {
            $point = Geometry::point((float) $point['x'], (float) $point['y']);

            if ($points !== [] && Geometry::distance($points[count($points) - 1], $point) < 0.2) {
                return;
            }

            if ($points !== []) {
                $tags[] = $tag;
            }

            $points[] = $point;
        };

        foreach ($path as $point) {
            $push($point, 'neck');
        }

        // کمی از سرشانه، تا سجاف در درز سرشانه گرفته شود
        if ($shoulder !== null) {
            $on = Geometry::pointOnEdge($outline, $shoulder, min(0.9, $width / max(1.0, Geometry::edgeLength($outline, $shoulder))));
            $push($on, 'shoulder');
        }

        $first = true;

        foreach (array_reverse($inner) as $point) {
            $push($point, $first ? 'shoulder' : 'hem');
            $first = false;
        }

        $tags[] = 'default'; // لبه بسته‌شدن روی خط مرکز

        if (count($points) < 4) {
            return null;
        }

        $facing = $this->newPiece([
            'code' => $piece['code'].'-neck-facing',
            'name' => 'سجاف خط یقه '.($this->sideOf($piece) === 'back' ? 'پشت' : 'جلو'),
            'cut_quantity' => (int) ($piece['cut_quantity'] ?? 1),
            'on_fold' => (bool) ($piece['on_fold'] ?? false),
            'mirror' => (bool) ($piece['mirror'] ?? false),
            'outline' => $points,
            'meta' => [
                'part' => 'facing',
                'facing_for' => $piece['code'],
                'edges' => $tags,
                'fold_edges' => [],
                'interfacing' => true,
                'width' => round($width, 2),
                'neckline_style' => static::key(),
            ],
        ]);

        $bounds = Geometry::bounds($facing['outline']);
        $facing['grainline'] = $this->grainline(
            $bounds[0] + (($bounds[2] - $bounds[0]) * 0.35),
            $bounds[1] + 0.8,
            $bounds[3] - 0.8,
        );
        $facing['meta']['fold_edges'] = $this->foldEdgesOnCenter($facing);

        return $facing;
    }

    /** نوار مورب دور خط یقه. */
    protected function bindingPiece(float $halfNeckline, float $width): array
    {
        $length = round(max(12.0, $halfNeckline * 2 * 0.97), 2);
        $height = round(max(1.5, $width) * 2, 2);

        $piece = $this->newPiece([
            'code' => 'neck-binding',
            'name' => 'نوار مورب خط یقه',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($length, 0),
                Geometry::point($length, $height),
                Geometry::point(0, $height),
            ],
            'markers' => [
                $this->marker('fold', 'خط تای نوار', 0, $height / 2, $length, $height / 2),
            ],
            'meta' => [
                'part' => 'binding',
                'edges' => ['neck', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'bias' => true,
                'neckline_length' => round($halfNeckline * 2, 2),
                'neckline_style' => static::key(),
                'note' => 'نوار روی مورب پارچه (۴۵ درجه) بریده شود.',
            ],
        ]);

        $piece['grainline'] = $this->grainlineBetween(
            ['x' => 1.0, 'y' => $height - 1.0],
            ['x' => 1.0 + $height - 2.0, 'y' => 1.0],
            'مورب ۴۵ درجه',
        );

        return $piece;
    }
}
