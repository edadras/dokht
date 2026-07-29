<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن کوتاه (شیت).
 *
 * یک‌تکه از سرشانه تا لبه دامن؛ درز پهلو یک‌سره می‌آید و ساسون کمر «بادامی»
 * می‌شود، یعنی هم به سمت سینه و هم به سمت باسن بسته می‌گردد. زیپ روی مرکز پشت
 * است. ساده‌ترین پیراهن قالبی و پایه بیشتر پیراهن‌های مجلسی کوتاه.
 */
class DressShortGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'dress_short';
    }

    public function label(): string
    {
        return 'پیراهن کوتاه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 1, 'neck_width_extra' => 0.5, 'front_neck_depth_extra' => 2, 'waist_dart_share' => 0.65]),
            $this->fitParam('fitted'),
            $this->garmentLengthParam(48, 20, 80, 'بلندی دامن از کمر'),
            $this->sleeveParam('none', 30, [
                'none' => 'بدون آستین',
                'set_in' => 'آستین معمولی',
            ]),
            [
                'hem_flare' => [
                    'label' => 'باز شدن دم دامن در هر پهلو', 'min' => -4, 'max' => 20, 'step' => 0.5,
                    'default' => 1, 'unit' => 'سانتی‌متر',
                ],
                'back_zip' => [
                    'label' => 'زیپ مرکز پشت', 'type' => 'toggle', 'default' => true,
                ],
                'back_vent' => [
                    'label' => 'بلندی چاک پشت', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.0]);
        $zip = $this->flag($params, 'back_zip', true);
        $length = (float) $this->param($params, 'length', 48);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'dress-short-',
            'grow' => $grow,
            'shape' => 'fitted',
            'opening' => 'closed',
            'facing' => false,
            'bust_dart' => true,
            'back_seam' => $zip,
            'front_name' => 'جلوی پیراهن',
            'back_name' => $zip ? 'پشت پیراهن (زیپ مرکزی)' : 'پشت پیراهن',
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 4)],
        ]);

        $vent = (float) $this->param($params, 'back_vent', 12);

        if ($vent > 1 && $zip) {
            $pieces[1]['meta']['back_vent'] = round($vent, 2);
            $pieces[1]['meta']['notes'] = array_merge($pieces[1]['meta']['notes'] ?? [], [
                'چاک مرکز پشت به بلندی '.$this->fa(round($vent, 1)).' سانتی‌متر باز می‌ماند.',
            ]);
        }

        if ($zip) {
            $pieces[1] = $this->markBackZip($pieces[1], $g);
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'dress-short-', 'width' => 6]);
        $pieces[] = $this->armholeBindingPiece($this->armholeOf([$pieces[0], $pieces[1]]), ['prefix' => 'dress-short-']);

        return $this->finishBlock($pieces, $g, $grow);
    }
}
