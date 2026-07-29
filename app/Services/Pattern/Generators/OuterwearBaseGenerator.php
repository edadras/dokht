<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایه مشترک کت، پالتو و کاپشن.
 *
 * تفاوت این خانواده با بقیهٔ کاتالوگ یک جملهٔ ساده است که همه‌چیز از آن درمی‌آید:
 * **لباس رویی روی لباس دیگری پوشیده می‌شود.** پیراهن روی تن می‌نشیند و آزادی‌اش
 * را از سلیقه می‌گیرد؛ پالتو باید روی پیراهن، روی بافت و گاهی روی ژاکت کلفت جا
 * شود و آزادی‌اش را از همان لایه‌ها می‌گیرد. اگر این حساب نشود، الگو روی کاغذ
 * درست است و روی تن، آستین از حلقه بیرون می‌زند و دکمه بسته نمی‌شود.
 *
 * پس چهار قاعده در همهٔ چهارده مدل این خانواده رعایت شده است:
 *
 *   ۱. آزادی از دو چیز درمی‌آید: پایهٔ خودِ مدل (کاپشن بایکر تنگ است و پافر
 *      گشاد) و لایه‌ای که زیرش پوشیده می‌شود. جمعشان در meta.cut_ease روی هر
 *      پنل پوسته می‌نشیند و در یادداشت هم با عدد گفته می‌شود؛ پنهان نمی‌ماند.
 *   ۲. هیچ‌جا از grow استفاده نمی‌شود. تمام آزادی داخل خودِ آرایهٔ ease می‌رود
 *      تا «دور تمام‌شده» دقیقاً برابر «دور بدن + آزادیِ اعلام‌شده» بماند و
 *      آزمون بتواند همان عدد را بسنجد.
 *   ۳. آستین از حلقهٔ اندازه‌گیری‌شدهٔ همین قطعه‌ها درفت می‌شود (کار
 *      outerGarment)، نه از عددی ثابت؛ حلقهٔ لباس رویی گودتر است و آستینی که
 *      برای حلقهٔ پیراهن بریده شده باشد در آن نمی‌نشیند.
 *   ۴. هرچه واقعاً درفت نشده — تاگل چوبی، خز کلاه، پُرِ پافر — در meta.notes
 *      گفته می‌شود و در meta.notions ثبت؛ نه اینکه نباشد و نه اینکه وانمود شود
 *      قطعه‌اش کشیده شده است.
 */
abstract class OuterwearBaseGenerator extends BodiceGarmentBase
{
    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'outerwear';
    }

    /** آزادی اضافه‌ای که هر لایهٔ زیر لازم دارد (سانتی‌متر روی دور سینه). */
    protected const UNDER_LAYER = [
        'none' => 0.0,
        'shirt' => 3.0,
        'knit' => 6.0,
        'heavy' => 9.0,
    ];

    /** توضیح فارسی هر لایه، برای یادداشت آزادی. */
    protected const LAYER_NOTE = [
        'none' => 'برای پوشیدن روی لباس زیر تنها',
        'shirt' => 'برای پوشیدن روی پیراهن یا تی‌شرت',
        'knit' => 'برای پوشیدن روی بافت یا ژاکت نازک',
        'heavy' => 'برای پوشیدن روی بافت ضخیم یا ژاکت کلفت',
    ];

    /**
     * بیشترین آزادی پذیرفتنی روی دور سینه.
     *
     * بالاتر از این، لباس دیگر روی شانه نمی‌ایستد و از تن می‌افتد؛ پافرِ پُر هم
     * حجمش را از پُر می‌گیرد نه از پهنای بی‌انتهای الگو.
     */
    protected const MAX_EASE = 26.0;

    /* ---------------------------------------------------------------------
     |  پارامترها
     * ------------------------------------------------------------------- */

    /**
     * پارامتر «زیر این لباس چه می‌پوشند».
     *
     * @return array<string, array<string, mixed>>
     */
    protected function layerParam(string $default = 'shirt'): array
    {
        return [
            'under_layer' => [
                'label' => 'زیر این لباس چه می‌پوشند', 'type' => 'select', 'default' => $default,
                'options' => [
                    'none' => 'چیزی جز لباس زیر نیست',
                    'shirt' => 'پیراهن یا تی‌شرت',
                    'knit' => 'بافت یا ژاکت نازک',
                    'heavy' => 'بافت ضخیم یا ژاکت کلفت',
                ],
                'hint' => 'لباس رویی روی لباس دیگر پوشیده می‌شود؛ آزادی این الگو از همین‌جا زیاد می‌شود.',
            ],
        ];
    }

    /**
     * پارامترهای مشترک این خانواده: درفت تنه + لایهٔ زیر + فرم لباس.
     *
     * @param  array<string, float>  $defaults  پیش‌فرض‌های درفت تنه
     * @param  array<string, array<string, mixed>>  $extra
     * @return array<string, array<string, mixed>>
     */
    protected function outerwearSchema(array $defaults = [], array $extra = [], string $fit = 'regular', string $layer = 'shirt'): array
    {
        return array_merge(
            $this->outerSchema($defaults),
            $this->layerParam($layer),
            $this->fitParam($fit),
            $extra,
        );
    }

    /**
     * برداشتن پارامترهایی که این مدل واقعاً از آن‌ها استفاده نمی‌کند.
     *
     * پارامتری که در فهرست بماند و بی‌اثر باشد، بدتر از نبودنش است: کاربر آن را
     * عوض می‌کند و هیچ اتفاقی نمی‌افتد.
     *
     * @param  array<string, array<string, mixed>>  $schema
     * @param  array<int, string>  $keys
     * @return array<string, array<string, mixed>>
     */
    protected function without(array $schema, array $keys): array
    {
        return array_diff_key($schema, array_flip($keys));
    }

    /* ---------------------------------------------------------------------
     |  آزادی
     * ------------------------------------------------------------------- */

    /**
     * آزادی این لباس رویی: پایهٔ مدل + لایهٔ زیر + فرم خواسته‌شده.
     *
     * خروجی مستقیم به blockMetrics می‌رود، پس «دور تمام‌شده» دقیقاً برابر
     * «دور بدن + همین عددها» درمی‌آید و هیچ آزادی پنهانی در کار نیست.
     *
     * @param  array<string, float>  $ease  آزادی خواسته‌شدهٔ کاربر (اگر داده باشد)
     * @return array<string, float>
     */
    protected function outerwearEase(array $ease, array $params, float $base, array $o = []): array
    {
        $layer = static::UNDER_LAYER[(string) $this->param($params, 'under_layer', 'shirt')] ?? 3.0;

        $fit = match ((string) $this->param($params, 'fit', 'regular')) {
            'fitted' => -3.0,
            'loose' => 4.0,
            default => 0.0,
        };

        $bust = max(5.0, min(static::MAX_EASE, $base + $layer + $fit));

        // کمر و باسن به همان اندازه باز می‌شوند مگر مدل جز این بخواهد؛ کتی که
        // سینه‌اش جا دارد و کمرش ندارد، روی حرکت دست قفل می‌شود.
        return array_merge($ease, [
            'bust' => round($bust, 2),
            'waist' => round($bust + (float) ($o['waist'] ?? 0), 2),
            'hip' => round($bust + (float) ($o['hip'] ?? 0), 2),
            // بازو هم لایهٔ زیر را با خود دارد؛ آستین تنگ، لباس رویی را بی‌مصرف می‌کند
            'bicep' => round((float) ($o['bicep'] ?? 9.0) + ($layer * 0.6), 2),
        ]);
    }

    /**
     * ثبت آزادیِ بریده‌شده روی پنل‌های پوسته.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float>  $ease
     * @return array<int, array<string, mixed>>
     */
    protected function stampEase(array $pieces, array $ease): array
    {
        $cut = [
            'bust' => round($this->ease($ease, 'bust', 0), 2),
            'waist' => round($this->ease($ease, 'waist', 0), 2),
            'hip' => round($this->ease($ease, 'hip', 0), 2),
            'bicep' => round($this->ease($ease, 'bicep', 0), 2),
        ];

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                continue;
            }

            $pieces[$index]['meta']['cut_ease'] = $cut;
        }

        return $pieces;
    }

    /**
     * یادداشتی که می‌گوید این لباس با چه آزادی‌ای بریده شده است.
     *
     * @param  array<string, float>  $ease
     */
    protected function easeNote(array $ease, array $params): string
    {
        $layer = (string) $this->param($params, 'under_layer', 'shirt');

        return 'این الگو با آزادی '.$this->fa(round($this->ease($ease, 'bust', 0), 1))
            .' سانتی‌متر روی دور سینه، '.$this->fa(round($this->ease($ease, 'waist', 0), 1))
            .' روی کمر و '.$this->fa(round($this->ease($ease, 'hip', 0), 1))
            .' روی باسن بریده شده است، '.(static::LAYER_NOTE[$layer] ?? static::LAYER_NOTE['shirt'])
            .'؛ آزادی بازو هم '.$this->fa(round($this->ease($ease, 'bicep', 0), 1)).' سانتی‌متر است.';
    }

    /**
     * یادداشت‌ها روی قطعهٔ اول می‌نشینند تا در کارت فنی و دستور دوخت دیده شوند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function outerNotes(array $pieces, array $notes): array
    {
        $pieces = array_values($pieces);

        if ($pieces === [] || $notes === []) {
            return $pieces;
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], array_values($notes));

        return $pieces;
    }

    /**
     * بستن کار یک لباس رویی: مهر آزادی، یادداشت‌ها و شماره‌گذاری.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float>  $g
     * @param  array<string, float>  $ease
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function finishOuterwear(array $pieces, array $g, array $ease, array $params, array $notes = []): array
    {
        $pieces = $this->stampEase(array_values(array_filter($pieces)), $ease);
        $pieces = $this->outerNotes($pieces, array_merge([$this->easeNote($ease, $params)], $notes));

        // grow همیشه صفر است: تمام آزادی داخل ease رفته، پس دور هدف همان
        // «دور بدن + آزادیِ اعلام‌شده» می‌ماند و آزمون همان را می‌بیند.
        return $this->finishBlock($pieces, $g, 0.0);
    }

    /* ---------------------------------------------------------------------
     |  یقهٔ برگردان پهن (پی‌کت و پالتو)
     * ------------------------------------------------------------------- */

    /**
     * یقهٔ برگردان پهن با نوک بلند (اولستر / پی‌کت).
     *
     * تفاوتش با یقهٔ برگردان معمولی فقط پهنا نیست: نوک یقه بیرون می‌زند و لبهٔ
     * درزِ گردن پرتر منحنی می‌شود، وگرنه یقه‌ای که این‌قدر پهن است روی شانه
     * نمی‌خوابد و پشت گردن بالا می‌زند.
     *
     * @return array<string, mixed>
     */
    protected function ulsterCollarPiece(float $halfNeck, float $height, array $o = []): array
    {
        $length = max(10.0, $halfNeck);
        $point = (float) ($o['point'] ?? 6.0);
        $height = max(6.0, $height);

        $outline = [
            Geometry::point(0, 0),
            Geometry::curve($length, 0.9, $length * 0.5, 0.25),
            Geometry::point($length + $point, $height * 0.58),
            Geometry::curve(0, $height, $length * 0.5, $height + 1.4),
        ];

        return $this->piece([
            'code' => ($o['prefix'] ?? '').'collar',
            'name' => $o['name'] ?? 'یقه برگردان پهن',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, 1.5, $height - 1),
            'notches' => [$this->notch(0, $height, 3, 'مرکز پشت یقه', 'collar_center')],
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', 0, 0, 0, $height),
                $this->marker('roll_line', 'خط برگردان یقه', 0, $height * 0.45, $length * 0.9, $height * 0.62),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => ['default', 'side', 'neck', 'default'],
                'fold_edges' => [3],
                'neck_length' => round(Geometry::edgeLength($outline, 2), 2),
                'target_neck' => round($halfNeck, 2),
                'interfacing' => true,
                'girth_role' => 'trim',
                'notes' => [
                    'یقه دولا بریده می‌شود؛ لایهٔ رو نیم سانتی‌متر بزرگ‌تر بریده شود تا درزِ دور یقه به سمت زیر بچرخد.',
                ],
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  کلاه
     * ------------------------------------------------------------------- */

    /**
     * کلاه با اندازه‌ای که از خودِ یقهٔ همین لباس درمی‌آید.
     *
     * کلاهِ عددِ ثابت روی بدن کودک مثل گونی است و روی بدن بلندقد تنگ؛ پس پهنا و
     * بلندی هر دو از طول یقه حساب می‌شوند و اختلاف باقی‌مانده صادقانه گزارش
     * می‌شود، نه اینکه پنهان شود.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function hoodSet(array $g, float $halfNeck, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? '');
        $width = max(16.0, $halfNeck + (float) ($o['width_extra'] ?? 6.0));
        $height = max(24.0, $halfNeck * (float) ($o['height_ratio'] ?? 2.0));

        $hood = $this->hoodPiece($g, $halfNeck, [
            'prefix' => $prefix,
            'width' => $width,
            'height' => $height,
            'face' => (float) ($o['face'] ?? 4.0),
            'name' => $o['name'] ?? 'کلاه',
        ]);

        $difference = round(((float) ($hood['meta']['neck_length'] ?? 0)) - $halfNeck, 1);

        $hood['meta']['notes'] = array_merge($hood['meta']['notes'] ?? [], [
            'لبهٔ گردنیِ کلاه '.$this->fa(round((float) ($hood['meta']['neck_length'] ?? 0), 1))
                .' و نیم‌یقهٔ لباس '.$this->fa(round($halfNeck, 1)).' سانتی‌متر است؛ اختلاف '
                .$this->fa(abs($difference)).' سانتی‌متر هنگام دوخت روی همان لبه پخش می‌شود.',
        ]);

        $pieces = [$hood];

        if ($o['facing'] ?? true) {
            $pieces[] = $this->bandPiece(
                $prefix.'hood-facing',
                'سجاف لبهٔ صورت کلاه',
                $height + 4,
                (float) ($o['facing_width'] ?? 7),
                ['cut' => 2, 'part' => 'facing', 'meta' => [
                    'interfacing' => false,
                    'notes' => ['روی لبهٔ صورتِ کلاه دوخته می‌شود و جای بند کلاه را می‌سازد.'],
                ]],
            );
        }

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  شنل
     * ------------------------------------------------------------------- */

    /**
     * پنل شنل: از سرشانه آزاد می‌ریزد و اصلاً حلقهٔ آستین ندارد.
     *
     * دو نکته که شنل را از «کتِ بی‌آستین» جدا می‌کند و اگر رعایت نشوند شنل
     * دوخته نمی‌شود:
     *
     *   الف) خط زیر بغل گود نمی‌شود. پارچه از نوک سرشانه با یک منحنی نرم به
     *        پهنای کامل می‌رسد و از آن‌جا تا دم باز می‌شود.
     *   ب) شیب سرشانهٔ جلو و پشت عمداً یکی است. شنل جز درز پهلو درزی ندارد؛ اگر
     *      شیب دو طرف فرق کند، همان یک درز هم‌اندازه درنمی‌آید.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function capePanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $ext = (float) ($o['extension'] ?? 0);
        $cf = $ext;

        $qb = $g['quarter_bust'];
        $neckW = $g['neck_width'] + (float) ($o['neck_width_extra'] ?? 0);
        $neckD = ($front ? $g['front_neck_depth'] : $g['back_neck_depth']) + (float) ($o['neck_depth_extra'] ?? 0);
        $shoulderX = min($g['shoulder_half'], $qb - 2.0);
        $shoulderY = $g['shoulder_drop'];
        $bustY = $g['bust_y'];
        $bottomY = $g['side_waist_y'] + max(10.0, (float) ($o['length'] ?? 70));
        $flare = max(0.0, (float) ($o['flare'] ?? 30));
        $hemX = $cf + $qb + $flare;

        $outline = [];
        $edges = [];

        if ($ext > 0) {
            $outline[] = Geometry::point(0, $neckD);
            $outline[] = Geometry::point($cf, $neckD);
            $edges[] = 'default';
        } else {
            $outline[] = Geometry::point($cf, $neckD);
        }

        $outline[] = Geometry::curve(
            $cf + $neckW,
            0,
            $cf + ($neckW * ($front ? 0.10 : 0.34)),
            $neckD * ($front ? 0.10 : 0.28),
        );
        $edges[] = 'neck';

        $outline[] = Geometry::point($cf + $shoulderX, $shoulderY);
        $edges[] = 'shoulder';

        $outline[] = Geometry::curve(
            $cf + $qb,
            $bustY,
            $cf + $shoulderX + 1.2,
            $shoulderY + (($bustY - $shoulderY) * 0.55),
        );
        $edges[] = 'side';

        $outline[] = Geometry::point($hemX, $bottomY);
        $edges[] = 'side';

        $outline[] = Geometry::point(0, $bottomY);
        $edges[] = 'hem';
        $edges[] = 'default';

        $onFold = (bool) ($o['on_fold'] ?? ($ext <= 0));

        // شکاف دست: جای بیرون آمدن دست، روی خودِ پنل جلو
        $slitTop = $bustY + max(4.0, (float) ($o['slit_drop'] ?? 6));
        $slitLength = max(12.0, (float) ($o['slit'] ?? 24));
        $slitBottom = min($bottomY - 4.0, $slitTop + $slitLength);
        $slitX = $cf + ($qb * 0.72);

        $markers = [
            $this->marker('bust', 'خط زیر بغل', $cf, $bustY, $cf + $qb),
            $this->marker($front ? 'cf' : 'cb', $front ? 'خط مرکز جلو' : 'خط مرکز پشت', $cf, $neckD, $cf, $bottomY),
        ];

        if ($front && $slitBottom > $slitTop + 6) {
            $markers[] = $this->marker('slit', 'شکاف حلقهٔ دست', $slitX, $slitTop, $slitX, $slitBottom);
        }

        return $this->finishPanel([
            'code' => $o['code'] ?? (($o['prefix'] ?? '').($front ? 'front' : 'back')),
            'name' => $o['name'] ?? ($front ? 'تنه جلوی شنل' : 'تنه پشت شنل'),
            'cut' => (int) ($o['cut'] ?? ($onFold ? 1 : 2)),
            'mirror' => ! $onFold,
            'layer' => $o['layer'] ?? 'outer',
            'girth_role' => $o['girth_role'] ?? (($o['layer'] ?? 'outer') === 'lining' ? 'lining' : 'shell'),
        ], $outline, $edges, [
            'lines' => ['bust' => $bustY],
            'center_x' => $cf,
            'on_fold' => $onFold,
            'fold_edges' => $onFold ? [count($outline) - 1] : [],
            'grainline' => $this->grainline($cf + ($qb * 0.4), max(1.5, $bustY - 6), $bottomY - 4),
            'notches' => [
                $this->notch($cf + $qb, $bustY, $ext > 0 ? 4 : 3, 'خط زیر بغل روی درز پهلو', 'underarm'),
            ],
            'markers' => $markers,
            'meta' => array_merge([
                'part' => $front ? 'front_bodice' : 'back_bodice',
                'side' => $front ? 'front' : 'back',
                'shape' => 'cape',
                // شنل حلقهٔ آستین ندارد و این را صریح می‌گوید؛ وگرنه هر بازرسی
                // فکر می‌کند حلقه‌اش گم شده است.
                'sleeveless' => true,
                'bust_y' => round($bustY, 2),
                'waist_y' => round($g['side_waist_y'], 2),
                'arm_slit' => $front ? round($slitBottom - $slitTop, 2) : 0.0,
            ], $o['meta'] ?? []),
        ]);
    }

    /* ---------------------------------------------------------------------
     |  برش پنلی
     * ------------------------------------------------------------------- */

    /**
     * بریدن یوک (پنل بالا) از یک پنل تنه.
     *
     * برش با همان موتور آزمودهٔ برش افقی انجام می‌شود، پس برچسب لبه‌ها، ساسون‌ها
     * و تای پارچه خودشان درست می‌مانند. حلقهٔ آستین میان دو قطعه تقسیم می‌شود و
     * هر دو تکه سهم خودشان را نگه می‌دارند.
     *
     * @param  array<string, mixed>  $panel
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>}
     */
    protected function panelYoke(array $panel, float $y, array $o = []): array
    {
        $onFold = (bool) ($panel['on_fold'] ?? false);
        $cut = (int) ($panel['cut_quantity'] ?? 1);
        $centerX = (float) ($o['center_x'] ?? 0.0);

        $parts = $this->cutPanelAt($panel, $y, [
            'code' => $o['yoke_code'] ?? (($panel['code'] ?? 'panel').'-yoke'),
            'name' => $o['yoke_name'] ?? 'یوک',
            'cut' => $cut,
            'on_fold' => $onFold,
            'cut_tag' => 'default',
            'lines' => [],
            'center_x' => $centerX,
            'meta' => [
                'part' => 'yoke',
                'girth_role' => 'trim',
                'style_line' => 'yoke',
                'waist_y' => null,
                'notes' => [$o['yoke_note'] ?? 'این پنل روی تنه دوخته می‌شود؛ درزش را پیش از بستن درز پهلو ببندید.'],
            ],
        ], [
            'code' => $panel['code'] ?? 'panel',
            'name' => $panel['name'] ?? 'تنه',
            'cut' => $cut,
            'on_fold' => $onFold,
            'cut_tag' => 'default',
            'lines' => $o['lines'] ?? [],
            'center_x' => $centerX,
            'meta' => [
                'part' => $panel['meta']['part'] ?? 'front_bodice',
                'style_line' => 'yoke',
            ],
        ]);

        if (count($parts) !== 2) {
            return [null, $panel];
        }

        return [$parts[0], $parts[1]];
    }

    /* ---------------------------------------------------------------------
     |  نشانه‌های بست و دوخت
     * ------------------------------------------------------------------- */

    /**
     * دو ردیف دکمه، قرینه دو طرف خط مرکز جلو.
     *
     * تفاوتش با تک‌ردیف فقط شمار دکمه نیست: در دوردیفه، خط مرکز جلو میان دو
     * ردیف می‌ماند و اضافهٔ جای دکمه باید دست‌کم به اندازهٔ فاصلهٔ هر ردیف تا
     * مرکز باشد، وگرنه دکمهٔ ردیف بیرونی روی هوا می‌افتد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markDoubleBreast(array $piece, float $stand, float $fromY, float $toY, int $rows): array
    {
        if ($rows < 1 || $toY - $fromY < 2 || $stand < 2) {
            return $piece;
        }

        $spread = max(1.5, min($stand - 1.0, $stand * 0.72));
        $step = $rows > 1 ? ($toY - $fromY) / ($rows - 1) : 0.0;

        foreach ([['left', $stand - $spread], ['right', $stand + $spread]] as [$side, $x]) {
            for ($i = 0; $i < $rows; $i++) {
                $piece['drills'][] = [
                    'key' => 'button_'.$side.'_'.($i + 1),
                    'label' => ($side === 'left' ? 'دکمه ردیف چپ ' : 'دکمه ردیف راست ').$this->fa($i + 1),
                    'x' => round($x, 2),
                    'y' => round($fromY + ($step * $i), 2),
                ];
            }
        }

        $piece['meta']['double_breasted'] = true;
        $piece['meta']['buttons'] = $rows * 2;
        $piece['meta']['notions'][] = [
            'type' => 'button',
            'label' => 'دکمهٔ دوردیفه',
            'count' => $rows * 2,
        ];
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'دو ردیف دکمه هرکدام '.$this->fa(round($spread, 1)).' سانتی‌متر از خط مرکز جلو فاصله دارند؛ '
                .'ردیف روییِ سمت راست جادکمه می‌خورد و ردیف چپ فقط دکمه.',
        ]);

        return $piece;
    }

    /**
     * زیپ اریب (کاپشن بایکر).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markDiagonalZip(array $piece, float $topX, float $topY, float $bottomX, float $bottomY, string $label = 'زیپ اریب جلو'): array
    {
        $length = round(sqrt((($bottomX - $topX) ** 2) + (($bottomY - $topY) ** 2)), 1);

        if ($length < 10) {
            return $piece;
        }

        $piece['markers'][] = $this->marker('zip', $label, $topX, $topY, $bottomX, $bottomY);
        $piece['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'زیپ جداشوندهٔ اریب جلو',
            'count' => 1,
            'length' => $length,
        ];
        $piece['meta']['zip_length'] = $length;

        return $piece;
    }

    /**
     * خطوط دوخت کاناله (بافل) روی یک پنل.
     *
     * پافر فرمش را از همین خط‌ها می‌گیرد: هر کانال یک لولهٔ بسته می‌شود که پُر
     * داخلش می‌ماند و جابه‌جا نمی‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function baffleLines(array $piece, float $spacing, float $from = 0.0, float $margin = 4.0): array
    {
        [$minX, $minY, , $maxY] = Geometry::bounds($piece['outline'] ?? []);
        $spacing = max(4.0, $spacing);
        $start = max($minY + $margin, $from);
        $count = 0;

        for ($y = $start; $y <= $maxY - $margin; $y += $spacing) {
            $width = $this->panelWidthAt($piece, $y);

            if ($width < 3.0) {
                continue;
            }

            $count++;
            $piece['markers'][] = $this->marker(
                'baffle',
                'خط دوخت کاناله '.$this->fa($count),
                $minX,
                round($y, 2),
                $minX + $width,
            );
        }

        $piece['meta']['baffles'] = $count;
        $piece['meta']['baffle_spacing'] = round($spacing, 2);

        return $piece;
    }

    /**
     * جای بند کشی (کمر پارکا، دم آنوراک، لبهٔ صورت کلاه).
     *
     * پارچه با بند جمع می‌شود ولی الگو به اندازهٔ **باز** بریده می‌شود؛ همین
     * جمع‌شدن در meta.gathers ثبت می‌شود تا کسی آن را با کوچک‌کردن الگو اشتباه
     * نگیرد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markDrawcord(array $piece, string $tag, float $draw, string $label, float $cordLength): array
    {
        // بندِ کمر روی هیچ لبه‌ای نیست؛ وسط خودِ پنل می‌افتد. پس اگر برچسبی
        // داده نشود، جمع‌شدگی بدون شمارهٔ لبه ثبت می‌شود — نه اینکه به لبه‌ای
        // نامربوط چسبانده شود.
        $edges = $tag === '' ? [] : Geometry::edgesWithTag($piece, $tag);

        if ($draw > 0.5) {
            $piece['meta']['gathers'][] = [
                'edge' => $edges === [] ? null : $edges[0],
                'amount' => round($draw, 2),
                'label' => $label,
            ];
        }

        $piece['meta']['notions'][] = [
            'type' => 'cord',
            'label' => $label,
            'count' => 1,
            'length' => round($cordLength, 1),
        ];
        $piece['meta']['notions'][] = [
            'type' => 'eyelet',
            'label' => 'مغزی سرِ '.$label,
            'count' => 2,
        ];
        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            $label.' پارچه را تا '.$this->fa(round($draw, 1)).' سانتی‌متر جمع می‌کند؛ الگو به اندازهٔ باز بریده می‌شود، نه جمع‌شده.',
        ]);

        return $piece;
    }

    /* ---------------------------------------------------------------------
     |  جیب
     * ------------------------------------------------------------------- */

    /**
     * جیب مغزی‌دار (کت و پالتو): مغزی + دو کیسه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function weltPocketSet(float $width, float $depth, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? '');
        $welt = max(1.5, (float) ($o['welt'] ?? 3.0));

        return [
            $this->bandPiece($prefix.'welt', $o['name'] ?? 'مغزی جیب', $width + 3, $welt * 2, [
                'cut' => (int) ($o['cut'] ?? 2), 'part' => 'pocket', 'fold_line' => true,
                'meta' => [
                    'interfacing' => true,
                    'notes' => ['از وسط تا می‌شود و دهانهٔ جیب را می‌سازد؛ لایی روی نیمهٔ رو می‌خورد.'],
                ],
            ]),
            $this->bandPiece($prefix.'pocket-bag', 'کیسه جیب', $width + 3, max(10.0, $depth), [
                'cut' => (int) ($o['bag_cut'] ?? 4), 'part' => 'pocket',
                'meta' => ['notes' => ['دو کیسه برای هر جیب؛ از آستر یا پارچهٔ نازک بریده می‌شود.']],
            ]),
        ];
    }

    /**
     * جیب کارگو: کیسهٔ رودوزی + نوار جان‌دار (اکاردئونی) + درپوش.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function cargoPocketSet(float $width, float $height, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? '');
        $depth = max(1.5, (float) ($o['depth'] ?? 3.5));
        $cut = (int) ($o['cut'] ?? 2);

        $pocket = $this->patchPocketPiece($width, $height, [
            'prefix' => $prefix,
            'name' => $o['name'] ?? 'جیب کارگو',
            'cut' => $cut,
        ]);

        $pocket['meta']['notes'] = array_merge($pocket['meta']['notes'] ?? [], [
            'این جیب جان‌دار است: نوار کناری آن را از تنه فاصله می‌دهد، پس دور تا دور جیب '
                .$this->fa(round($depth, 1)).' سانتی‌متر عمق پیدا می‌کند.',
        ]);

        return [
            $pocket,
            $this->bandPiece($prefix.'pocket-gusset', 'نوار جان‌دار جیب', $width + (2 * $height), $depth, [
                'cut' => $cut, 'part' => 'pocket',
                'meta' => ['notes' => ['دور سه لبهٔ جیب می‌چرخد؛ سرش را روی لبهٔ بالای جیب تمام کنید.']],
            ]),
            $this->bandPiece($prefix.'pocket-flap', 'درپوش جیب', $width + 1, max(6.0, (float) ($o['flap'] ?? 5.5)) * 2, [
                'cut' => $cut * 2, 'part' => 'pocket', 'fold_line' => true,
                'meta' => [
                    'interfacing' => true,
                    'notions' => [['type' => 'snap', 'label' => 'دکمهٔ فشاری درپوش جیب', 'count' => 1, 'per_cut' => false]],
                    'notes' => ['دو لایه برای هر درپوش؛ از وسط تا می‌شود و لبه‌اش را برمی‌گردانید.'],
                ],
            ]),
        ];
    }

    /* ---------------------------------------------------------------------
     |  بست‌های ویژه
     * ------------------------------------------------------------------- */

    /**
     * بند و تاگل پالتو دافل.
     *
     * خودِ تاگل چوبی خریدنی است و درفت نمی‌شود؛ آنچه اینجا ساخته می‌شود
     * نوارهای چرمی‌اند که تاگل روی آن‌ها می‌نشیند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toggleSet(int $count, array $o = []): array
    {
        $prefix = (string) ($o['prefix'] ?? '');
        $count = max(2, min(6, $count));

        $loop = $this->bandPiece($prefix.'toggle-loop', 'نوار حلقهٔ تاگل', (float) ($o['loop'] ?? 16), 2.5, [
            'cut' => $count, 'part' => 'belt',
            'meta' => [
                'notions' => [
                    ['type' => 'button', 'label' => 'تاگل چوبی (شاخی)', 'count' => $count],
                ],
                'notes' => [
                    'تاگل چوبی خریدنی است و در الگو کشیده نمی‌شود؛ این نوار همان چیزی است که تاگل رویش دوخته می‌شود.',
                ],
            ],
        ]);

        $stay = $this->bandPiece($prefix.'toggle-stay', 'نوار پایهٔ تاگل', (float) ($o['stay'] ?? 9), 2.5, [
            'cut' => $count, 'part' => 'belt',
            'meta' => ['notes' => ['روی سمت مقابل دوخته می‌شود و حلقه دورش می‌افتد.']],
        ]);

        return [$loop, $stay];
    }
}
