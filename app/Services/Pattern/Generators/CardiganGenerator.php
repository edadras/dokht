<?php

namespace App\Services\Pattern\Generators;

/**
 * کاردیگان.
 *
 * ژاکت جلوباز از پارچه کشباف: بدون ساسون، با حلقه آستین گشادتر و نوارِ یک‌سره‌ای
 * که از لبه پایینِ جلو تا دور یقه و تا لبه پایینِ سمت دیگر می‌رود. چون پارچه
 * کشباف است، نوار کمی کوتاه‌تر از مسیر خودش بریده می‌شود تا لبه جلو موج نیندازد.
 */
class CardiganGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'cardigan';
    }

    public function label(): string
    {
        return 'کاردیگان';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 3.5, 'neck_width_extra' => 1.5, 'shoulder_slope' => 4]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(30, 5, 95),
            $this->sleeveParam('set_in', 58),
            [
                'band_width' => [
                    'label' => 'پهنای نوار لبه جلو', 'min' => 2, 'max' => 10, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'band_ratio' => [
                    'label' => 'نسبت کوتاهی نوار', 'min' => 0.8, 'max' => 1, 'step' => 0.02,
                    'default' => 0.94,
                    'hint' => 'نوار کمی کوتاه‌تر بریده می‌شود تا لبه جلو موج نیندازد.',
                ],
                'buttons' => [
                    'label' => 'تعداد دکمه روی نوار', 'min' => 0, 'max' => 12, 'step' => 1, 'default' => 5,
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 15, 'step' => 0.5,
                    'default' => 1, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 13, 14),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.0, 'regular' => 1.5, 'loose' => 3.5]);
        $length = (float) $this->param($params, 'length', 30);
        $bandWidth = (float) $this->param($params, 'band_width', 4);
        $ratio = (float) $this->param($params, 'band_ratio', 0.94);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'cardigan-',
            'grow' => $grow,
            'shape' => 'straight',
            'opening' => 'open',
            'facing' => false,
            'front_name' => 'تنه جلوی کاردیگان',
            'back_name' => 'تنه پشت کاردیگان',
            'panel' => ['waist_dart' => false],
        ]);

        $front = $pieces[0];
        $back = $pieces[1];

        // مسیر نوار: از لبه پایین جلو تا سرشانه، به‌علاوه نیم یقه پشت
        $path = ($g['front_waist_y'] + $length - $g['front_neck_depth'])
            + (float) ($front['meta']['neck_length'] ?? 0)
            + (float) ($back['meta']['neck_length'] ?? 0);

        $pieces[] = $this->bandPiece('cardigan-band', 'نوار یک‌سره لبه جلو و یقه', max(20.0, $path * $ratio), $bandWidth * 2, [
            'cut' => 2, 'on_fold' => false, 'part' => 'placket', 'fold_line' => true,
            'meta' => [
                'stretch_ratio' => $ratio,
                'target_length' => round($path, 2),
                'buttons' => (int) $this->param($params, 'buttons', 5),
                'notes' => [
                    'نوار '.$this->fa(round((1 - $ratio) * 100)).' درصد کوتاه‌تر از مسیر لبه جلو و یقه بریده می‌شود و کشیده دوخته می‌گردد.',
                ],
            ],
        ]);

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'cardigan-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
