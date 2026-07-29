<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * بادی نوزاد.
 *
 * سخت‌ترین لباس این خانواده، چون دو کارِ ناسازگار را با هم می‌خواهد: باید از سر
 * نوزاد رد شود و باید روی تن بچسبد. سه تصمیم این تناقض را حل می‌کند:
 *
 *   ۱. یقه پاکتی. یقه آن‌قدر پهن باز می‌شود که دور تمام‌شده‌اش از دور سر بزرگ‌تر
 *      باشد، و لبه سرشانه جلو روی پشت می‌افتد؛ همان دو مثلثِ رویهم که در بادی
 *      نوزاد می‌بینید. با کشیدنِ یقه لباس از سر رد می‌شود و بعد دوباره جمع
 *      می‌شود. اگر پارچه یا اندازه اجازه ندهد، چاک پشت با قزن باز می‌شود.
 *   ۲. زبانه فاق با قزن. برای عوض کردن پوشک لباس درنمی‌آید؛ فقط زبانه باز
 *      می‌شود. زبانه جلو عمداً بلندتر است تا روی زبانه پشت بیفتد و قزن‌ها روی
 *      همان رویهم‌آمدن بنشینند.
 *   ۳. خط پای منحنی. لای پا باید باز بماند تا پوشک جا شود؛ لبه پایینِ صافِ یک
 *      تاپ این کار را نمی‌کند.
 */
class ChildBodysuitGenerator extends ChildBaseGenerator
{
    public static function key(): string
    {
        return 'child_bodysuit';
    }

    public function label(): string
    {
        return 'بادی نوزاد';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->childSchema([
                'shoulder_slope' => 2,
                'neck_width_extra' => 2,
                'front_neck_depth_extra' => 1.5,
                'back_neck_depth' => 1.5,
                'armhole_depth_extra' => 1.5,
            ]),
            $this->childEaseSchema(1.0, 1.5),
            $this->sleeveParam('set_in', 12, [
                'set_in' => 'آستین کوتاه',
                'none' => 'بی‌آستین (نوار حلقه)',
            ]),
            [
                'rise_ease' => [
                    'label' => 'آزادی قد فاق', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                    'hint' => 'قد فاق از دور باسن حساب می‌شود؛ این عدد جای پوشک را باز می‌کند.',
                ],
                'leg_rise' => [
                    'label' => 'بالا آمدن خط پا', 'min' => 3, 'max' => 16, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'tab' => [
                    'label' => 'پهنای زبانه فاق', 'min' => 5, 'max' => 16, 'step' => 0.5,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'crotch_overlap' => [
                    'label' => 'رویهم‌آمدن زبانه جلو', 'min' => 1, 'max' => 7, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'snaps' => [
                    'label' => 'تعداد قزن فاق', 'min' => 2, 'max' => 5, 'step' => 1, 'default' => 3,
                ],
                'envelope' => [
                    'label' => 'یقه پاکتی (رویهم سرشانه)', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->childGrow($params);

        // دو سانتی‌متر حاشیه برای یقه پاکتی بس است؛ یقه در پارچه کشباف با کشیدن
        // هم باز می‌شود، ولی روی الگو حسابش را نمی‌کنیم که اگر پارچه بافته بود
        // هم لباس از سر رد شود
        $clearance = $this->headClearance($g, $measurements, [
            'margin' => $this->flag($params, 'envelope', true) ? 2.0 : 5.0,
            'max_depth' => 5.0,
        ]);

        $rise = ($this->m($measurements, 'hip', 64) / 4) + 2.5 + (float) $this->param($params, 'rise_ease', 2.5);
        $overlap = (float) $this->param($params, 'crotch_overlap', 3);
        $tab = (float) $this->param($params, 'tab', 8);

        $shared = [
            'grow' => $grow,
            'rise' => $rise,
            'tab' => $tab,
            'leg_rise' => (float) $this->param($params, 'leg_rise', 7),
            'neck_width_extra' => $clearance['width_extra'],
            'meta' => ['knit' => true],
        ];

        $front = $this->bodysuitPanel($g, array_merge($shared, [
            'side' => 'front',
            'neck_depth_extra' => $clearance['front_depth_extra'],
            'crotch_extra' => $overlap,
            'code' => 'child-bodysuit-front',
            'name' => 'بادی جلو (زبانه بلند)',
        ]));

        $back = $this->bodysuitPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'child-bodysuit-back',
            'name' => 'بادی پشت',
        ]));

        $snaps = max(2, (int) $this->param($params, 'snaps', 3));
        $front = $this->markSnaps($front, $tab, $overlap, $snaps);

        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet(
            $measurements,
            $ease,
            $params,
            $this->armholeOf([$front, $back]),
            $g,
            ['prefix' => 'child-bodysuit-'],
        ));

        $pieces[] = $this->neckBandPiece($this->neckOf([$front, $back]), [
            'prefix' => 'child-bodysuit-',
            'ratio' => 0.9,
            'height' => 2.5,
            'name' => 'نوار یقه پاکتی',
        ]);

        // نوار خط پا از خودِ منحنی خط پا اندازه گرفته می‌شود، نه از عددی حدسی
        $legEdge = Geometry::edgeLength($front['outline'], (int) ($front['meta']['leg_edge'] ?? 5))
            + Geometry::edgeLength($back['outline'], (int) ($back['meta']['leg_edge'] ?? 5));

        $pieces[] = $this->bandPiece('child-bodysuit-leg-binding', 'نوار خط پا', max(14.0, $legEdge * 0.9), 2.5, [
            'cut' => 2, 'part' => 'facing',
            'meta' => [
                'stretch_ratio' => 0.9,
                'target_length' => round($legEdge, 2),
                'girth_role' => 'trim',
                'notes' => ['برای هر پا یک نوار؛ ده درصد کوتاه‌تر بریده می‌شود تا خط پا دور ران جمع بماند.'],
            ],
        ]);

        if ((string) $this->param($params, 'sleeve_style', 'set_in') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf([$front, $back]), [
                'prefix' => 'child-bodysuit-', 'height' => 2.5,
            ]);
        }

        $pieces = $this->stampHeadClearance($pieces, $clearance, $g, ['notion' => 'snap']);

        return $this->finishBlock($this->childNoted($pieces, [
            'زبانه فاق جلو '.$this->fa($overlap).' سانتی‌متر بلندتر از پشت است و روی آن می‌افتد؛'
                .' قزن‌ها روی همین رویهم‌آمدن می‌نشینند و برای عوض کردن پوشک باز می‌شوند.',
            'با پارچه کشباف پنبه‌ای بدوزید و درزها را با سردوز تمام کنید؛ درز زبر روی پوست نوزاد می‌ماند.',
        ]), $g, $grow);
    }

    /**
     * ردیف قزن روی زبانه فاق.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function markSnaps(array $piece, float $tab, float $overlap, int $count): array
    {
        [, , , $maxY] = Geometry::bounds($piece['outline']);
        $y = round($maxY - ($overlap / 2), 2);
        $half = max(1.5, $tab / 2);
        $step = $count > 1 ? $half / ($count - 1) : 0.0;

        for ($i = 0; $i < $count; $i++) {
            $piece['drills'][] = [
                'key' => 'snap_'.($i + 1),
                'label' => 'قزن فاق '.$this->fa($i + 1),
                'x' => round($step * $i, 2),
                'y' => $y,
            ];
        }

        $piece['meta']['notions'][] = [
            'type' => 'snap',
            // قطعه روی تای پارچه بریده می‌شود، پس ردیف قزن هم قرینه می‌شود؛
            // نقطه روی خط مرکز یکی است و بقیه دو تا
            'label' => 'قزن زبانه فاق',
            'count' => max(1, ($count * 2) - 1),
        ];

        return $piece;
    }
}
