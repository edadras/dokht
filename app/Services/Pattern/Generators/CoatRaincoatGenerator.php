<?php

namespace App\Services\Pattern\Generators;

/**
 * بارانی.
 *
 * روی پارچه ضدآب دوخته می‌شود، پس هرچه درز کمتر باشد بهتر است: آستین رگلان
 * انتخاب می‌شود تا درز حلقه حذف گردد و کلاه به یقه دوخته می‌شود. تنه گشاد است
 * تا روی لباس ضخیم بنشیند و لبه پایین کمی باز می‌شود.
 */
class CoatRaincoatGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'raincoat';
    }

    public function label(): string
    {
        return 'بارانی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['armhole_depth_extra' => 6, 'neck_width_extra' => 3, 'front_neck_depth_extra' => 3, 'shoulder_slope' => 3.5]),
            $this->fitParam('regular'),
            $this->garmentLengthParam(66, 25, 120),
            $this->openingParam('zip', 0, [
                'zip' => 'زیپ سرتاسری با لتِ رویی',
                'button' => 'دکمه فشاری',
            ]),
            $this->sleeveParam('set_in', 60, [
                'set_in' => 'آستین معمولی',
                'two_piece' => 'آستین دوتکه خیاطی',
            ]),
            [
                'hood' => [
                    'label' => 'کلاه داشته باشد', 'type' => 'toggle', 'default' => true,
                ],
                'hood_height' => [
                    'label' => 'بلندی کلاه', 'min' => 28, 'max' => 46, 'step' => 1,
                    'default' => 38, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 25, 'step' => 1,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'storm_flap' => [
                    'label' => 'لتِ روی زیپ', 'type' => 'toggle', 'default' => true,
                ],
            ],
            $this->pocketParam(true, 16, 17),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 2.5, 'loose' => 4.0]);
        $length = (float) $this->param($params, 'length', 66);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'raincoat-',
            'grow' => $grow,
            'shape' => 'straight',
            'front_name' => 'تنه جلوی بارانی',
            'back_name' => 'تنه پشت بارانی',
            'facing_width' => 8,
            'panel' => ['waist_dart' => false],
            'collar' => $this->flag($params, 'hood', true) ? 'hood' : 'stand',
            'collar_height' => 6,
            'hood' => [
                'height' => (float) $this->param($params, 'hood_height', 38),
                'width' => 27,
                'name' => 'کلاه بارانی',
            ],
        ]);

        if ($this->flag($params, 'storm_flap', true)) {
            $pieces[] = $this->bandPiece('raincoat-storm-flap', 'لتِ روی زیپ', $g['front_waist_y'] + $length, 8, [
                'cut' => 1, 'part' => 'placket',
                'meta' => ['notes' => ['روی زیپ دوخته می‌شود تا آب از درز زیپ نفوذ نکند.']],
            ]);
        }

        $pieces = array_merge($pieces, $this->pocketSet($params, ['prefix' => 'raincoat-']));

        return $this->finishBlock($pieces, $g, $grow);
    }
}
