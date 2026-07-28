<?php

namespace App\Services\Pattern\Generators;

/**
 * تی‌شرت بچگانه.
 *
 * از پارچه کشباف با آزادی کم؛ ساسون ندارد و یقه با نوار کشباف بسته می‌شود.
 * نکته کلیدی لباس کودک این است که دور یقه باید دست‌کم به اندازه دور سر باز شود،
 * وگرنه از سر رد نمی‌شود؛ اگر عرض یقه کم باشد یک چاک کوتاه روی مرکز پشت
 * پیشنهاد می‌شود.
 */
class ChildTshirtGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'child_tshirt';
    }

    public function label(): string
    {
        return 'تی‌شرت بچگانه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['shoulder_slope' => 2.5, 'neck_width_extra' => 2, 'front_neck_depth_extra' => 1.5, 'back_neck_depth' => 1.5, 'armhole_depth_extra' => 1.5],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            $this->garmentLengthParam(10, 0, 30),
            $this->sleeveParam('set_in', 22, [
                'set_in' => 'آستین (کوتاه یا بلند)',
                'none' => 'بدون آستین',
            ]),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 0, 'max' => 5, 'step' => 0.5,
                    'default' => 1, 'unit' => 'سانتی‌متر',
                ],
                'neck_band' => [
                    'label' => 'نوار یقه کشباف', 'type' => 'toggle', 'default' => true,
                ],
                'band_ratio' => [
                    'label' => 'نسبت کوتاهی نوار یقه', 'min' => 0.75, 'max' => 1, 'step' => 0.05,
                    'default' => 0.88,
                ],
                'back_slit' => [
                    'label' => 'بلندی چاک مرکز پشت', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                    'hint' => 'اگر دور یقه از دور سر کودک کوچک‌تر بود، این چاک را باز کنید.',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 1);
        $length = (float) $this->param($params, 'length', 10);
        $slit = (float) $this->param($params, 'back_slit', 0);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'grow' => $grow,
            'waist_dart' => false,
            'bust_dart' => false,
            'bottom_tag' => 'hem',
            'meta' => ['knit' => true],
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'child-tshirt-front',
            'name' => 'تنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'child-tshirt-back',
            'name' => 'تنه پشت',
        ]));

        [$front, $back] = $this->walkSideSeams($front, $back);

        if ($slit > 0.5) {
            $back['markers'][] = $this->marker('slit', 'چاک مرکز پشت', 0, $g['back_neck_depth'], 0, $g['back_neck_depth'] + $slit);
            $back['meta']['back_slit'] = round($slit, 2);
        }

        $pieces = [$front, $back];

        $pieces = array_merge($pieces, $this->sleeveSet(
            $measurements,
            $ease,
            $params,
            $this->armholeOf([$front, $back]),
            $g,
            ['prefix' => 'child-tshirt-'],
        ));

        if ($this->flag($params, 'neck_band', true)) {
            $pieces[] = $this->neckBandPiece($this->neckOf([$front, $back]), [
                'prefix' => 'child-tshirt-',
                'ratio' => (float) $this->param($params, 'band_ratio', 0.88),
                'height' => 3,
            ]);
        }

        return $this->finishBlock($pieces, $g, $grow);
    }
}
