<?php

namespace App\Services\Pattern\Generators;

/**
 * هودی.
 *
 * سویشرت کلاه‌دار از پارچه کشباف (دورس یا فوتر): تنه گشاد و بدون ساسون، سرشانه
 * افتاده، کلاه دو‌پنلی که به یقه دوخته می‌شود، جیب کانگورویی یک‌تکه روی جلو و
 * نوار کشباف روی لبه پایین و مچ. قد تنه به اندازه بلندی نوار کوتاه‌تر درفت
 * می‌شود تا قد نهایی درست دربیاید.
 */
class HoodieGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'hoodie';
    }

    public function label(): string
    {
        return 'هودی';
    }

    /** این مدل کلاه دارد؛ سویشرت ساده آن را خاموش می‌کند. */
    protected function hasHood(array $params): bool
    {
        return $this->flag($params, 'hood', true);
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 4.5, 'neck_width_extra' => 2.5, 'front_neck_depth_extra' => 2.5, 'shoulder_slope' => 3]),
            $this->fitParam('loose'),
            $this->garmentLengthParam(22, 4, 55),
            $this->openingParam('closed', 0, [
                'closed' => 'جلو بسته (از سر پوشیده می‌شود)',
                'zip' => 'زیپ سرتاسری',
            ]),
            $this->sleeveParam('set_in', 58, [
                'set_in' => 'آستین معمولی',
            ]),
            [
                'hood' => [
                    'label' => 'کلاه داشته باشد', 'type' => 'toggle', 'default' => true,
                ],
                'hood_height' => [
                    'label' => 'بلندی کلاه', 'min' => 26, 'max' => 44, 'step' => 1,
                    'default' => 36, 'unit' => 'سانتی‌متر',
                ],
                'shoulder_extra' => [
                    'label' => 'افتادگی سرشانه', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
                'rib_height' => [
                    'label' => 'بلندی نوار کشباف', 'min' => 4, 'max' => 12, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'rib_ratio' => [
                    'label' => 'نسبت کوتاهی نوار کشباف', 'min' => 0.6, 'max' => 1, 'step' => 0.05,
                    'default' => 0.85,
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
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 2.5, 'loose' => 4.5]);
        $ribHeight = (float) $this->param($params, 'rib_height', 6);
        $ratio = (float) $this->param($params, 'rib_ratio', 0.85);
        $length = max(2.0, (float) $this->param($params, 'length', 22) - $ribHeight);
        $hood = $this->hasHood($params);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => static::key().'-',
            'grow' => $grow,
            'shape' => 'straight',
            'length' => $length,
            'facing' => false,
            'front_name' => 'تنه جلو',
            'back_name' => 'تنه پشت',
            'panel' => [
                'waist_dart' => false,
                'shoulder_extra' => (float) $this->param($params, 'shoulder_extra', 3),
            ],
            'collar' => $hood ? 'hood' : 'none',
            'hood' => [
                'height' => (float) $this->param($params, 'hood_height', 36),
                'width' => 26,
            ],
        ]);

        $hemWidth = ($this->panelWidthAt($pieces[0], max(1.0, $g['side_waist_y'] + $length - 0.5)) * 2)
            + ($this->panelWidthAt($pieces[1], max(1.0, $g['side_waist_y'] + $length - 0.5)) * 2);

        $pieces[] = $this->ribBandPiece(static::key().'-hem-rib', 'نوار کشباف لبه پایین', $hemWidth, [
            'height' => $ribHeight, 'ratio' => $ratio, 'cut' => 1, 'on_fold' => true,
        ]);

        $pieces[] = $this->ribBandPiece(static::key().'-cuff-rib', 'نوار کشباف مچ آستین', $this->m($measurements, 'wrist', 16.5) + 8, [
            'height' => $ribHeight, 'ratio' => min(0.95, $ratio + 0.05), 'cut' => 2, 'on_fold' => false, 'part' => 'cuff',
        ]);

        if (! $hood) {
            $neck = $this->neckOf([$pieces[0], $pieces[1]]) * 2;

            $pieces[] = $this->ribBandPiece(static::key().'-neck-rib', 'نوار کشباف یقه', $neck, [
                'height' => $ribHeight * 0.5, 'ratio' => min(0.95, $ratio + 0.05), 'cut' => 1, 'on_fold' => true, 'part' => 'collar',
            ]);
        }

        if ($this->flag($params, 'kangaroo', true)) {
            $pieces[] = $this->kangarooPocketPiece(
                $g['quarter_bust'] + $grow - 1,
                min(20.0, max(12.0, $length + 8)),
                ['prefix' => static::key().'-', 'opening' => 11],
            );
        }

        return $this->finishBlock($pieces, $g, $grow);
    }
}
