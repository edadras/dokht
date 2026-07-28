<?php

namespace App\Services\Pattern\Generators;

/**
 * هودی بچگانه.
 *
 * همان منطق هودی بزرگسال با اندازه‌های کودک: یقه پهن‌تر (سر کودک نسبت به تنش
 * بزرگ‌تر است و باید راحت رد شود)، آزادی بیشتر برای بازی و رشد، و کلاه کوچک‌تر.
 * لبه پایین و مچ با نوار کشباف بسته می‌شوند.
 */
class ChildHoodieGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'child_hoodie';
    }

    public function label(): string
    {
        return 'هودی بچگانه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(
                ['shoulder_slope' => 2.5, 'neck_width_extra' => 2.5, 'front_neck_depth_extra' => 2, 'back_neck_depth' => 2, 'armhole_depth_extra' => 3],
                ['shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra', 'back_neck_depth', 'armhole_depth_extra', 'bodice_length_extra'],
            ),
            $this->garmentLengthParam(14, 2, 40),
            $this->sleeveParam('set_in', 40, [
                'set_in' => 'آستین معمولی',
            ]),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 0.5, 'max' => 8, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                    'hint' => 'کودک باید بتواند راحت بازی کند و لباس یک فصل بیشتر اندازه‌اش بماند.',
                ],
                'hood' => [
                    'label' => 'کلاه داشته باشد', 'type' => 'toggle', 'default' => true,
                ],
                'hood_height' => [
                    'label' => 'بلندی کلاه', 'min' => 20, 'max' => 36, 'step' => 1,
                    'default' => 28, 'unit' => 'سانتی‌متر',
                ],
                'shoulder_extra' => [
                    'label' => 'افتادگی سرشانه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => 1.5, 'unit' => 'سانتی‌متر',
                ],
                'rib_height' => [
                    'label' => 'بلندی نوار کشباف', 'min' => 3, 'max' => 9, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'kangaroo' => [
                    'label' => 'جیب کانگورویی', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 2.5);
        $ribHeight = (float) $this->param($params, 'rib_height', 5);
        $length = max(2.0, (float) $this->param($params, 'length', 14) - $ribHeight);
        $hood = $this->flag($params, 'hood', true);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'child-hoodie-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $length,
            'opening' => 'closed',
            'facing' => false,
            'front_name' => 'تنه جلو',
            'back_name' => 'تنه پشت',
            'panel' => [
                'waist_dart' => false,
                'bust_dart' => false,
                'shoulder_extra' => (float) $this->param($params, 'shoulder_extra', 1.5),
            ],
            'collar' => $hood ? 'hood' : 'none',
            'hood' => [
                'height' => (float) $this->param($params, 'hood_height', 28),
                'width' => 20,
                'name' => 'کلاه بچگانه',
            ],
        ]);

        $hemWidth = ($this->panelWidthAt($pieces[0], max(1.0, $g['side_waist_y'] + $length - 0.5)) * 2)
            + ($this->panelWidthAt($pieces[1], max(1.0, $g['side_waist_y'] + $length - 0.5)) * 2);

        $pieces[] = $this->ribBandPiece('child-hoodie-hem-rib', 'نوار کشباف لبه پایین', $hemWidth, [
            'height' => $ribHeight, 'ratio' => 0.85, 'cut' => 1, 'on_fold' => true,
        ]);

        $pieces[] = $this->ribBandPiece('child-hoodie-cuff-rib', 'نوار کشباف مچ آستین', $this->m($measurements, 'wrist', 12) + 6, [
            'height' => $ribHeight, 'ratio' => 0.9, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff',
        ]);

        if (! $hood) {
            $pieces[] = $this->ribBandPiece('child-hoodie-neck-rib', 'نوار کشباف یقه', $this->neckOf([$pieces[0], $pieces[1]]) * 2, [
                'height' => 3, 'ratio' => 0.88, 'cut' => 1, 'on_fold' => true, 'part' => 'collar',
            ]);
        }

        if ($this->flag($params, 'kangaroo', true)) {
            $pieces[] = $this->kangarooPocketPiece(
                max(8.0, $g['quarter_bust'] + $grow - 1),
                min(14.0, max(9.0, $length + 6)),
                ['prefix' => 'child-hoodie-', 'opening' => 8],
            );
        }

        return $this->finishBlock($pieces, $g, $grow);
    }
}
