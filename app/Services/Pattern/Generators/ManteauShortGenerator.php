<?php

namespace App\Services\Pattern\Generators;

/**
 * مانتو کوتاه اسپرت.
 *
 * تا روی باسن می‌آید، جلو زیپ می‌خورد و سرشانه کمی افتاده است تا حالت اسپرت
 * بگیرد. لبه پایین و مچ آستین با نوار کشباف بسته می‌شود، پس لباس روی تن جمع
 * می‌ماند و شبیه کاپشن سبک می‌افتد.
 */
class ManteauShortGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'manteau_short';
    }

    public function label(): string
    {
        return 'مانتو کوتاه اسپرت';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['shoulder_slope' => 3.5, 'armhole_depth_extra' => 4]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(24, 4, 55),
            $this->openingParam('zip', 0, [
                'zip' => 'زیپ سرتاسری',
                'button' => 'دکمه',
                'open' => 'بدون بست',
            ]),
            $this->collarParam('stand', [
                'none' => 'بدون یقه',
                'stand' => 'یقه ایستاده',
                'hood' => 'کلاه',
            ], 7),
            $this->sleeveParam('set_in', 58),
            [
                'shoulder_extra' => [
                    'label' => 'افتادگی سرشانه', 'min' => 0, 'max' => 8, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'rib_hem' => [
                    'label' => 'نوار کشباف لبه پایین و مچ', 'type' => 'toggle', 'default' => true,
                ],
                'rib_height' => [
                    'label' => 'بلندی نوار کشباف', 'min' => 3, 'max' => 12, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 14, 15),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 2.5, 'loose' => 4.5]);
        $length = (float) $this->param($params, 'length', 24);
        $rib = $this->flag($params, 'rib_hem', true);
        $ribHeight = (float) $this->param($params, 'rib_height', 6);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'manteau-short-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $rib ? max(4.0, $length - $ribHeight) : $length,
            'front_name' => 'تنه جلوی مانتو کوتاه',
            'back_name' => 'تنه پشت مانتو کوتاه',
            'facing_width' => 6,
            'panel' => [
                'shoulder_extra' => (float) $this->param($params, 'shoulder_extra', 2),
                'waist_dart' => false,
            ],
            'hood' => ['height' => 36, 'width' => 26],
        ]);

        if ($rib) {
            $hemWidth = $this->panelWidthAt($pieces[0], max(1.0, $g['side_waist_y'] + $length - $ribHeight - 0.5)) * 2
                + ($this->panelWidthAt($pieces[1], max(1.0, $g['side_waist_y'] + $length - $ribHeight - 0.5)) * 2);

            $pieces[] = $this->ribBandPiece('manteau-short-hem-rib', 'نوار کشباف لبه پایین', $hemWidth, [
                'height' => $ribHeight, 'cut' => 1, 'on_fold' => true,
            ]);

            $pieces[] = $this->ribBandPiece('manteau-short-cuff-rib', 'نوار کشباف مچ آستین', $this->m($measurements, 'wrist', 16.5) + 6, [
                'height' => $ribHeight, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff', 'ratio' => 0.9,
            ]);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'manteau-short-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
