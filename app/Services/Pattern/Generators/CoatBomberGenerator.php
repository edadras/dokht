<?php

namespace App\Services\Pattern\Generators;

/**
 * کاپشن بمبر.
 *
 * تنه کوتاه و گشاد با سرشانه افتاده؛ لبه پایین، مچ آستین و یقه هر سه با نوار
 * کشباف بسته می‌شوند و همین سه نوار، فرم جمع‌شده بمبر را می‌سازند. طول تنه به
 * اندازه بلندی نوار کوتاه‌تر درفت می‌شود تا قد نهایی همان چیزی باشد که کاربر
 * خواسته است.
 */
class CoatBomberGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'bomber';
    }

    public function label(): string
    {
        return 'کاپشن بمبر';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 5, 'neck_width_extra' => 2.5, 'front_neck_depth_extra' => 2, 'shoulder_slope' => 3]),
            $this->fitParam('loose'),
            $this->garmentLengthParam(20, 4, 45),
            $this->sleeveParam('set_in', 58, [
                'set_in' => 'آستین معمولی',
            ]),
            [
                'shoulder_extra' => [
                    'label' => 'افتادگی سرشانه', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                ],
                'rib_height' => [
                    'label' => 'بلندی نوار کشباف', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'rib_ratio' => [
                    'label' => 'نسبت کوتاهی نوار کشباف', 'min' => 0.6, 'max' => 1, 'step' => 0.05,
                    'default' => 0.8,
                ],
            ],
            $this->pocketParam(true, 15, 15),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.5, 'regular' => 3.0, 'loose' => 5.0]);
        $ribHeight = (float) $this->param($params, 'rib_height', 7);
        $ratio = (float) $this->param($params, 'rib_ratio', 0.8);
        $length = max(2.0, (float) $this->param($params, 'length', 20) - $ribHeight);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'bomber-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $length,
            'opening' => 'zip',
            'front_name' => 'تنه جلوی بمبر',
            'back_name' => 'تنه پشت بمبر',
            'facing_width' => 6,
            'panel' => [
                'waist_dart' => false,
                'shoulder_extra' => (float) $this->param($params, 'shoulder_extra', 3.5),
            ],
            'lining' => true,
            'lining_options' => ['shape' => 'straight', 'length' => $length],
        ]);

        $hem = ($this->panelWidthAt($pieces[0], max(1.0, $g['side_waist_y'] + $length - 0.5)) * 2)
            + ($this->panelWidthAt($pieces[1], max(1.0, $g['side_waist_y'] + $length - 0.5)) * 2);
        $neck = $this->neckOf([$pieces[0], $pieces[1]]) * 2;

        $pieces[] = $this->ribBandPiece('bomber-hem-rib', 'نوار کشباف لبه پایین', $hem, [
            'height' => $ribHeight, 'ratio' => $ratio, 'cut' => 1, 'on_fold' => true,
        ]);

        $pieces[] = $this->ribBandPiece('bomber-collar-rib', 'نوار کشباف یقه', $neck, [
            'height' => $ribHeight * 0.75, 'ratio' => min(0.95, $ratio + 0.1), 'cut' => 1, 'on_fold' => true, 'part' => 'collar',
        ]);

        $pieces[] = $this->ribBandPiece('bomber-cuff-rib', 'نوار کشباف مچ آستین', $this->m($measurements, 'wrist', 16.5) + 8, [
            'height' => $ribHeight, 'ratio' => min(0.95, $ratio + 0.1), 'cut' => 2, 'on_fold' => false, 'part' => 'cuff',
        ]);

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'bomber-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
