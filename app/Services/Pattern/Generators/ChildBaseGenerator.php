<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\DraftsPants;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;

/**
 * پایه مشترک لباس کودک.
 *
 * بدن کودک، بدنِ کوچک‌شده بزرگسال نیست و اگر مثل بزرگسال درفت شود، لباس نه
 * پوشیده می‌شود نه می‌ایستد. چهار تفاوت، چهار تصمیم در الگو:
 *
 *   ۱. سر نسبت به تن بزرگ است. سرِ کودکِ صد و شانزده سانتی نزدیک به دور سرِ یک
 *      بزرگسال است، در حالی که یقه‌اش نصف اوست. پس هر لباسی که از سر پوشیده
 *      می‌شود باید یقه‌اش را با دور سر بسنجد، نه با دور گردن. دور سر در
 *      اندازه‌های سامانه نیست، پس از روی قد تخمین زده و در meta.notes صادقانه
 *      اعلام می‌شود. اگر یقه به دور سر نرسید، چاک یا قزن اضافه می‌شود؛ لباس
 *      کودکی که از سر رد نشود، اصلاً لباس نیست.
 *   ۲. کمر فرورفتگی ندارد. دور کمر کودک تقریباً با دور سینه و باسنش یکی است، پس
 *      ساسون کمر و کمرگیریِ درز پهلو در این خانواده اصلاً کشیده نمی‌شود؛ اگر
 *      کشیده شود لباس روی شکم می‌ایستد و بالا می‌زند.
 *   ۳. شکم جلو آمده است. همان صفر شدنِ کمرگیری این را حل می‌کند، به‌علاوه آزادی
 *      یکنواختی که روی هر سه دور می‌نشیند.
 *   ۴. کودک رشد می‌کند. «آزادی رشد» یک پارامتر جداست تا کاربر بداند چقدر از
 *      گشادی لباس برای امروز است و چقدر برای فصل بعد.
 */
abstract class ChildBaseGenerator extends BodiceGarmentBase
{
    use DraftsPants;

    /** گروه فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'child';
    }

    /**
     * دور سر بر پایه قد (سانتی‌متر).
     *
     * سامانه دور سر را نمی‌پرسد، پس این جدول از داده رشد استفاده می‌کند: نوزادِ
     * شصت سانتی دور سر ۴۱ و بزرگسال ۵۶ سانتی‌متر. نکته همین‌جاست که کار را از
     * درفت بزرگسال جدا می‌کند: دور سر با قد خطی بالا نمی‌رود، خیلی زودتر
     * می‌ایستد. پس هرچه کودک کوچک‌تر، نسبت سر به یقه بدتر.
     *
     * @var array<int, array{0: float, 1: float}>
     */
    protected const HEAD_TABLE = [
        [60, 41.0], [70, 44.0], [80, 47.0], [92, 49.0], [104, 51.0],
        [116, 52.5], [128, 53.5], [140, 54.5], [152, 55.5], [168, 56.5],
    ];

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای درفت بالاتنه کودک.
     *
     * @param  array<string, float>  $defaults
     * @return array<string, array<string, mixed>>
     */
    protected function childSchema(array $defaults = []): array
    {
        return $this->baseSchema(
            array_merge([
                'shoulder_slope' => 2.5,
                'neck_width_extra' => 1.5,
                'front_neck_depth_extra' => 1,
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 2,
            ], $defaults),
            ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra'],
        );
    }

    /**
     * آزادی بازی و آزادی رشد، جدا از هم.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function childEaseSchema(float $play = 1.5, float $growth = 2): array
    {
        return [
            'play_ease' => [
                'label' => 'آزادی بازی (هر نیم‌قطعه)', 'min' => 0, 'max' => 8, 'step' => 0.5,
                'default' => $play, 'unit' => 'سانتی‌متر',
                'hint' => 'کودک باید بتواند بدود، بنشیند و دست‌هایش را بالا ببرد.',
            ],
            'growth' => [
                'label' => 'آزادی رشد', 'min' => 0, 'max' => 8, 'step' => 0.5,
                'default' => $growth, 'unit' => 'سانتی‌متر',
                'hint' => 'به بلندی تنه و آستین اضافه می‌شود تا لباس یک فصل بیشتر اندازه بماند.',
            ],
        ];
    }

    /** جمع آزادی بازی و رشد روی «یک‌چهارم» بدن. */
    protected function childGrow(array $params): float
    {
        return (float) $this->param($params, 'play_ease', 1.5) + ((float) $this->param($params, 'growth', 2) / 4);
    }

    /* ---------------------------------------------------------------------
     |  رد شدن یقه از سر
     * ------------------------------------------------------------------- */

    /** دور سر تخمینی این بدن. */
    protected function headGirth(array $m): float
    {
        $height = $this->m($m, 'height', 116);
        $table = static::HEAD_TABLE;
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
     * دور یقه تمام‌شده برای یک عرض و گودی داده‌شده.
     *
     * منحنی یقه دقیقاً با همان نقطه‌های کنترلی ساخته می‌شود که bodyPanel می‌سازد،
     * پس عددی که این‌جا درمی‌آید همان عددی است که روی الگو بریده می‌شود. هر پنل
     * نیم‌قطعه است، پس دور کامل دو برابر جمع جلو و پشت است.
     *
     * @param  array<string, float>  $g
     */
    protected function neckGirth(array $g, float $widthExtra = 0.0, float $frontDepthExtra = 0.0): float
    {
        $frontW = $g['neck_width'] + $widthExtra;
        // همان سه میلی‌متری که bodyPanel به عرض یقه پشت اضافه می‌کند
        $backW = $frontW + 0.3;
        $frontD = $g['front_neck_depth'] + $frontDepthExtra;
        $backD = $g['back_neck_depth'];

        $front = Geometry::edgeLength([
            Geometry::point(0, $frontD),
            Geometry::curve($frontW, 0, $frontW * 0.10, $frontD * 0.10),
        ], 0);

        $back = Geometry::edgeLength([
            Geometry::point(0, $backD),
            Geometry::curve($backW, 0, $backW * 0.34, $backD * 0.28),
        ], 0);

        return round(2 * ($front + $back), 2);
    }

    /**
     * یقه‌ای که از سر کودک رد شود.
     *
     * اول عرض یقه باز می‌شود (تا جایی که خط سرشانه برنگردد و به پهنای جلوی سینه
     * نزند)، بعد گودی یقه جلو. اگر باز هم نرسید، چاک مرکز پشت با قزن اضافه
     * می‌شود؛ یقه‌ای که از سر رد نمی‌شود، بی‌صدا رها نمی‌شود.
     *
     * گزینه shoulder_extra باید همان عددی باشد که به پنل داده می‌شود: اگر سرشانه
     * باریک‌تر درفت شود و سقف یقه از آن خبر نداشته باشد، عرض یقه از نوک سرشانه
     * بیرون می‌زند و مسیر قطعه خودش را قطع می‌کند.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array{width_extra: float, front_depth_extra: float, neck_girth: float, head: float, slit: float}
     */
    protected function headClearance(array $g, array $m, array $o = []): array
    {
        $head = $this->headGirth($m);

        // لباسی که از جلو باز می‌شود از سر پوشیده نمی‌شود، پس یقه‌اش را زورکی
        // پهن نمی‌کنیم؛ ولی عددها را همان‌جا هم اعلام می‌کنیم
        if (($o['required'] ?? true) === false) {
            return [
                'width_extra' => 0.0,
                'front_depth_extra' => 0.0,
                'neck_girth' => $this->neckGirth($g),
                'head' => $head,
                'slit' => 0.0,
            ];
        }

        $target = $head + (float) ($o['margin'] ?? 2.0);
        $shoulder = $g['shoulder_half'] + (float) ($o['shoulder_extra'] ?? 0);

        $ceiling = max(0.0, min($shoulder - 2.0, $g['across_chest'] - 0.8) - $g['neck_width']);
        $maxDepth = max(0.0, (float) ($o['max_depth'] ?? 5.0));

        $width = 0.0;
        $depth = 0.0;

        while ($width < $ceiling && $this->neckGirth($g, $width, $depth) < $target) {
            $width = min($ceiling, $width + 0.25);
        }

        while ($depth < $maxDepth && $this->neckGirth($g, $width, $depth) < $target) {
            $depth = min($maxDepth, $depth + 0.25);
        }

        $girth = $this->neckGirth($g, $width, $depth);

        return [
            'width_extra' => round($width, 2),
            'front_depth_extra' => round($depth, 2),
            'neck_girth' => $girth,
            'head' => $head,
            // چاک باید همان کمبود دور را جبران کند؛ هر سانتی‌متر چاک، دو
            // سانتی‌متر به دهانه یقه اضافه می‌کند چون یقه از دو طرف باز می‌شود
            'slit' => $girth >= $target ? 0.0 : round(max(4.0, ($target - $girth) / 2) + 1.0, 1),
        ];
    }

    /**
     * ثبت نتیجه سنجش سر روی قطعه‌ها.
     *
     * روی قطعه پشت، اگر چاک لازم بود، خط نشانه و قزن گذاشته می‌شود و در هر حال
     * عددها (دور سر تخمینی و دور یقه تمام‌شده) روی meta می‌نشیند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array{width_extra: float, front_depth_extra: float, neck_girth: float, head: float, slit: float}  $clearance
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function stampHeadClearance(array $pieces, array $clearance, array $g, array $o = []): array
    {
        $note = 'دور سر در اندازه‌های سامانه نیست و از روی قد تخمین زده شد: '
            .$this->fa($clearance['head']).' سانتی‌متر. دور یقه تمام‌شده '
            .$this->fa($clearance['neck_girth']).' سانتی‌متر درفت شد.';

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                continue;
            }

            $pieces[$index]['meta']['head_clearance'] = [
                'head' => $clearance['head'],
                'neck_girth' => $clearance['neck_girth'],
                'passes' => $clearance['slit'] <= 0.0,
                'slit' => $clearance['slit'],
            ];
        }

        $back = null;

        foreach ($pieces as $index => $piece) {
            if (($piece['meta']['part'] ?? '') === 'back_bodice') {
                $back = $index;
                break;
            }
        }

        if ($clearance['slit'] > 0.0 && $back !== null) {
            $slit = (float) $clearance['slit'];
            $top = (float) $g['back_neck_depth'];

            $pieces[$back]['markers'][] = $this->marker('slit', 'چاک مرکز پشت', 0, $top, 0, $top + $slit);
            $pieces[$back]['meta']['back_slit'] = $slit;
            $pieces[$back]['meta']['notions'][] = [
                'type' => (string) ($o['notion'] ?? 'snap'),
                'label' => 'قزن سرِ چاک پشت',
                'count' => (int) ($o['notion_count'] ?? 1),
            ];

            $note .= ' چون یقه به دور سر نمی‌رسید، چاک '.$this->fa($slit)
                .' سانتی‌متری مرکز پشت با قزن باز شد.';
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [$note]);

        return $pieces;
    }

    /* ---------------------------------------------------------------------
     |  بادی: پنل سرشانه تا فاق
     * ------------------------------------------------------------------- */

    /**
     * پنل بادی: از سرشانه تا فاق، با خط پای منحنی و زبانه فاق.
     *
     * چرا از bodyPanel ساخته نشد: پنل بالاتنه لبه پایینِ افقی دارد و بادی
     * لبه پایینش سه تکه است — درز پهلو تا خط پا، منحنی خط پا، و زبانه فاق.
     * همان منحنی است که بادی را روی ران باز می‌گذارد و پوشک را جا می‌دهد.
     *
     * گزینه‌ها: side، grow، neck_width_extra، neck_depth_extra، armhole_drop،
     * shoulder_extra، rise (قد فاق از خط کمر)، leg_rise، tab (پهنای زبانه فاق)،
     * crotch_extra، code، name، meta.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    protected function bodysuitPanel(array $g, array $o = []): array
    {
        $front = ($o['side'] ?? 'front') === 'front';
        $grow = (float) ($o['grow'] ?? 0);

        $qb = $g['quarter_bust'] + $grow;
        $bustY = $g['bust_y'] + (float) ($o['armhole_drop'] ?? 0);
        $neckW = $g['neck_width'] + (float) ($o['neck_width_extra'] ?? 0) + ($front ? 0.0 : 0.3);
        $neckD = ($front ? $g['front_neck_depth'] : $g['back_neck_depth']) + (float) ($o['neck_depth_extra'] ?? 0);
        $shoulderX = min($g['shoulder_half'] + (float) ($o['shoulder_extra'] ?? 0), $qb - 0.6);
        $shoulderY = $g['shoulder_drop'] + ($front ? 0.0 : 0.5);
        $across = min($qb - 3.0, ($front ? $g['across_chest'] : $g['across_back']) + ($grow * 0.5));
        $acrossY = $shoulderY + (($bustY - $shoulderY) * 0.62);

        $rise = max(10.0, (float) ($o['rise'] ?? 24));
        $crotchY = $g['side_waist_y'] + $rise + ($front ? (float) ($o['crotch_extra'] ?? 0) : 0.0);
        $tabHalf = max(2.5, (float) ($o['tab'] ?? 7) / 2);
        $legY = max($bustY + 4.0, $g['side_waist_y'] + $rise - max(4.0, (float) ($o['leg_rise'] ?? 6)));

        $outline = [
            Geometry::point(0, $neckD),
            Geometry::curve($neckW, 0, $neckW * ($front ? 0.10 : 0.34), $neckD * ($front ? 0.10 : 0.28)),
            Geometry::point($shoulderX, $shoulderY),
            Geometry::curve($across, $acrossY, $shoulderX + 0.4, $shoulderY + (($acrossY - $shoulderY) * 0.62)),
            Geometry::curve($qb, $bustY, $across + (($qb - $across) * 0.16), $bustY - (($bustY - $acrossY) * 0.06)),
            Geometry::point($qb, $legY),
            // خط پا: از درز پهلو به داخل و پایین جارو می‌شود؛ نقطه کنترل بالا و
            // چپ می‌نشیند تا پارچه از لای پا برداشته شود، نه اضافه بیاید
            Geometry::curve(
                $tabHalf,
                $crotchY,
                $tabHalf + (($qb - $tabHalf) * 0.25),
                $legY + (($crotchY - $legY) * 0.15),
            ),
            Geometry::point(0, $crotchY),
        ];

        $edges = ['neck', 'shoulder', 'armhole', 'armhole', 'side', 'hem', 'hem', 'default'];

        return $this->finishPanel([
            'code' => $o['code'] ?? ($front ? 'bodysuit-front' : 'bodysuit-back'),
            'name' => $o['name'] ?? ($front ? 'بادی جلو' : 'بادی پشت'),
            'cut' => 1,
            'mirror' => false,
            'layer' => 'outer',
        ], $outline, $edges, [
            'lines' => ['bust' => $bustY],
            'center_x' => 0.0,
            'on_fold' => true,
            'fold_edges' => [7],
            'grainline' => $this->grainline($qb * 0.4, max(1.5, $bustY * 0.4), $crotchY - 2),
            'notches' => [
                $this->notch($qb, $legY, 4, 'سر خط پا روی درز پهلو', 'leg_side'),
                $this->notch($tabHalf, $crotchY, 6, 'گوشه زبانه فاق', 'crotch_tab'),
            ],
            'markers' => [
                $this->marker('bust', 'خط سینه', 0, $bustY, $qb),
                $this->marker($front ? 'cf' : 'cb', $front ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, $neckD, 0, $crotchY),
            ],
            'meta' => array_merge([
                'part' => $front ? 'front_bodice' : 'back_bodice',
                'side' => $front ? 'front' : 'back',
                'shape' => 'bodysuit',
                'armhole_edge' => 3,
                'side_edges' => [4],
                'leg_edge' => 5,
                'bust_y' => round($bustY, 2),
                'waist_y' => round($g['side_waist_y'], 2),
                'crotch_y' => round($crotchY, 2),
                'leg_y' => round($legY, 2),
                'crotch_tab' => round($tabHalf * 2, 2),
            ], $o['meta'] ?? []),
        ]);
    }

    /* ---------------------------------------------------------------------
     |  پایین‌تنه کودک
     * ------------------------------------------------------------------- */

    /**
     * همان آزادی بالاتنه، رسیده به شلوار.
     *
     * @param  array<string, float>  $ease
     * @return array<string, float>
     */
    protected function legEase(array $ease, float $grow): array
    {
        return array_merge($ease, [
            'waist' => $this->ease($ease, 'waist', 4) + (4 * $grow),
            'hip' => $this->ease($ease, 'hip', 6) + (4 * $grow),
        ]);
    }

    /**
     * چین لبه کمر یک پنل پایین‌تنه.
     *
     * چین یک بار و فقط یک بار ثبت می‌شود، آن هم در meta.gathers. اگر هم‌زمان به
     * شکل «پیلی با دهانه» هم ثبت شود، هر کسی که طول دوخته‌شده درز را حساب می‌کند
     * دو بار از لبه کم می‌کند و آن‌وقت کمر دامن از کمر بالاتنه کوتاه‌تر درمی‌آید.
     * meta.fullness هم این‌جا کافی نیست، چون اندازه‌گیرهای عمومی نمی‌خوانندش.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function gatheredWaist(array $piece, float $amount, string $label = 'چین لبه کمر'): array
    {
        if ($amount <= 0.01) {
            return $piece;
        }

        $edges = Geometry::edgesWithTag($piece, 'waist');

        if ($edges === []) {
            return $piece;
        }

        $piece = FullnessRecorder::gathers($piece, $edges[0], $amount, ['label' => $label]);

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'لبه کمر این پنل '.$this->fa(round($amount, 1))
                .' سانتی‌متر بلندتر از جای دوختش بریده شده و همان اضافه با چین جمع می‌شود.',
        ]);

        return $piece;
    }

    /**
     * نوار کش کمر شلوار یا دامن کودک.
     *
     * کودک زیپ و دکمه کمر را خودش باز نمی‌کند؛ کمر کشی تنها بستی است که خودش از
     * پسش برمی‌آید. نوار کش کوتاه‌تر از دور کمر بریده می‌شود، ولی لبه‌ای که به آن
     * دوخته می‌شود باید از دور باسن بزرگ‌تر باشد وگرنه شلوار بالا نمی‌آید.
     *
     * @return array<string, mixed>
     */
    protected function elasticWaistPiece(string $code, array $m, array $ease, float $ratio = 0.85, float $height = 3.0): array
    {
        $waist = $this->m($m, 'waist', 56) + $this->ease($ease, 'waist', 4);

        return $this->bandPiece($code, 'نوار کش کمر', max(20.0, $waist * $ratio), $height, [
            'cut' => 1, 'part' => 'waistband',
            'meta' => [
                'stretch_ratio' => round($ratio, 2),
                'target_length' => round($waist, 2),
                'girth_role' => 'trim',
                'notes' => [
                    'کش '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از دور کمر بریده و کشیده دوخته می‌شود.',
                    'جای کش روی لبه کمر با درز دولا ساخته می‌شود تا کش پیچ نخورد.',
                ],
            ],
        ]);
    }

    /**
     * یادداشت همیشگی این خانواده روی قطعه اول.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function childNoted(array $pieces, array $notes): array
    {
        $pieces = array_values($pieces);

        if ($pieces === []) {
            return $pieces;
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $pieces;
    }
}
