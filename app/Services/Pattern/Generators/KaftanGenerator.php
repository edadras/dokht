<?php

namespace App\Services\Pattern\Generators;

/**
 * کافتان.
 *
 * پیراهن بلندِ یک‌سره با آستین پیوسته به تنه؛ جلو بسته است و فقط یک چاک از یقه
 * به پایین دارد. کمرگیری ندارد و همه فرم لباس از پهنای تنه و بلندی آستین
 * می‌آید. کم‌درزترین لباس کاتالوگ است و روی پارچه‌های نقش‌دار زیبا می‌افتد.
 */
class KaftanGenerator extends BodiceGarmentBase
{
    public static function key(): string
    {
        return 'kaftan';
    }

    public function label(): string
    {
        return 'کافتان';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema(['neck_width_extra' => 2, 'front_neck_depth_extra' => 2, 'armhole_depth_extra' => 4]),
            $this->garmentLengthParam(78, 30, 130),
            [
                'ease_extra' => [
                    'label' => 'آزادی افزوده تنه (هر نیم‌قطعه)', 'min' => 1, 'max' => 16, 'step' => 0.5,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
                'underarm_drop' => [
                    'label' => 'پایین آمدن زیر بغل', 'min' => 3, 'max' => 22, 'step' => 0.5,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
                'sleeve_length' => [
                    'label' => 'بلندی آستین از زیر بغل', 'min' => 8, 'max' => 60, 'step' => 1,
                    'default' => 28, 'unit' => 'سانتی‌متر',
                ],
                'cuff_width' => [
                    'label' => 'پهنای دم آستین', 'min' => 12, 'max' => 40, 'step' => 1,
                    'default' => 22, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 35, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
                'neck_slit' => [
                    'label' => 'بلندی چاک یقه', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 18, 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 60, 'step' => 1,
                    'default' => 30, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = (float) $this->param($params, 'ease_extra', 6);
        $length = (float) $this->param($params, 'length', 78);
        $slit = (float) $this->param($params, 'neck_slit', 18);
        $vent = (float) $this->param($params, 'side_vent', 30);

        $shared = [
            'grow' => $grow,
            'length' => $length,
            'underarm_drop' => (float) $this->param($params, 'underarm_drop', 9),
            'sleeve_length' => (float) $this->param($params, 'sleeve_length', 28),
            'cuff_width' => (float) $this->param($params, 'cuff_width', 22),
            'hem_flare' => (float) $this->param($params, 'hem_flare', 10),
        ];

        $front = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'kaftan-front',
            'name' => 'تنه و آستین جلو',
        ]));

        $back = $this->kimonoPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'kaftan-back',
            'name' => 'تنه و آستین پشت',
        ]));

        if ($slit > 2) {
            $front['markers'][] = $this->marker('slit', 'چاک یقه', 0, $g['front_neck_depth'], 0, $g['front_neck_depth'] + $slit);
            $front['meta']['neck_slit'] = round($slit, 2);
        }

        if ($vent > 1) {
            foreach ([&$front, &$back] as &$panel) {
                $panel['meta']['vent'] = round($vent, 2);
                $panel['meta']['notes'] = array_merge($panel['meta']['notes'] ?? [], [
                    'چاک پهلو به بلندی '.$this->fa(round($vent, 1)).' سانتی‌متر از لبه پایین باز می‌ماند.',
                ]);
            }

            unset($panel);
        }

        $pieces = [
            $front,
            $back,
            $this->bandPiece('kaftan-neck-binding', 'نوار یقه و چاک', ($g['neck_width'] * 3) + ($slit * 2) + 8, 5, [
                'cut' => 1, 'part' => 'facing',
                'meta' => ['bias' => true, 'notes' => ['نوار اریب که دور یقه و دو لبه چاک را می‌پوشاند.']],
            ]),
            $this->backNeckFacingPiece($g, ['prefix' => 'kaftan-', 'width' => 7]),
        ];

        return $this->finishBlock($pieces, $g, $grow);
    }
}
